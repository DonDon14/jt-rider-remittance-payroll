<?php

namespace App\Controllers;

use App\Models\AnnouncementModel;
use App\Models\DeliveryRecordModel;
use App\Models\DeliverySubmissionModel;
use App\Models\PayrollModel;
use App\Models\PayrollAdjustmentModel;
use App\Models\RemittanceModel;
use App\Models\RiderModel;
use App\Models\RiderCommissionRateModel;
use App\Models\ShortagePaymentModel;
use App\Models\UserModel;
use Dompdf\Dompdf;

class AdminController extends BaseController
{
    private array $denominations = [
        'denom_025' => 0.25,
        'denom_1' => 1,
        'denom_5' => 5,
        'denom_10' => 10,
        'denom_20' => 20,
        'denom_50' => 50,
        'denom_100' => 100,
        'denom_500' => 500,
        'denom_1000' => 1000,
    ];

    public function index()
    {
        $riderModel = new RiderModel();
        $deliveryModel = new DeliveryRecordModel();
        $remittanceModel = new RemittanceModel();
        $payrollModel = new PayrollModel();
        $adjustmentModel = new PayrollAdjustmentModel();
        $submissionModel = new DeliverySubmissionModel();

        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        $summary = [
            'riders' => $riderModel->where('is_active', 1)->countAllResults(),
            'today_deliveries' => (int) ($deliveryModel->selectSum('successful_deliveries')->where('delivery_date', $today)->first()['successful_deliveries'] ?? 0),
            'today_salary_earnings' => (float) ($deliveryModel->selectSum('total_due')->where('delivery_date', $today)->first()['total_due'] ?? 0),
            'today_expected_remittance' => (float) ($deliveryModel->selectSum('expected_remittance')->where('delivery_date', $today)->first()['expected_remittance'] ?? 0),
            'today_remitted' => (float) ($remittanceModel->selectSum('total_remitted')->where('delivery_date', $today)->first()['total_remitted'] ?? 0),
            'month_payroll' => (float) ($payrollModel->selectSum('net_pay')->where('month_year', $monthStart)->first()['net_pay'] ?? 0),
            'open_shortages' => count(array_filter($this->getShortageBalances(), static fn (array $item): bool => $item['outstanding_balance'] > 0)),
            'overdue_remittances' => count(array_filter($this->getPendingRemittances(), static fn (array $item): bool => $item['aging_days'] > 0)),
            'month_adjustments' => (float) ($adjustmentModel->selectSum('amount')->where('adjustment_date >=', $monthStart)->where('adjustment_date <=', $monthEnd)->first()['amount'] ?? 0),
            'pending_submission_requests' => $submissionModel->where('status', 'PENDING')->countAllResults(),
        ];

        $recentDeliveries = $deliveryModel
            ->select('delivery_records.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = delivery_records.rider_id')
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('delivery_records.id', 'DESC')
            ->findAll(8);

        $recentRemittances = $remittanceModel
            ->select('remittances.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = remittances.rider_id')
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('remittances.id', 'DESC')
            ->findAll(8);

        $performance = $this->getRiderPerformanceRanking($monthStart, $monthEnd);
        $announcements = array_slice($this->getActiveAnnouncements(), 0, 5);
        $pendingSubmissions = $submissionModel
            ->select('delivery_submissions.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = delivery_submissions.rider_id')
            ->where('delivery_submissions.status', 'PENDING')
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('delivery_submissions.id', 'DESC')
            ->findAll(5);

        return view('admin/dashboard', array_merge(
            $this->adminBaseData('dashboard'),
            [
                'summary' => $summary,
                'today' => $today,
                'recentDeliveries' => $recentDeliveries,
                'recentRemittances' => $recentRemittances,
                'topRiders' => array_slice($performance, 0, 5),
                'lowRiders' => array_slice(array_reverse($performance), 0, 5),
                'announcements' => $announcements,
                'pendingSubmissions' => $pendingSubmissions,
            ]
        ));
    }

    public function riders()
    {
        $search = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));
        $riderModel = new RiderModel();
        $builder = $riderModel
            ->select('riders.*, users.username, users.is_active AS user_is_active')
            ->join('users', 'users.rider_id = riders.id AND users.role = "rider"', 'left')
            ->orderBy('name', 'ASC');

        if ($search !== '') {
            $builder
                ->groupStart()
                ->like('riders.name', $search)
                ->orLike('riders.rider_code', $search)
                ->orLike('users.username', $search)
                ->groupEnd();
        }

        if ($status === 'ACTIVE') {
            $builder->where('riders.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $builder->where('riders.is_active', 0);
        }

        return view('admin/riders', array_merge(
            $this->adminBaseData('riders'),
            [
                'search' => $search,
                'status' => $status,
                'riders' => $builder->findAll(),
            ]
        ));
    }

    public function deliveries()
    {
        $deliveryModel = new DeliveryRecordModel();

        return view('admin/deliveries', array_merge(
            $this->adminBaseData('deliveries'),
            [
                'today' => date('Y-m-d'),
                'dailyRecords' => $deliveryModel
                    ->select('delivery_records.*, riders.name, riders.rider_code')
                    ->join('riders', 'riders.id = delivery_records.rider_id')
                    ->orderBy('delivery_date', 'DESC')
                    ->orderBy('delivery_records.id', 'DESC')
                    ->findAll(30),
            ]
        ));
    }

    public function deliveryHistory()
    {
        $search = trim((string) $this->request->getGet('q'));
        $startDate = trim((string) $this->request->getGet('start_date'));
        $endDate = trim((string) $this->request->getGet('end_date'));
        $riderId = trim((string) $this->request->getGet('rider_id'));
        $remittanceStatus = trim((string) $this->request->getGet('remittance_status'));

        $records = $this->getFilteredHistoryRecords($search, $startDate, $endDate, $riderId, $remittanceStatus);

        return view('admin/delivery_history', array_merge(
            $this->adminBaseData('history'),
            [
                'search' => $search,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'selectedRiderId' => $riderId,
                'remittanceStatus' => $remittanceStatus,
                'historySummary' => [
                    'records' => count($records),
                    'successful' => array_sum(array_column($records, 'successful_deliveries')),
                    'salary_earnings' => round(array_sum(array_map(static fn (array $record): float => (float) ($record['total_due'] ?? 0), $records)), 2),
                    'expected_remittance' => round(array_sum(array_map(static fn (array $record): float => (float) ($record['expected_remittance'] ?? 0), $records)), 2),
                ],
                'riders' => (new RiderModel())->orderBy('name', 'ASC')->findAll(),
                'records' => $records,
            ]
        ));
    }

    public function deliveryShow(int $id)
    {
        $record = (new DeliveryRecordModel())
            ->select('delivery_records.*, riders.name, riders.rider_code, riders.contact_number, riders.commission_rate')
            ->join('riders', 'riders.id = delivery_records.rider_id')
            ->where('delivery_records.id', $id)
            ->first();

        if (! $record) {
            return redirect()->to('/admin/history')->with('error', 'Delivery record not found.');
        }

        $record['applied_commission_rate'] = (int) $record['successful_deliveries'] > 0
            ? round((float) $record['total_due'] / max(1, (int) $record['successful_deliveries']), 2)
            : $this->getCommissionRateForDate((int) $record['rider_id'], (string) $record['delivery_date']);

        $remittance = (new RemittanceModel())->where('delivery_record_id', $id)->first();
        $payroll = null;
        if (! empty($record['payroll_id'])) {
            $payroll = (new PayrollModel())->find((int) $record['payroll_id']);
        }

        return view('admin/delivery_show', array_merge(
            $this->adminBaseData('history'),
            [
                'record' => $record,
                'remittance' => $remittance,
                'payroll' => $payroll,
            ]
        ));
    }

    public function remittances()
    {
        $remittanceModel = new RemittanceModel();
        $submissionModel = new DeliverySubmissionModel();

        $pendingDeliveries = $this->getPendingRemittances();
        $pendingSubmissions = $submissionModel
            ->select('delivery_submissions.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = delivery_submissions.rider_id')
            ->where('delivery_submissions.status', 'PENDING')
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('delivery_submissions.id', 'DESC')
            ->findAll(30);

        $recentRemittances = $remittanceModel
            ->select('remittances.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = remittances.rider_id')
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('remittances.id', 'DESC')
            ->findAll(30);

        $recentRemittances = $this->decorateRemittanceStatuses($recentRemittances);

        return view('admin/remittances', array_merge(
            $this->adminBaseData('remittances'),
            [
                'pendingDeliveries' => $pendingDeliveries,
                'pendingSubmissions' => $pendingSubmissions,
                'recentRemittances' => $recentRemittances,
            ]
        ));
    }

    public function deliverySubmissionForm(int $submissionId)
    {
        $submission = (new DeliverySubmissionModel())
            ->select('delivery_submissions.*, riders.name, riders.rider_code, riders.commission_rate')
            ->join('riders', 'riders.id = delivery_submissions.rider_id')
            ->where('delivery_submissions.id', $submissionId)
            ->first();

        if (! $submission || ($submission['status'] ?? '') !== 'PENDING') {
            return redirect()->to('/admin/remittances')->with('error', 'Delivery submission not found or already processed.');
        }

        $isModal = $this->request->getGet('modal') === '1';

        return view('admin/delivery_submission_form', array_merge(
            $this->adminBaseData('remittances'),
            [
                'submission' => $submission,
                'layout' => $isModal ? 'layouts/modal' : 'layouts/main',
                'isModal' => $isModal,
            ]
        ));
    }

    public function payroll()
    {
        $selectedRiderId = trim((string) $this->request->getGet('rider_id'));
        $selectedPayrollMonth = trim((string) $this->request->getGet('payroll_month'));
        $selectedCutoff = trim((string) $this->request->getGet('cutoff_period'));

        $payrollModel = new PayrollModel();
        $payrollBuilder = $payrollModel
            ->select('payrolls.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = payrolls.rider_id')
            ->orderBy('end_date', 'DESC')
            ->orderBy('payrolls.id', 'DESC');

        if ($selectedRiderId !== '' && ctype_digit($selectedRiderId)) {
            $payrollBuilder->where('payrolls.rider_id', (int) $selectedRiderId);
        }

        if ($selectedPayrollMonth !== '' && in_array($selectedCutoff, ['FIRST', 'SECOND'], true)) {
            [$filterStart, $filterEnd] = $this->getCutoffWindow($selectedPayrollMonth, $selectedCutoff);
            $payrollBuilder
                ->where('payrolls.start_date', $filterStart)
                ->where('payrolls.end_date', $filterEnd);
        } elseif ($selectedPayrollMonth !== '') {
            $payrollBuilder
                ->where('payrolls.start_date >=', $selectedPayrollMonth . '-01')
                ->where('payrolls.start_date <=', date('Y-m-t', strtotime($selectedPayrollMonth . '-01')));
        }

        $payrolls = $payrollBuilder->findAll(50);

        $cutoffSummaries = $payrollModel
            ->select('start_date, end_date, COUNT(*) AS rider_count, SUM(gross_earnings) AS gross_total, SUM(bonus_total) AS bonus_total, SUM(deduction_total) AS deduction_total, SUM(shortage_deductions) AS shortage_total, SUM(shortage_payments_received) AS repayment_total, SUM(net_pay) AS net_total')
            ->groupBy('start_date, end_date')
            ->orderBy('end_date', 'DESC')
            ->orderBy('start_date', 'DESC')
            ->findAll(10);

        [$defaultStart, $defaultEnd, $defaultCutoff] = $this->getCutoffWindow(date('Y-m'), (int) date('j') <= 15 ? 'FIRST' : 'SECOND');

        return view('admin/payroll', array_merge(
            $this->adminBaseData('payroll'),
            [
                'payrolls' => $payrolls,
                'today' => date('Y-m-d'),
                'defaultPayrollMonth' => substr($defaultStart, 0, 7),
                'defaultCutoff' => $defaultCutoff,
                'cutoffSummaries' => $cutoffSummaries,
                'selectedRiderId' => $selectedRiderId,
                'selectedPayrollMonth' => $selectedPayrollMonth,
                'selectedCutoff' => $selectedCutoff,
            ]
        ));
    }

    public function settings()
    {
        $rateModel = new RiderCommissionRateModel();
        $riders = (new RiderModel())->orderBy('name', 'ASC')->findAll();

        foreach ($riders as &$rider) {
            $currentRate = $this->getCommissionRateForDate((int) $rider['id'], date('Y-m-d'));
            $rider['current_commission_rate'] = $currentRate;
            $rider['display_commission_rate'] = number_format($currentRate, 2);
        }
        unset($rider);

        $rateHistory = $rateModel
            ->select('rider_commission_rates.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = rider_commission_rates.rider_id')
            ->orderBy('effective_date', 'DESC')
            ->orderBy('rider_commission_rates.id', 'DESC')
            ->findAll(50);

        return view('admin/settings', array_merge(
            $this->adminBaseData('settings'),
            [
                'riders' => $riders,
                'today' => date('Y-m-d'),
                'rateHistory' => $rateHistory,
            ]
        ));
    }

    public function adjustments()
    {
        $adjustments = (new PayrollAdjustmentModel())
            ->select('payroll_adjustments.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = payroll_adjustments.rider_id')
            ->orderBy('adjustment_date', 'DESC')
            ->orderBy('payroll_adjustments.id', 'DESC')
            ->findAll(50);

        return view('admin/adjustments', array_merge(
            $this->adminBaseData('adjustments'),
            [
                'today' => date('Y-m-d'),
                'adjustments' => $adjustments,
            ]
        ));
    }

    public function analytics()
    {
        $startDate = date('Y-m-d', strtotime('-13 days'));
        $endDate = date('Y-m-d');
        $dailyRows = (new DeliveryRecordModel())
            ->select('delivery_date, SUM(allocated_parcels) AS allocated_total, SUM(successful_deliveries) AS successful_total, SUM(total_due) AS earning_total')
            ->where('delivery_date >=', $startDate)
            ->where('delivery_date <=', $endDate)
            ->groupBy('delivery_date')
            ->orderBy('delivery_date', 'ASC')
            ->findAll();

        $dailyMap = [];
        foreach ($dailyRows as $row) {
            $dailyMap[$row['delivery_date']] = $row;
        }

        $labels = [];
        $allocatedSeries = [];
        $successfulSeries = [];
        $earningsSeries = [];
        $current = strtotime($startDate);
        $last = strtotime($endDate);
        while ($current <= $last) {
            $date = date('Y-m-d', $current);
            $labels[] = date('M d', $current);
            $allocatedSeries[] = (int) ($dailyMap[$date]['allocated_total'] ?? 0);
            $successfulSeries[] = (int) ($dailyMap[$date]['successful_total'] ?? 0);
            $earningsSeries[] = round((float) ($dailyMap[$date]['earning_total'] ?? 0), 2);
            $current = strtotime('+1 day', $current);
        }

        $performance = $this->getRiderPerformanceRanking(date('Y-m-01'), date('Y-m-t'));

        return view('admin/analytics', array_merge(
            $this->adminBaseData('analytics'),
            [
                'chartLabels' => $labels,
                'allocatedSeries' => $allocatedSeries,
                'successfulSeries' => $successfulSeries,
                'earningsSeries' => $earningsSeries,
                'topRiders' => array_slice($performance, 0, 5),
                'lowRiders' => array_slice(array_reverse($performance), 0, 5),
            ]
        ));
    }

    public function shortages()
    {
        $shortages = $this->getShortageBalances();
        $paymentHistory = (new ShortagePaymentModel())
            ->select('shortage_payments.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = shortage_payments.rider_id')
            ->orderBy('payment_date', 'DESC')
            ->orderBy('shortage_payments.id', 'DESC')
            ->findAll(30);

        return view('admin/shortages', array_merge(
            $this->adminBaseData('shortages'),
            [
                'today' => date('Y-m-d'),
                'shortages' => $shortages,
                'paymentHistory' => $paymentHistory,
            ]
        ));
    }

    public function announcements()
    {
        $announcements = (new AnnouncementModel())
            ->orderBy('published_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll(50);

        return view('admin/announcements', array_merge(
            $this->adminBaseData('announcements'),
            [
                'today' => date('Y-m-d'),
                'announcements' => $announcements,
            ]
        ));
    }

    public function createRider()
    {
        $rules = [
            'rider_code' => 'required|min_length[3]|max_length[40]|is_unique[riders.rider_code]',
            'name' => 'required|min_length[3]|max_length[120]',
            'contact_number' => 'permit_empty|max_length[30]',
            'commission_rate' => 'required|decimal',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $riderCode = trim((string) $this->request->getPost('rider_code'));
        $riderName = trim((string) $this->request->getPost('name'));
        $contactNumber = trim((string) $this->request->getPost('contact_number'));
        $commissionRate = (float) $this->request->getPost('commission_rate');
        $defaultUsername = strtolower($riderCode);
        $defaultPassword = $defaultUsername . '123';
        $userModel = new UserModel();

        if (db_connect()->tableExists('users') && $userModel->where('username', $defaultUsername)->first()) {
            return redirect()->back()->withInput()->with('error', 'The rider code would create a username that already exists.');
        }

        $db = db_connect();
        $db->transStart();

        $riderId = (new RiderModel())->insert([
            'rider_code' => $riderCode,
            'name' => $riderName,
            'contact_number' => $contactNumber,
            'commission_rate' => $commissionRate,
            'is_active' => 1,
        ]);

        (new RiderCommissionRateModel())->insert([
            'rider_id' => (int) $riderId,
            'commission_rate' => $commissionRate,
            'effective_date' => date('Y-m-d'),
        ]);

        if ($db->tableExists('users')) {
            $userModel->insert([
                'username' => $defaultUsername,
                'password_hash' => password_hash($defaultPassword, PASSWORD_DEFAULT),
                'role' => 'rider',
                'rider_id' => (int) $riderId,
                'is_active' => 1,
            ]);
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Unable to create rider profile.');
        }

        return redirect()->to('/admin/riders')->with('success', 'Rider profile created. Login: ' . $defaultUsername . ' / ' . $defaultPassword);
    }

    public function updateRider(int $id)
    {
        $riderModel = new RiderModel();
        $rider = $riderModel->find($id);

        if (! $rider) {
            return redirect()->to('/admin/riders')->with('error', 'Rider not found.');
        }

        $rules = [
            'rider_code' => 'required|min_length[3]|max_length[40]|is_unique[riders.rider_code,id,' . $id . ']',
            'name' => 'required|min_length[3]|max_length[120]',
            'contact_number' => 'permit_empty|max_length[30]',
            'is_active' => 'required|in_list[0,1]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $riderCode = trim((string) $this->request->getPost('rider_code'));
        $name = trim((string) $this->request->getPost('name'));
        $contactNumber = trim((string) $this->request->getPost('contact_number'));
        $isActive = (int) $this->request->getPost('is_active') === 1 ? 1 : 0;

        $db = db_connect();
        $db->transStart();

        $riderModel->update($id, [
            'rider_code' => $riderCode,
            'name' => $name,
            'contact_number' => $contactNumber,
            'is_active' => $isActive,
        ]);

        if ($db->tableExists('users')) {
            $userModel = new UserModel();
            $existingUser = $userModel->where('rider_id', $id)->where('role', 'rider')->first();
            $username = strtolower($riderCode);
            $usernameOwner = $userModel->where('username', $username)->first();

            if ($usernameOwner && (int) $usernameOwner['rider_id'] !== $id) {
                $db->transRollback();

                return redirect()->back()->withInput()->with('error', 'The updated rider code would create a username that already exists.');
            }

            if ($existingUser) {
                $userModel->update((int) $existingUser['id'], [
                    'username' => $username,
                    'is_active' => $isActive,
                ]);
            } else {
                $userModel->insert([
                    'username' => $username,
                    'password_hash' => password_hash($username . '123', PASSWORD_DEFAULT),
                    'role' => 'rider',
                    'rider_id' => $id,
                    'is_active' => $isActive,
                ]);
            }
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Unable to update rider profile.');
        }

        return redirect()->to('/admin/riders')->with('success', 'Rider details updated.');
    }

    public function resetRiderPassword(int $id)
    {
        $rider = (new RiderModel())->find($id);
        if (! $rider) {
            return redirect()->to('/admin/riders')->with('error', 'Rider not found.');
        }

        $userModel = new UserModel();
        $user = $userModel->where('rider_id', $id)->where('role', 'rider')->first();
        $username = strtolower((string) $rider['rider_code']);
        $newPassword = $username . '123';

        if ($user) {
            $userModel->update((int) $user['id'], [
                'username' => $username,
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'is_active' => (int) ($rider['is_active'] ?? 1),
            ]);
        } else {
            $userModel->insert([
                'username' => $username,
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'role' => 'rider',
                'rider_id' => $id,
                'is_active' => (int) ($rider['is_active'] ?? 1),
            ]);
        }

        return redirect()->to('/admin/riders')->with('success', 'Rider password reset. Login: ' . $username . ' / ' . $newPassword);
    }

    public function storeAdjustment()
    {
        $rules = [
            'adjustment_scope' => 'required|in_list[SINGLE,ALL]',
            'adjustment_date' => 'required|valid_date[Y-m-d]',
            'type' => 'required|in_list[BONUS,DEDUCTION]',
            'amount' => 'required|decimal|greater_than[0]',
            'description' => 'required|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $scope = (string) $this->request->getPost('adjustment_scope');
        $adjustmentDate = (string) $this->request->getPost('adjustment_date');
        $type = (string) $this->request->getPost('type');
        $amount = round((float) $this->request->getPost('amount'), 2);
        $description = trim((string) $this->request->getPost('description'));
        $batchReference = $scope === 'ALL'
            ? 'BATCH-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6))
            : null;

        $riders = [];
        if ($scope === 'ALL') {
            $riders = (new RiderModel())->select('id')->findAll();
            if ($riders === []) {
                return redirect()->back()->withInput()->with('error', 'No riders are available for a batch adjustment.');
            }
        } else {
            $riderId = (int) $this->request->getPost('rider_id');
            if ($riderId <= 0 || ! (new RiderModel())->find($riderId)) {
                return redirect()->back()->withInput()->with('error', 'Rider not found.');
            }

            $riders = [['id' => $riderId]];
        }

        $adjustmentModel = new PayrollAdjustmentModel();
        $db = db_connect();
        $db->transStart();

        foreach ($riders as $rider) {
            $adjustmentModel->insert([
                'rider_id' => (int) $rider['id'],
                'adjustment_date' => $adjustmentDate,
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
                'batch_reference' => $batchReference,
            ]);
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Unable to save payroll adjustment.');
        }

        $count = count($riders);
        $message = $count === 1
            ? 'Payroll adjustment saved for 1 rider.'
            : 'Payroll adjustment saved for all riders (' . $count . ' records created). Batch: ' . $batchReference;

        return redirect()->to('/admin/adjustments')->with('success', $message);
    }

    public function storeAnnouncement()
    {
        $rules = [
            'title' => 'required|max_length[150]',
            'message' => 'required',
            'published_at' => 'required|valid_date[Y-m-d]',
            'expires_at' => 'permit_empty|valid_date[Y-m-d]',
            'is_active' => 'required|in_list[0,1]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        (new AnnouncementModel())->insert([
            'title' => trim((string) $this->request->getPost('title')),
            'message' => trim((string) $this->request->getPost('message')),
            'published_at' => (string) $this->request->getPost('published_at') . ' 00:00:00',
            'expires_at' => $this->request->getPost('expires_at') ? (string) $this->request->getPost('expires_at') . ' 23:59:59' : null,
            'is_active' => (int) $this->request->getPost('is_active'),
        ]);

        return redirect()->to('/admin/announcements')->with('success', 'Announcement published.');
    }

    public function updateAnnouncement(int $id)
    {
        $announcementModel = new AnnouncementModel();
        $announcement = $announcementModel->find($id);
        if (! $announcement) {
            return redirect()->to('/admin/announcements')->with('error', 'Announcement not found.');
        }

        $rules = [
            'title' => 'required|max_length[150]',
            'message' => 'required',
            'published_at' => 'required|valid_date[Y-m-d]',
            'expires_at' => 'permit_empty|valid_date[Y-m-d]',
            'is_active' => 'required|in_list[0,1]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $announcementModel->update($id, [
            'title' => trim((string) $this->request->getPost('title')),
            'message' => trim((string) $this->request->getPost('message')),
            'published_at' => (string) $this->request->getPost('published_at') . ' 00:00:00',
            'expires_at' => $this->request->getPost('expires_at') ? (string) $this->request->getPost('expires_at') . ' 23:59:59' : null,
            'is_active' => (int) $this->request->getPost('is_active'),
        ]);

        return redirect()->to('/admin/announcements')->with('success', 'Announcement updated.');
    }

    public function storeCommissionRate()
    {
        $rules = [
            'rider_id' => 'required|is_natural_no_zero',
            'commission_rate' => 'required|decimal|greater_than[0]',
            'effective_date' => 'required|valid_date[Y-m-d]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $riderId = (int) $this->request->getPost('rider_id');
        $commissionRate = round((float) $this->request->getPost('commission_rate'), 2);
        $effectiveDate = (string) $this->request->getPost('effective_date');

        $rider = (new RiderModel())->find($riderId);
        if (! $rider) {
            return redirect()->back()->withInput()->with('error', 'Rider not found.');
        }

        $rateModel = new RiderCommissionRateModel();
        $existing = $rateModel
            ->where('rider_id', $riderId)
            ->where('effective_date', $effectiveDate)
            ->first();

        if ($existing) {
            $rateModel->update((int) $existing['id'], [
                'commission_rate' => $commissionRate,
            ]);
        } else {
            $rateModel->insert([
                'rider_id' => $riderId,
                'commission_rate' => $commissionRate,
                'effective_date' => $effectiveDate,
            ]);
        }

        $this->syncCurrentRiderCommission($riderId);

        return redirect()->to('/admin/settings')->with('success', 'Commission rate updated for ' . $rider['name'] . ' effective ' . $effectiveDate . '.');
    }

    public function storeDelivery()
    {
        $rules = [
            'rider_id' => 'required|is_natural_no_zero',
            'delivery_date' => 'required|valid_date[Y-m-d]',
            'allocated_parcels' => 'required|is_natural',
            'successful_deliveries' => 'required|is_natural',
            'expected_remittance' => 'required|decimal',
            'commission_rate' => 'required|decimal|greater_than[0]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $rider = (new RiderModel())->find((int) $this->request->getPost('rider_id'));
        if (! $rider) {
            return redirect()->back()->withInput()->with('error', 'Rider not found.');
        }

        $allocated = (int) $this->request->getPost('allocated_parcels');
        $successful = (int) $this->request->getPost('successful_deliveries');
        $failed = max(0, $allocated - $successful);
        $expectedRemittance = round((float) $this->request->getPost('expected_remittance'), 2);
        $appliedCommissionRate = round((float) $this->request->getPost('commission_rate'), 2);

        if ($successful > $allocated) {
            return redirect()->back()->withInput()->with('error', 'Successful deliveries cannot exceed allocated parcels.');
        }

        if ($expectedRemittance < 0) {
            return redirect()->back()->withInput()->with('error', 'Expected remittance cannot be negative.');
        }

        $totalDue = round($successful * $appliedCommissionRate, 2);

        $payload = [
            'rider_id' => (int) $this->request->getPost('rider_id'),
            'delivery_date' => $this->request->getPost('delivery_date'),
            'allocated_parcels' => $allocated,
            'successful_deliveries' => $successful,
            'failed_deliveries' => $failed,
            'expected_remittance' => $expectedRemittance,
            'commission_rate' => $appliedCommissionRate,
            'total_due' => $totalDue,
            'notes' => (string) $this->request->getPost('notes'),
        ];

        $deliveryModel = new DeliveryRecordModel();
        $existing = $deliveryModel
            ->where('rider_id', (int) $this->request->getPost('rider_id'))
            ->where('delivery_date', (string) $this->request->getPost('delivery_date'))
            ->first();

        if ($existing) {
            $deliveryModel->update((int) $existing['id'], $payload);

            return redirect()->to('/admin/deliveries')->with('success', 'Existing rider-day record updated. Salary earning is PHP ' . number_format($totalDue, 2) . ' and expected remittance is PHP ' . number_format($expectedRemittance, 2) . '.');
        }

        $deliveryModel->insert($payload);

        return redirect()->to('/admin/deliveries')->with('success', 'Delivery record saved. Salary earning is PHP ' . number_format($totalDue, 2) . ' and expected remittance is PHP ' . number_format($expectedRemittance, 2) . '.');
    }

    public function approveDeliverySubmission(int $submissionId)
    {
        $submissionModel = new DeliverySubmissionModel();
        $submission = $submissionModel->find($submissionId);

        if (! $submission || ($submission['status'] ?? '') !== 'PENDING') {
            return redirect()->to('/admin/remittances')->with('error', 'Delivery submission not found or already processed.');
        }

        $rules = [
            'commission_rate' => 'required|decimal|greater_than[0]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $commissionRate = round((float) $this->request->getPost('commission_rate'), 2);
        $successful = (int) ($submission['successful_deliveries'] ?? 0);
        $totalDue = round($successful * $commissionRate, 2);

        $payload = [
            'rider_id' => (int) $submission['rider_id'],
            'delivery_date' => (string) $submission['delivery_date'],
            'allocated_parcels' => (int) ($submission['allocated_parcels'] ?? 0),
            'successful_deliveries' => $successful,
            'failed_deliveries' => (int) ($submission['failed_deliveries'] ?? 0),
            'expected_remittance' => round((float) ($submission['expected_remittance'] ?? 0), 2),
            'commission_rate' => $commissionRate,
            'total_due' => $totalDue,
            'notes' => (string) ($submission['notes'] ?? ''),
        ];

        $deliveryModel = new DeliveryRecordModel();
        $db = db_connect();
        $db->transStart();

        $existing = $deliveryModel
            ->where('rider_id', (int) $submission['rider_id'])
            ->where('delivery_date', (string) $submission['delivery_date'])
            ->first();

        if ($existing) {
            $deliveryId = (int) $existing['id'];
            $deliveryModel->update($deliveryId, $payload);
        } else {
            $deliveryId = (int) $deliveryModel->insert($payload);
        }

        $submissionModel->update($submissionId, [
            'status' => 'APPROVED',
            'processed_delivery_record_id' => $deliveryId,
        ]);

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Unable to approve the delivery submission.');
        }

        $target = '/admin/remittance/' . $deliveryId . ($this->request->getGet('modal') === '1' ? '?modal=1' : '');

        return redirect()->to($target)->with('success', 'Rider submission approved. Review the remittance using the final commission rate.');
    }

    public function rejectDeliverySubmission(int $submissionId)
    {
        $submissionModel = new DeliverySubmissionModel();
        $submission = $submissionModel->find($submissionId);
        $isModal = $this->request->getGet('modal') === '1';

        if (! $submission || ($submission['status'] ?? '') !== 'PENDING') {
            return redirect()->to('/admin/remittances')->with('error', 'Delivery submission not found or already processed.');
        }

        $rejectionNote = trim((string) $this->request->getPost('rejection_note'));
        $notes = trim((string) ($submission['notes'] ?? ''));
        if ($rejectionNote !== '') {
            $notes = trim($notes . PHP_EOL . 'Admin rejection note: ' . $rejectionNote);
        }

        $submissionModel->update($submissionId, [
            'status' => 'REJECTED',
            'notes' => $notes,
        ]);

        if ($isModal) {
            return $this->response
                ->setHeader('Content-Type', 'text/html; charset=UTF-8')
                ->setBody('<script>window.parent.postMessage({ type: "submission-updated" }, "*");</script>');
        }

        return redirect()->to('/admin/remittances')->with('success', 'Rider submission rejected.');
    }

    public function remittanceForm(int $deliveryRecordId)
    {
        $delivery = (new DeliveryRecordModel())
            ->select('delivery_records.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = delivery_records.rider_id')
            ->where('delivery_records.id', $deliveryRecordId)
            ->first();

        if (! $delivery) {
            return redirect()->to('/admin/remittances')->with('error', 'Delivery record not found.');
        }

        $remittance = (new RemittanceModel())->where('delivery_record_id', $deliveryRecordId)->first();

        $isModal = $this->request->getGet('modal') === '1';

        return view('admin/remittance_form', array_merge(
            $this->adminBaseData('remittances'),
            [
                'delivery' => $delivery,
                'remittance' => $remittance,
                'denominations' => $this->denominations,
                'layout' => $isModal ? 'layouts/modal' : 'layouts/main',
                'isModal' => $isModal,
            ]
        ));
    }

    public function saveRemittance(int $deliveryRecordId)
    {
        $delivery = (new DeliveryRecordModel())->find($deliveryRecordId);
        $isModal = $this->request->getGet('modal') === '1';
        if (! $delivery) {
            return redirect()->to('/admin/remittances')->with('error', 'Delivery record not found.');
        }

        $totals = $this->calculateRemittanceTotals();
        $actualInput = trim((string) $this->request->getPost('actual_remitted'));
        $supposedRemittance = isset($delivery['expected_remittance']) ? (float) $delivery['expected_remittance'] : null;
        $actualRemitted = $actualInput === '' ? null : (float) $actualInput;

        if (($supposedRemittance !== null && $supposedRemittance < 0) || ($actualRemitted !== null && $actualRemitted < 0)) {
            return redirect()->back()->withInput()->with('error', 'Remittance amounts cannot be negative.');
        }

        // When actual remitted is manually encoded, it becomes the accounting source.
        $totalRemitted = $actualRemitted ?? $totals['total_remitted'];

        $variance = 0.0;
        $type = 'PENDING';

        if ($supposedRemittance !== null) {
            $variance = round($totalRemitted - $supposedRemittance, 2);
            $type = 'BALANCED';

            if ($variance > 0.005) {
                $type = 'OVER';
            } elseif ($variance < -0.005) {
                $type = 'SHORT';
            }
        }

        $payload = array_merge($totals['denoms'], [
            'rider_id' => (int) $delivery['rider_id'],
            'delivery_record_id' => $deliveryRecordId,
            'delivery_date' => $delivery['delivery_date'],
            'total_due' => (float) $delivery['total_due'],
            'total_remitted' => $totalRemitted,
            'supposed_remittance' => $supposedRemittance,
            'actual_remitted' => $actualRemitted,
            'variance_amount' => abs($variance),
            'variance_type' => $type,
        ]);

        $model = new RemittanceModel();
        $existing = $model->where('delivery_record_id', $deliveryRecordId)->first();

        if ($existing) {
            $model->update((int) $existing['id'], $payload);
            $id = (int) $existing['id'];
        } else {
            $id = (int) $model->insert($payload);
        }

        $target = '/admin/remittance/' . $deliveryRecordId . ($isModal ? '?modal=1' : '');

        return redirect()->to($target)->with('success', 'Remittance saved. Status: ' . $type . '. Receipt is ready to download.')->with('remittance_id', $id);
    }

    public function recordShortagePayment(int $remittanceId)
    {
        $remittance = (new RemittanceModel())->find($remittanceId);
        if (! $remittance || $remittance['variance_type'] !== 'SHORT') {
            return redirect()->to('/admin/shortages')->with('error', 'Shortage record not found.');
        }

        $rules = [
            'payment_date' => 'required|valid_date[Y-m-d]',
            'amount' => 'required|decimal|greater_than[0]',
            'notes' => 'permit_empty|max_length[500]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $shortage = $this->findShortageBalance($remittanceId);
        if ($shortage === null) {
            return redirect()->to('/admin/shortages')->with('error', 'Unable to load shortage balance.');
        }

        $amount = round((float) $this->request->getPost('amount'), 2);
        if ($amount > $shortage['outstanding_balance']) {
            return redirect()->back()->withInput()->with('error', 'Payment exceeds the remaining shortage balance.');
        }

        (new ShortagePaymentModel())->insert([
            'remittance_id' => $remittanceId,
            'rider_id' => (int) $remittance['rider_id'],
            'payment_date' => (string) $this->request->getPost('payment_date'),
            'amount' => $amount,
            'notes' => trim((string) $this->request->getPost('notes')),
        ]);

        return redirect()->to('/admin/shortages')->with('success', 'Shortage payment recorded.');
    }

    public function remittancePdf(int $id)
    {
        $record = (new RemittanceModel())
            ->select('remittances.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = remittances.rider_id')
            ->where('remittances.id', $id)
            ->first();

        if (! $record) {
            return redirect()->to('/admin/remittances')->with('error', 'Remittance not found.');
        }

        $html = view('pdf/remittance_receipt', ['record' => $record, 'denominations' => $this->denominations]);

        return $this->renderPdf($html, 'remittance-receipt-' . $record['rider_code'] . '-' . $record['delivery_date'] . '.pdf');
    }

    public function generatePayroll()
    {
        $rules = [
            'rider_id' => 'required|is_natural_no_zero',
            'payroll_month' => 'required|regex_match[/^\d{4}\-\d{2}$/]',
            'cutoff_period' => 'required|in_list[FIRST,SECOND]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $riderId = (int) $this->request->getPost('rider_id');
        $payrollMonth = (string) $this->request->getPost('payroll_month');
        $cutoffPeriod = (string) $this->request->getPost('cutoff_period');
        [$startDate, $endDate] = $this->getCutoffWindow($payrollMonth, $cutoffPeriod);

        $rider = (new RiderModel())->find($riderId);
        if (! $rider) {
            return redirect()->back()->with('error', 'Rider not found.');
        }

        $payrollModel = new PayrollModel();
        $duplicateRange = $payrollModel
            ->where('rider_id', $riderId)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->first();

        if ($duplicateRange) {
            return redirect()->back()->withInput()->with('error', 'A payroll for this rider and date range already exists.');
        }

        $deliveryModel = new DeliveryRecordModel();
        $deliveries = $deliveryModel
            ->where('rider_id', $riderId)
            ->where('delivery_date >=', $startDate)
            ->where('delivery_date <=', $endDate)
            ->where('payroll_id', null)
            ->findAll();

        $shortagePaymentModel = new ShortagePaymentModel();
        $shortagePayments = $shortagePaymentModel
            ->where('rider_id', $riderId)
            ->where('payment_date >=', $startDate)
            ->where('payment_date <=', $endDate)
            ->where('payroll_id', null)
            ->findAll();

        $adjustmentModel = new PayrollAdjustmentModel();
        $adjustments = $adjustmentModel
            ->where('rider_id', $riderId)
            ->where('adjustment_date >=', $startDate)
            ->where('adjustment_date <=', $endDate)
            ->where('payroll_id', null)
            ->findAll();

        if ($deliveries === [] && $shortagePayments === [] && $adjustments === []) {
            return redirect()->back()->withInput()->with('error', 'No unpaid delivery days, shortage repayments, or payroll adjustments were found in that date range.');
        }

        $deliveryIds = array_map(static fn (array $delivery): int => (int) $delivery['id'], $deliveries);
        $remittances = $deliveryIds === []
            ? []
            : (new RemittanceModel())->whereIn('delivery_record_id', $deliveryIds)->findAll();

        $totalSuccessful = array_sum(array_column($deliveries, 'successful_deliveries'));
        $grossEarnings = round(array_sum(array_column($deliveries, 'total_due')), 2);
        $totalDue = round(array_sum(array_column($deliveries, 'total_due')), 2);
        $totalRemitted = round(array_sum(array_map(static fn (array $remittance): float => (float) ($remittance['total_remitted'] ?? 0), $remittances)), 2);
        $totalExpectedRemittance = round(array_sum(array_map(static fn (array $delivery): float => (float) ($delivery['expected_remittance'] ?? 0), $deliveries)), 2);
        $varianceNet = round($totalRemitted - $totalExpectedRemittance, 2);
        $shortageDeductions = round(array_sum(array_map(
            static fn (array $remittance): float => ($remittance['variance_type'] ?? '') === 'SHORT' ? (float) ($remittance['variance_amount'] ?? 0) : 0.0,
            $remittances
        )), 2);
        $shortagePaymentsReceived = round(array_sum(array_column($shortagePayments, 'amount')), 2);
        $bonusTotal = round(array_sum(array_map(
            static fn (array $adjustment): float => ($adjustment['type'] ?? '') === 'BONUS' ? (float) ($adjustment['amount'] ?? 0) : 0.0,
            $adjustments
        )), 2);
        $deductionTotal = round(array_sum(array_map(
            static fn (array $adjustment): float => ($adjustment['type'] ?? '') === 'DEDUCTION' ? (float) ($adjustment['amount'] ?? 0) : 0.0,
            $adjustments
        )), 2);
        $outstandingBalance = $this->calculateOutstandingShortageBalance($riderId, $endDate);
        $netPay = round(
            $grossEarnings
            - $shortageDeductions
            + $shortagePaymentsReceived,
            2
        );
        $netPay = round($netPay + $bonusTotal - $deductionTotal, 2);

        $data = [
            'rider_id' => $riderId,
            'month_year' => $startDate,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_successful' => $totalSuccessful,
            'gross_earnings' => $grossEarnings,
            'total_due' => $totalDue,
            'total_remitted' => $totalRemitted,
            'remittance_variance' => $varianceNet,
            'shortage_deductions' => $shortageDeductions,
            'shortage_payments_received' => $shortagePaymentsReceived,
            'bonus_total' => $bonusTotal,
            'deduction_total' => $deductionTotal,
            'outstanding_shortage_balance' => $outstandingBalance,
            'net_pay' => $netPay,
        ];

        $db = db_connect();
        $db->transStart();
        $payrollId = (int) $payrollModel->insert($data);

        if ($deliveryIds !== []) {
            $deliveryModel->whereIn('id', $deliveryIds)->set(['payroll_id' => $payrollId])->update();
        }

        $paymentIds = array_map(static fn (array $payment): int => (int) $payment['id'], $shortagePayments);
        if ($paymentIds !== []) {
            $shortagePaymentModel->whereIn('id', $paymentIds)->set(['payroll_id' => $payrollId])->update();
        }

        $adjustmentIds = array_map(static fn (array $adjustment): int => (int) $adjustment['id'], $adjustments);
        if ($adjustmentIds !== []) {
            $adjustmentModel->whereIn('id', $adjustmentIds)->set(['payroll_id' => $payrollId])->update();
        }
        $db->transComplete();

        return redirect()->to('/admin/payroll')->with('success', 'Payroll generated for ' . $rider['name'] . ' covering ' . $startDate . ' to ' . $endDate . '.');
    }

    public function payrollPdf(int $id)
    {
        $payroll = (new PayrollModel())
            ->select('payrolls.*, riders.name, riders.rider_code, riders.commission_rate')
            ->join('riders', 'riders.id = payrolls.rider_id')
            ->where('payrolls.id', $id)
            ->first();

        if (! $payroll) {
            return redirect()->to('/admin/payroll')->with('error', 'Payroll not found.');
        }

        $adjustments = (new PayrollAdjustmentModel())
            ->where('payroll_id', $id)
            ->orderBy('type', 'ASC')
            ->orderBy('description', 'ASC')
            ->findAll();

        $html = view('pdf/payroll_payslip', [
            'payroll' => $payroll,
            'adjustments' => $adjustments,
        ]);

        return $this->renderPdf($html, 'payroll-payslip-' . $payroll['rider_code'] . '-' . $payroll['month_year'] . '.pdf');
    }

    public function payrollSummaryPdf()
    {
        $startDate = trim((string) $this->request->getGet('start_date'));
        $endDate = trim((string) $this->request->getGet('end_date'));

        if ($startDate === '' || $endDate === '') {
            return redirect()->to('/admin/payroll')->with('error', 'Payroll summary range is required.');
        }

        $rows = (new PayrollModel())
            ->select('payrolls.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = payrolls.rider_id')
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->orderBy('riders.name', 'ASC')
            ->findAll();

        if ($rows === []) {
            return redirect()->to('/admin/payroll')->with('error', 'No payroll records found for that cutoff.');
        }

        $summary = [
            'rider_count' => count($rows),
            'gross_total' => round(array_sum(array_map(static fn (array $row): float => (float) ($row['gross_earnings'] ?? 0), $rows)), 2),
            'bonus_total' => round(array_sum(array_map(static fn (array $row): float => (float) ($row['bonus_total'] ?? 0), $rows)), 2),
            'deduction_total' => round(array_sum(array_map(static fn (array $row): float => (float) ($row['deduction_total'] ?? 0), $rows)), 2),
            'shortage_total' => round(array_sum(array_map(static fn (array $row): float => (float) ($row['shortage_deductions'] ?? 0), $rows)), 2),
            'repayment_total' => round(array_sum(array_map(static fn (array $row): float => (float) ($row['shortage_payments_received'] ?? 0), $rows)), 2),
            'net_total' => round(array_sum(array_map(static fn (array $row): float => (float) ($row['net_pay'] ?? 0), $rows)), 2),
        ];

        $html = view('pdf/payroll_summary', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'rows' => $rows,
            'summary' => $summary,
        ]);

        return $this->renderPdf($html, 'payroll-summary-' . $startDate . '-to-' . $endDate . '.pdf');
    }

    public function deliveryHistoryCsv()
    {
        $search = trim((string) $this->request->getGet('q'));
        $startDate = trim((string) $this->request->getGet('start_date'));
        $endDate = trim((string) $this->request->getGet('end_date'));
        $riderId = trim((string) $this->request->getGet('rider_id'));
        $remittanceStatus = trim((string) $this->request->getGet('remittance_status'));

        $records = $this->getFilteredHistoryRecords($search, $startDate, $endDate, $riderId, $remittanceStatus, 1000);

        $lines = [
            ['Date', 'Rider Code', 'Rider Name', 'Allocated', 'Successful', 'Failed', 'Salary Earning', 'Expected Remittance', 'Remittance Status'],
        ];

        foreach ($records as $record) {
            $lines[] = [
                (string) $record['delivery_date'],
                (string) $record['rider_code'],
                (string) $record['name'],
                (int) $record['allocated_parcels'],
                (int) $record['successful_deliveries'],
                (int) $record['failed_deliveries'],
                number_format((float) ($record['total_due'] ?? 0), 2, '.', ''),
                number_format((float) ($record['expected_remittance'] ?? 0), 2, '.', ''),
                (string) ($record['variance_type'] ?? 'NOT RECORDED'),
            ];
        }

        $handle = fopen('php://temp', 'r+');
        foreach ($lines as $line) {
            fputcsv($handle, $line);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $this->response
            ->setHeader('Content-Type', 'text/csv')
            ->setHeader('Content-Disposition', 'attachment; filename="delivery-history-export.csv"')
            ->setBody($csv);
    }

    public function reopenPayroll(int $id)
    {
        $payrollModel = new PayrollModel();
        $payroll = $payrollModel->find($id);

        if (! $payroll) {
            return redirect()->to('/admin/payroll')->with('error', 'Payroll not found.');
        }

        $db = db_connect();
        $db->transStart();

        (new DeliveryRecordModel())
            ->where('payroll_id', $id)
            ->set(['payroll_id' => null])
            ->update();

        (new ShortagePaymentModel())
            ->where('payroll_id', $id)
            ->set(['payroll_id' => null])
            ->update();

        (new PayrollAdjustmentModel())
            ->where('payroll_id', $id)
            ->set(['payroll_id' => null])
            ->update();

        $payrollModel->delete($id);

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to('/admin/payroll')->with('error', 'Unable to reopen payroll.');
        }

        return redirect()->to('/admin/payroll')->with('success', 'Payroll reopened. Covered delivery days and shortage payments are available again.');
    }

    private function adminBaseData(string $activeTab): array
    {
        $pendingSubmissionCount = (new DeliverySubmissionModel())
            ->where('status', 'PENDING')
            ->countAllResults();

        return [
            'activeTab' => $activeTab,
            'title' => 'Admin - J&T Rider Remittance & Payroll',
            'riders' => (new RiderModel())->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'pendingSubmissionCount' => $pendingSubmissionCount,
        ];
    }

    private function calculateRemittanceTotals(): array
    {
        $denomCounts = [];
        $total = 0;

        foreach ($this->denominations as $field => $value) {
            $count = max(0, (int) $this->request->getPost($field));
            $denomCounts[$field] = $count;
            $total += $count * $value;
        }

        return [
            'denoms' => $denomCounts,
            'total_remitted' => round($total, 2),
        ];
    }

    private function renderPdf(string $html, string $filename)
    {
        $dompdf = new Dompdf([
            'isRemoteEnabled' => true,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->setBody($dompdf->output());
    }

    private function getCommissionRateForDate(int $riderId, string $effectiveDate): float
    {
        $rate = (new RiderCommissionRateModel())
            ->where('rider_id', $riderId)
            ->where('effective_date <=', $effectiveDate)
            ->orderBy('effective_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        if ($rate) {
            return round((float) $rate['commission_rate'], 2);
        }

        $rider = (new RiderModel())->find($riderId);

        return round((float) ($rider['commission_rate'] ?? 0), 2);
    }

    private function syncCurrentRiderCommission(int $riderId): void
    {
        $currentRate = $this->getCommissionRateForDate($riderId, date('Y-m-d'));
        (new RiderModel())->update($riderId, ['commission_rate' => $currentRate]);
    }

    private function getCutoffWindow(string $payrollMonth, string $cutoffPeriod): array
    {
        $monthStart = $payrollMonth . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        if ($cutoffPeriod === 'FIRST') {
            return [$monthStart, $payrollMonth . '-15', 'FIRST'];
        }

        return [$payrollMonth . '-16', $monthEnd, 'SECOND'];
    }

    private function getFilteredHistoryRecords(
        string $search,
        string $startDate,
        string $endDate,
        string $riderId,
        string $remittanceStatus,
        int $limit = 100
    ): array {
        $builder = (new DeliveryRecordModel())
            ->select('delivery_records.*, riders.name, riders.rider_code, remittances.id AS remittance_id, remittances.variance_type, remittances.supposed_remittance, remittances.actual_remitted')
            ->join('riders', 'riders.id = delivery_records.rider_id')
            ->join('remittances', 'remittances.delivery_record_id = delivery_records.id', 'left');

        if ($search !== '') {
            $builder->groupStart()
                ->like('riders.name', $search)
                ->orLike('riders.rider_code', $search)
                ->groupEnd();
        }

        if ($startDate !== '') {
            $builder->where('delivery_records.delivery_date >=', $startDate);
        }

        if ($endDate !== '') {
            $builder->where('delivery_records.delivery_date <=', $endDate);
        }

        if ($riderId !== '') {
            $builder->where('delivery_records.rider_id', (int) $riderId);
        }

        if ($remittanceStatus === 'NOT_RECORDED') {
            $builder->where('remittances.id', null);
        } elseif (in_array($remittanceStatus, ['PENDING', 'BALANCED', 'SHORT', 'OVER'], true)) {
            $builder->where('remittances.variance_type', $remittanceStatus);
        }

        return $builder
            ->orderBy('delivery_records.delivery_date', 'DESC')
            ->orderBy('delivery_records.id', 'DESC')
            ->findAll($limit);
    }

    private function getShortageBalances(): array
    {
        $shortages = (new RemittanceModel())
            ->select('remittances.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = remittances.rider_id')
            ->where('remittances.variance_type', 'SHORT')
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('remittances.id', 'DESC')
            ->findAll();

        if ($shortages === []) {
            return [];
        }

        $paymentRows = (new ShortagePaymentModel())
            ->select('remittance_id, SUM(amount) AS paid_total')
            ->whereIn('remittance_id', array_column($shortages, 'id'))
            ->groupBy('remittance_id')
            ->findAll();

        $paidMap = [];
        foreach ($paymentRows as $row) {
            $paidMap[(int) $row['remittance_id']] = round((float) $row['paid_total'], 2);
        }

        foreach ($shortages as &$shortage) {
            $paidAmount = $paidMap[(int) $shortage['id']] ?? 0.0;
            $outstanding = max(0, round((float) $shortage['variance_amount'] - $paidAmount, 2));
            $shortage['paid_amount'] = $paidAmount;
            $shortage['outstanding_balance'] = $outstanding;
            $shortage['shortage_status'] = $outstanding > 0 ? 'OPEN' : 'SETTLED';
        }
        unset($shortage);

        return $shortages;
    }

    private function findShortageBalance(int $remittanceId): ?array
    {
        foreach ($this->getShortageBalances() as $shortage) {
            if ((int) $shortage['id'] === $remittanceId) {
                return $shortage;
            }
        }

        return null;
    }

    private function getPendingRemittances(): array
    {
        $pendingDeliveries = (new DeliveryRecordModel())
            ->select('delivery_records.*, riders.name, riders.rider_code, remittances.id AS remittance_id, remittances.variance_type')
            ->join('riders', 'riders.id = delivery_records.rider_id')
            ->join('remittances', 'remittances.delivery_record_id = delivery_records.id', 'left')
            ->groupStart()
            ->where('remittances.id', null)
            ->orWhere('remittances.variance_type', 'PENDING')
            ->groupEnd()
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('delivery_records.id', 'DESC')
            ->findAll();

        return $this->decoratePendingRemittances($pendingDeliveries);
    }

    private function decoratePendingRemittances(array $records): array
    {
        $today = strtotime(date('Y-m-d'));

        foreach ($records as &$record) {
            $deliveryTs = strtotime((string) $record['delivery_date']);
            $agingDays = max(0, (int) floor(($today - $deliveryTs) / 86400));
            $record['aging_days'] = $agingDays;
            $record['pending_status'] = ($record['variance_type'] ?? null) === 'PENDING'
                ? 'AWAITING EXPECTED TOTAL'
                : ($agingDays > 0 ? 'OVERDUE' : 'DUE TODAY');
        }
        unset($record);

        return $records;
    }

    private function decorateRemittanceStatuses(array $records): array
    {
        $shortageMap = [];
        foreach ($this->getShortageBalances() as $shortage) {
            $shortageMap[(int) $shortage['id']] = $shortage;
        }

        foreach ($records as &$record) {
            $record['settlement_status'] = (string) $record['variance_type'];
            if ($record['variance_type'] === 'PENDING') {
                $record['settlement_status'] = 'AWAITING EXPECTED TOTAL';
            }
            if ($record['variance_type'] === 'SHORT' && isset($shortageMap[(int) $record['id']])) {
                $record['settlement_status'] = $shortageMap[(int) $record['id']]['outstanding_balance'] > 0 ? 'SHORT OPEN' : 'SHORT SETTLED';
            }
        }
        unset($record);

        return $records;
    }

    private function calculateOutstandingShortageBalance(int $riderId, string $asOfDate): float
    {
        $shortageRemittances = (new RemittanceModel())
            ->where('rider_id', $riderId)
            ->where('variance_type', 'SHORT')
            ->findAll();

        $outstandingShortageBalance = 0.0;

        foreach ($shortageRemittances as $remittance) {
            $shortageAmount = round((float) ($remittance['variance_amount'] ?? 0), 2);
            $paidUntilDate = (float) ((new ShortagePaymentModel())
                ->selectSum('amount')
                ->where('remittance_id', (int) $remittance['id'])
                ->where('payment_date <=', $asOfDate)
                ->first()['amount'] ?? 0);

            $outstandingShortageBalance += max(0, round($shortageAmount - $paidUntilDate, 2));
        }

        return round($outstandingShortageBalance, 2);
    }

    private function getRiderPerformanceRanking(string $startDate, string $endDate): array
    {
        $rows = (new DeliveryRecordModel())
            ->select('delivery_records.rider_id, riders.name, riders.rider_code, SUM(delivery_records.allocated_parcels) AS allocated_total, SUM(delivery_records.successful_deliveries) AS successful_total, SUM(delivery_records.total_due) AS earning_total')
            ->join('riders', 'riders.id = delivery_records.rider_id')
            ->where('delivery_records.delivery_date >=', $startDate)
            ->where('delivery_records.delivery_date <=', $endDate)
            ->groupBy('delivery_records.rider_id, riders.name, riders.rider_code')
            ->findAll();

        foreach ($rows as &$row) {
            $allocated = max(1, (int) ($row['allocated_total'] ?? 0));
            $successful = (int) ($row['successful_total'] ?? 0);
            $row['success_rate'] = round(($successful / $allocated) * 100, 2);
            $row['earning_total'] = round((float) ($row['earning_total'] ?? 0), 2);
        }
        unset($row);

        usort($rows, static function (array $a, array $b): int {
            return [$b['success_rate'], $b['successful_total'], $b['earning_total']]
                <=> [$a['success_rate'], $a['successful_total'], $a['earning_total']];
        });

        return $rows;
    }

    private function getActiveAnnouncements(): array
    {
        $today = date('Y-m-d H:i:s');
        $rows = (new AnnouncementModel())
            ->orderBy('published_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();

        return array_values(array_filter($rows, static function (array $announcement) use ($today): bool {
            if (! (bool) ($announcement['is_active'] ?? false)) {
                return false;
            }

            if (! empty($announcement['published_at']) && (string) $announcement['published_at'] > $today) {
                return false;
            }

            if (! empty($announcement['expires_at']) && (string) $announcement['expires_at'] < $today) {
                return false;
            }

            return true;
        }));
    }
}
