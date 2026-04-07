<?php

namespace App\Controllers;

use App\Models\AnnouncementModel;
use App\Models\DeliveryAuditLogModel;
use App\Models\DeliveryCorrectionRequestModel;
use App\Models\DeliveryRecordModel;
use App\Models\DeliverySubmissionModel;
use App\Models\PayrollModel;
use App\Models\PayrollAdjustmentModel;
use App\Models\RemittanceModel;
use App\Models\RemittanceAccountModel;
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
        $correctionRequestModel = new DeliveryCorrectionRequestModel();

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
            'pending_correction_requests' => $correctionRequestModel->where('status', 'PENDING')->countAllResults(),
        ];

        $recentDeliveries = $deliveryModel
            ->select('delivery_records.*, riders.name, riders.rider_code, remittance_accounts.account_name AS remittance_account_name, remittance_accounts.account_number AS remittance_account_number')
            ->join('riders', 'riders.id = delivery_records.rider_id')
            ->join('remittance_accounts', 'remittance_accounts.id = delivery_records.remittance_account_id', 'left')
            ->orderBy('delivery_records.updated_at', 'DESC')
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('delivery_records.id', 'DESC')
            ->findAll(8);

        $recentRemittances = $remittanceModel
            ->select('remittances.*, riders.name, riders.rider_code, remittance_accounts.account_name AS remittance_account_name, remittance_accounts.account_number AS remittance_account_number')
            ->join('riders', 'riders.id = remittances.rider_id')
            ->join('remittance_accounts', 'remittance_accounts.id = remittances.remittance_account_id', 'left')
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('remittances.id', 'DESC')
            ->findAll(8);

        $performance = $this->getRiderPerformanceRanking($monthStart, $monthEnd);
        $announcements = array_slice($this->getActiveAnnouncements(), 0, 5);
        $pendingSubmissions = $submissionModel
            ->select('delivery_submissions.*, riders.name, riders.rider_code, remittance_accounts.account_name AS remittance_account_name, remittance_accounts.account_number AS remittance_account_number')
            ->join('riders', 'riders.id = delivery_submissions.rider_id')
            ->join('remittance_accounts', 'remittance_accounts.id = delivery_submissions.remittance_account_id', 'left')
            ->where('delivery_submissions.status', 'PENDING')
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('delivery_submissions.id', 'DESC')
            ->findAll(5);
        $pendingCorrections = $correctionRequestModel
            ->select('delivery_correction_requests.*, delivery_records.delivery_date, riders.name, riders.rider_code')
            ->join('delivery_records', 'delivery_records.id = delivery_correction_requests.delivery_record_id')
            ->join('riders', 'riders.id = delivery_records.rider_id')
            ->where('delivery_correction_requests.status', 'PENDING')
            ->orderBy('delivery_correction_requests.created_at', 'DESC')
            ->orderBy('delivery_correction_requests.id', 'DESC')
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
                'pendingCorrections' => $pendingCorrections,
            ]
        ));
    }

    public function riders()
    {
        $search = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));
        $riderModel = $this->buildRiderQuery($search, $status);
        $riders = $riderModel->paginate(15, 'riders');

        return view('admin/riders', array_merge(
            $this->adminBaseData('riders'),
            [
                'search' => $search,
                'status' => $status,
                'riders' => $riders,
                'pager' => $riderModel->pager,
                'pageGroup' => 'riders',
            ]
        ));
    }

    public function deliveries()
    {
        $search = trim((string) $this->request->getGet('q'));
        $source = trim((string) $this->request->getGet('source'));
        $deliveryDate = trim((string) $this->request->getGet('delivery_date'));
        $deliveryModel = $this->buildDeliveryQuery($search, $source, $deliveryDate);
        $dailyRecords = $deliveryModel->paginate(20, 'deliveries');

        return view('admin/deliveries', array_merge(
            $this->adminBaseData('deliveries'),
            [
                'today' => date('Y-m-d'),
                'search' => $search,
                'source' => $source,
                'deliveryDate' => $deliveryDate,
                'dailyRecords' => $dailyRecords,
                'pager' => $deliveryModel->pager,
                'pageGroup' => 'deliveries',
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

        $historyModel = $this->buildHistoryQuery($search, $startDate, $endDate, $riderId, $remittanceStatus);
        $records = $historyModel->paginate(20, 'history');
        $summaryRows = $this->buildHistoryQuery($search, $startDate, $endDate, $riderId, $remittanceStatus)->findAll();

        return view('admin/delivery_history', array_merge(
            $this->adminBaseData('history'),
            [
                'search' => $search,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'selectedRiderId' => $riderId,
                'remittanceStatus' => $remittanceStatus,
                'historySummary' => [
                    'records' => count($summaryRows),
                    'successful' => array_sum(array_column($summaryRows, 'successful_deliveries')),
                    'salary_earnings' => round(array_sum(array_map(static fn (array $record): float => (float) ($record['total_due'] ?? 0), $summaryRows)), 2),
                    'expected_remittance' => round(array_sum(array_map(static fn (array $record): float => (float) ($record['expected_remittance'] ?? 0), $summaryRows)), 2),
                ],
                'riders' => (new RiderModel())->orderBy('name', 'ASC')->findAll(),
                'records' => $records,
                'pager' => $historyModel->pager,
                'pageGroup' => 'history',
            ]
        ));
    }

    public function deliveryShow(int $id)
    {
        $record = (new DeliveryRecordModel())
            ->select('delivery_records.*, riders.name, riders.rider_code, riders.contact_number, riders.commission_rate, delivery_submissions.status AS submission_status, delivery_submissions.notes AS submission_notes, delivery_accounts.account_name AS remittance_account_name, delivery_accounts.account_number AS remittance_account_number, submission_accounts.account_name AS submission_remittance_account_name, submission_accounts.account_number AS submission_remittance_account_number')
            ->join('riders', 'riders.id = delivery_records.rider_id')
            ->join('delivery_submissions', 'delivery_submissions.id = delivery_records.source_submission_id', 'left')
            ->join('remittance_accounts AS delivery_accounts', 'delivery_accounts.id = delivery_records.remittance_account_id', 'left')
            ->join('remittance_accounts AS submission_accounts', 'submission_accounts.id = delivery_submissions.remittance_account_id', 'left')
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
        $auditLogs = (new DeliveryAuditLogModel())
            ->groupStart()
                ->where('delivery_record_id', $id)
                ->orWhere('delivery_submission_id', (int) ($record['source_submission_id'] ?? 0))
            ->groupEnd()
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();
        $correctionRequests = (new DeliveryCorrectionRequestModel())
            ->where('delivery_record_id', $id)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();

        return view('admin/delivery_show', array_merge(
            $this->adminBaseData('history'),
            [
                'record' => $record,
                'remittance' => $remittance,
                'payroll' => $payroll,
                'auditLogs' => $auditLogs,
                'correctionRequests' => $correctionRequests,
            ]
        ));
    }

    public function corrections()
    {
        $search = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));
        $startDate = trim((string) $this->request->getGet('start_date'));
        $endDate = trim((string) $this->request->getGet('end_date'));

        $correctionModel = $this->buildCorrectionQuery($search, $status, $startDate, $endDate);
        $rows = $correctionModel->paginate(20, 'corrections');
        $summaryRows = $this->buildCorrectionQuery($search, $status, $startDate, $endDate)->findAll();

        $summary = [
            'pending' => count(array_filter($summaryRows, static fn (array $row): bool => ($row['status'] ?? '') === 'PENDING')),
            'applied' => count(array_filter($summaryRows, static fn (array $row): bool => ($row['status'] ?? '') === 'APPLIED')),
            'rejected' => count(array_filter($summaryRows, static fn (array $row): bool => ($row['status'] ?? '') === 'REJECTED')),
        ];

        return view('admin/corrections', array_merge(
            $this->adminBaseData('corrections'),
            [
                'search' => $search,
                'status' => $status,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'rows' => $rows,
                'summary' => $summary,
                'pager' => $correctionModel->pager,
                'pageGroup' => 'corrections',
            ]
        ));
    }

    public function activity()
    {
        $search = trim((string) $this->request->getGet('q'));
        $role = trim((string) $this->request->getGet('role'));
        $startDate = trim((string) $this->request->getGet('start_date'));
        $endDate = trim((string) $this->request->getGet('end_date'));

        $activityModel = $this->buildActivityQuery($search, $role, $startDate, $endDate);
        $rows = $activityModel->paginate(20, 'activity');

        return view('admin/activity', array_merge(
            $this->adminBaseData('activity'),
            [
                'search' => $search,
                'role' => $role,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'rows' => $rows,
                'pager' => $activityModel->pager,
                'pageGroup' => 'activity',
            ]
        ));
    }

    public function remittances()
    {
        $remittanceModel = new RemittanceModel();
        $submissionModel = new DeliverySubmissionModel();

        $pendingDeliveries = $this->paginateArray($this->getPendingRemittances(), 15, 'pending_remittances');
        $pendingSubmissionsModel = $submissionModel
            ->select('delivery_submissions.*, riders.name, riders.rider_code, remittance_accounts.account_name AS remittance_account_name, remittance_accounts.account_number AS remittance_account_number')
            ->join('riders', 'riders.id = delivery_submissions.rider_id')
            ->join('remittance_accounts', 'remittance_accounts.id = delivery_submissions.remittance_account_id', 'left')
            ->where('delivery_submissions.status', 'PENDING')
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('delivery_submissions.id', 'DESC');
        $pendingSubmissions = $pendingSubmissionsModel->paginate(12, 'pending_submissions');

        $recentRemittancesModel = $remittanceModel
            ->select('remittances.*, riders.name, riders.rider_code, remittance_accounts.account_name AS remittance_account_name, remittance_accounts.account_number AS remittance_account_number')
            ->join('riders', 'riders.id = remittances.rider_id')
            ->join('remittance_accounts', 'remittance_accounts.id = remittances.remittance_account_id', 'left')
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('remittances.id', 'DESC');
        $recentRemittances = $recentRemittancesModel->paginate(12, 'recent_remittances');

        $recentRemittances = $this->decorateRemittanceStatuses($recentRemittances);

        return view('admin/remittances', array_merge(
            $this->adminBaseData('remittances'),
            [
                'pendingDeliveries' => $pendingDeliveries,
                'pendingSubmissions' => $pendingSubmissions,
                'recentRemittances' => $recentRemittances,
                'pendingSubmissionsPager' => $pendingSubmissionsModel->pager,
                'recentRemittancesPager' => $recentRemittancesModel->pager,
            ]
        ));
    }

    public function deliverySubmissionForm(int $submissionId)
    {
        $submission = (new DeliverySubmissionModel())
            ->select('delivery_submissions.*, riders.name, riders.rider_code, riders.commission_rate, remittance_accounts.account_name AS remittance_account_name, remittance_accounts.account_number AS remittance_account_number')
            ->join('riders', 'riders.id = delivery_submissions.rider_id')
            ->join('remittance_accounts', 'remittance_accounts.id = delivery_submissions.remittance_account_id', 'left')
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

        $payrollModel = $this->buildPayrollQuery($selectedRiderId, $selectedPayrollMonth, $selectedCutoff);
        $payrolls = $payrollModel->paginate(20, 'payrolls');

        $cutoffSummaries = (new PayrollModel())
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
                'pager' => $payrollModel->pager,
                'pageGroup' => 'payrolls',
            ]
        ));
    }

    public function settings()
    {
        $historySearch = trim((string) $this->request->getGet('q'));
        $riders = (new RiderModel())->orderBy('name', 'ASC')->findAll();
        $remittanceAccounts = (new RemittanceAccountModel())
            ->orderBy('sort_order', 'ASC')
            ->orderBy('account_name', 'ASC')
            ->findAll();

        foreach ($riders as &$rider) {
            $currentRate = $this->getCommissionRateForDate((int) $rider['id'], date('Y-m-d'));
            $rider['current_commission_rate'] = $currentRate;
            $rider['display_commission_rate'] = number_format($currentRate, 2);
        }
        unset($rider);

        $rateHistoryModel = $this->buildCommissionRateHistoryQuery($historySearch);
        $rateHistory = $rateHistoryModel->paginate(20, 'commission_history');

        return view('admin/settings', array_merge(
            $this->adminBaseData('settings'),
            [
                'riders' => $riders,
                'remittanceAccounts' => $remittanceAccounts,
                'today' => date('Y-m-d'),
                'rateHistory' => $rateHistory,
                'historySearch' => $historySearch,
                'pager' => $rateHistoryModel->pager,
                'pageGroup' => 'commission_history',
            ]
        ));
    }

    public function adjustments()
    {
        $search = trim((string) $this->request->getGet('q'));
        $type = trim((string) $this->request->getGet('type'));
        $status = trim((string) $this->request->getGet('status'));
        $startDate = trim((string) $this->request->getGet('start_date'));
        $endDate = trim((string) $this->request->getGet('end_date'));

        $adjustmentModel = $this->buildAdjustmentQuery($search, $type, $status, $startDate, $endDate);
        $adjustments = $adjustmentModel->paginate(20, 'adjustments');

        return view('admin/adjustments', array_merge(
            $this->adminBaseData('adjustments'),
            [
                'today' => date('Y-m-d'),
                'adjustments' => $adjustments,
                'search' => $search,
                'type' => $type,
                'status' => $status,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'pager' => $adjustmentModel->pager,
                'pageGroup' => 'adjustments',
            ]
        ));
    }

    public function adjustmentsCsv()
    {
        $search = trim((string) $this->request->getGet('q'));
        $type = trim((string) $this->request->getGet('type'));
        $status = trim((string) $this->request->getGet('status'));
        $startDate = trim((string) $this->request->getGet('start_date'));
        $endDate = trim((string) $this->request->getGet('end_date'));

        $rows = $this->buildAdjustmentQuery($search, $type, $status, $startDate, $endDate)->findAll();

        $lines = [
            ['Date', 'Rider Code', 'Rider Name', 'Type', 'Amount', 'Description', 'Batch Reference', 'Status'],
        ];

        foreach ($rows as $row) {
            $lines[] = [
                (string) ($row['adjustment_date'] ?? ''),
                (string) ($row['rider_code'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['type'] ?? ''),
                number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
                (string) ($row['description'] ?? ''),
                (string) ($row['batch_reference'] ?: '-'),
                empty($row['payroll_id']) ? 'UNPAID' : 'LOCKED TO PAYROLL',
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
            ->setHeader('Content-Disposition', 'attachment; filename="adjustments-export.csv"')
            ->setBody($csv);
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


    public function createRider()
    {
        helper('credentials');

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
        $temporaryPassword = app_generate_temporary_password();
        $userModel = new UserModel();

        if (db_connect()->tableExists('users') && $userModel->where('username', $defaultUsername)->first()) {
            return redirect()->back()->withInput()->with('error', 'The rider code would create a username that already exists.');
        }

        $db = db_connect();
        $db->transStart();
        $successMessage = 'Rider details updated.';

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
                'password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
                'role' => 'rider',
                'rider_id' => (int) $riderId,
                'is_active' => 1,
                'force_password_change' => 1,
            ]);
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Unable to create rider profile.');
        }

        return redirect()->to('/admin/riders')->with('success', 'Rider profile created. Temporary login: ' . $defaultUsername . ' / ' . $temporaryPassword . '. The rider must change it after login.');
    }

    public function updateRider(int $id)
    {
        helper('credentials');

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
                $temporaryPassword = app_generate_temporary_password();
                $userModel->insert([
                    'username' => $username,
                    'password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
                    'role' => 'rider',
                    'rider_id' => $id,
                    'is_active' => $isActive,
                    'force_password_change' => 1,
                ]);
                $successMessage = 'Rider details updated. Temporary login: ' . $username . ' / ' . $temporaryPassword . '. The rider must change it after login.';
            }
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Unable to update rider profile.');
        }

        return redirect()->to('/admin/riders')->with('success', $successMessage);
    }

    public function resetRiderPassword(int $id)
    {
        helper('credentials');

        $rider = (new RiderModel())->find($id);
        if (! $rider) {
            return redirect()->to('/admin/riders')->with('error', 'Rider not found.');
        }

        $userModel = new UserModel();
        $user = $userModel->where('rider_id', $id)->where('role', 'rider')->first();
        $username = strtolower((string) $rider['rider_code']);
        $newPassword = app_generate_temporary_password();

        if ($user) {
            $userModel->update((int) $user['id'], [
                'username' => $username,
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'is_active' => (int) ($rider['is_active'] ?? 1),
                'force_password_change' => 1,
            ]);
        } else {
            $userModel->insert([
                'username' => $username,
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'role' => 'rider',
                'rider_id' => $id,
                'is_active' => (int) ($rider['is_active'] ?? 1),
                'force_password_change' => 1,
            ]);
        }

        return redirect()->to('/admin/riders')->with('success', 'Rider password reset. Temporary login: ' . $username . ' / ' . $newPassword . '. The rider must change it after login.');
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

    public function storeRemittanceAccount()
    {
        $rules = [
            'account_name' => 'required|min_length[3]|max_length[120]',
            'account_number' => 'permit_empty|max_length[80]',
            'description' => 'permit_empty|max_length[255]',
            'sort_order' => 'permit_empty|is_natural',
            'is_active' => 'required|in_list[0,1]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        (new RemittanceAccountModel())->insert([
            'account_name' => trim((string) $this->request->getPost('account_name')),
            'account_number' => trim((string) $this->request->getPost('account_number')),
            'description' => trim((string) $this->request->getPost('description')),
            'sort_order' => (int) ($this->request->getPost('sort_order') ?: 0),
            'is_active' => (int) $this->request->getPost('is_active') === 1 ? 1 : 0,
        ]);

        return redirect()->to('/admin/settings')->with('success', 'Remittance account added.');
    }

    public function updateRemittanceAccount(int $id)
    {
        $accountModel = new RemittanceAccountModel();
        $account = $accountModel->find($id);
        if (! $account) {
            return redirect()->to('/admin/settings')->with('error', 'Remittance account not found.');
        }

        $rules = [
            'account_name' => 'required|min_length[3]|max_length[120]',
            'account_number' => 'permit_empty|max_length[80]',
            'description' => 'permit_empty|max_length[255]',
            'sort_order' => 'permit_empty|is_natural',
            'is_active' => 'required|in_list[0,1]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $accountModel->update($id, [
            'account_name' => trim((string) $this->request->getPost('account_name')),
            'account_number' => trim((string) $this->request->getPost('account_number')),
            'description' => trim((string) $this->request->getPost('description')),
            'sort_order' => (int) ($this->request->getPost('sort_order') ?: 0),
            'is_active' => (int) $this->request->getPost('is_active') === 1 ? 1 : 0,
        ]);

        return redirect()->to('/admin/settings')->with('success', 'Remittance account updated.');
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
            'admin_entry_reason' => 'required|max_length[255]',
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
        $adminReason = trim((string) $this->request->getPost('admin_entry_reason'));

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
            'last_admin_reason' => $adminReason,
        ];

        $deliveryModel = new DeliveryRecordModel();
        $auditModel = new DeliveryAuditLogModel();
        $existing = $deliveryModel
            ->where('rider_id', (int) $this->request->getPost('rider_id'))
            ->where('delivery_date', (string) $this->request->getPost('delivery_date'))
            ->first();

        if ($existing) {
            if (! empty($existing['payroll_id'])) {
                return redirect()->back()->withInput()->with('error', 'This delivery day is already locked into payroll. Reopen the payroll batch or use a correction workflow before editing it.');
            }
            $payload['entry_source'] = $existing['entry_source'] ?? 'ADMIN_MANUAL';
            $payload['source_submission_id'] = $existing['source_submission_id'] ?? null;
            $payload['created_by_user_id'] = $existing['created_by_user_id'] ?? (int) session()->get('user_id');
            $deliveryModel->update((int) $existing['id'], $payload);
            $this->logDeliveryAudit($auditModel, [
                'delivery_record_id' => (int) $existing['id'],
                'rider_id' => (int) $payload['rider_id'],
                'action' => 'ADMIN_MANUAL_UPDATE',
                'notes' => $adminReason,
                'details_json' => json_encode([
                    'entry_source' => $payload['entry_source'],
                    'delivery_date' => $payload['delivery_date'],
                    'successful_deliveries' => $successful,
                    'commission_rate' => $appliedCommissionRate,
                    'expected_remittance' => $expectedRemittance,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            return redirect()->to('/admin/deliveries')->with('success', 'Existing rider-day record updated. Salary earning is PHP ' . number_format($totalDue, 2) . ' and expected remittance is PHP ' . number_format($expectedRemittance, 2) . '.');
        }

        $payload['entry_source'] = 'ADMIN_MANUAL';
        $payload['created_by_user_id'] = (int) session()->get('user_id');
        $deliveryModel->insert($payload);
        $deliveryId = (int) $deliveryModel->getInsertID();
        $this->logDeliveryAudit($auditModel, [
            'delivery_record_id' => $deliveryId,
            'rider_id' => (int) $payload['rider_id'],
            'action' => 'ADMIN_MANUAL_CREATE',
            'notes' => $adminReason,
            'details_json' => json_encode([
                'entry_source' => 'ADMIN_MANUAL',
                'delivery_date' => $payload['delivery_date'],
                'successful_deliveries' => $successful,
                'commission_rate' => $appliedCommissionRate,
                'expected_remittance' => $expectedRemittance,
            ], JSON_UNESCAPED_UNICODE),
        ]);

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
            'remittance_account_id' => ! empty($submission['remittance_account_id']) ? (int) $submission['remittance_account_id'] : null,
            'commission_rate' => $commissionRate,
            'total_due' => $totalDue,
            'notes' => (string) ($submission['notes'] ?? ''),
            'entry_source' => 'RIDER_SUBMISSION',
            'source_submission_id' => $submissionId,
            'created_by_user_id' => (int) session()->get('user_id'),
            'last_admin_reason' => 'Approved rider-submitted delivery request.',
        ];

        $deliveryModel = new DeliveryRecordModel();
        $auditModel = new DeliveryAuditLogModel();
        $db = db_connect();
        $db->transStart();

        $existing = $deliveryModel
            ->where('rider_id', (int) $submission['rider_id'])
            ->where('delivery_date', (string) $submission['delivery_date'])
            ->first();

        if ($existing) {
            $deliveryId = (int) $existing['id'];
            if (! empty($existing['payroll_id'])) {
                $db->transRollback();

                return redirect()->back()->withInput()->with('error', 'This delivery day is already locked into payroll. Reopen the payroll batch before approving the rider submission.');
            }
            $payload['created_by_user_id'] = $existing['created_by_user_id'] ?? (int) session()->get('user_id');
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

        $this->logDeliveryAudit($auditModel, [
            'delivery_record_id' => $deliveryId,
            'delivery_submission_id' => $submissionId,
            'rider_id' => (int) $submission['rider_id'],
            'action' => $existing ? 'DELIVERY_UPDATED_FROM_SUBMISSION' : 'DELIVERY_CREATED_FROM_SUBMISSION',
            'notes' => 'Admin approved rider submission and finalized commission rate.',
            'details_json' => json_encode([
                'commission_rate' => $commissionRate,
                'expected_remittance' => $payload['expected_remittance'],
                'successful_deliveries' => $successful,
                'remittance_account' => $this->formatRemittanceAccountLabel($submission),
            ], JSON_UNESCAPED_UNICODE),
        ]);
        $this->logDeliveryAudit($auditModel, [
            'delivery_record_id' => $deliveryId,
            'delivery_submission_id' => $submissionId,
            'rider_id' => (int) $submission['rider_id'],
            'action' => 'SUBMISSION_APPROVED',
            'notes' => 'Rider submission approved.',
        ]);

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

        $this->logDeliveryAudit(new DeliveryAuditLogModel(), [
            'delivery_submission_id' => $submissionId,
            'rider_id' => (int) ($submission['rider_id'] ?? 0),
            'action' => 'SUBMISSION_REJECTED',
            'notes' => $rejectionNote !== '' ? $rejectionNote : 'Submission rejected without additional note.',
        ]);

        if ($isModal) {
            return $this->response
                ->setHeader('Content-Type', 'text/html; charset=UTF-8')
                ->setBody('<script>window.parent.postMessage({ type: "submission-updated" }, "*");</script>');
        }

        return redirect()->to('/admin/remittances')->with('success', 'Rider submission rejected.');
    }

    public function storeDeliveryCorrectionRequest(int $deliveryId)
    {
        $delivery = (new DeliveryRecordModel())->find($deliveryId);
        if (! $delivery) {
            return redirect()->to('/admin/history')->with('error', 'Delivery record not found.');
        }

        $rules = [
            'allocated_parcels' => 'required|is_natural',
            'successful_deliveries' => 'required|is_natural',
            'expected_remittance' => 'required|decimal',
            'commission_rate' => 'required|decimal|greater_than[0]',
            'correction_reason' => 'required|max_length[1000]',
            'notes' => 'permit_empty|max_length[1000]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $allocated = (int) $this->request->getPost('allocated_parcels');
        $successful = (int) $this->request->getPost('successful_deliveries');
        if ($successful > $allocated) {
            return redirect()->back()->withInput()->with('error', 'Successful deliveries cannot exceed allocated parcels.');
        }

        $expectedRemittance = round((float) $this->request->getPost('expected_remittance'), 2);
        $commissionRate = round((float) $this->request->getPost('commission_rate'), 2);
        $notes = trim((string) $this->request->getPost('notes'));
        $reason = trim((string) $this->request->getPost('correction_reason'));

        $requestModel = new DeliveryCorrectionRequestModel();
        $requestModel->insert([
            'delivery_record_id' => $deliveryId,
            'requested_by_user_id' => (int) session()->get('user_id') ?: null,
            'status' => 'PENDING',
            'reason' => $reason,
            'requested_payload_json' => json_encode([
                'allocated_parcels' => $allocated,
                'successful_deliveries' => $successful,
                'failed_deliveries' => max(0, $allocated - $successful),
                'expected_remittance' => $expectedRemittance,
                'commission_rate' => $commissionRate,
                'total_due' => round($successful * $commissionRate, 2),
                'notes' => $notes,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $this->logDeliveryAudit(new DeliveryAuditLogModel(), [
            'delivery_record_id' => $deliveryId,
            'rider_id' => (int) ($delivery['rider_id'] ?? 0),
            'action' => 'CORRECTION_REQUEST_CREATED',
            'notes' => $reason,
            'details_json' => json_encode([
                'allocated_parcels' => $allocated,
                'successful_deliveries' => $successful,
                'expected_remittance' => $expectedRemittance,
                'commission_rate' => $commissionRate,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        return redirect()->to('/admin/deliveries/' . $deliveryId)->with('success', 'Correction request recorded. Apply it explicitly after review.');
    }

    public function applyDeliveryCorrectionRequest(int $requestId)
    {
        $returnTo = $this->request->getPost('return_to') ?: '/admin/deliveries/' . (int) ($this->request->getPost('delivery_record_id') ?: 0);
        $requestModel = new DeliveryCorrectionRequestModel();
        $request = $requestModel->find($requestId);

        if (! $request || ($request['status'] ?? '') !== 'PENDING') {
            return redirect()->to($returnTo ?: '/admin/history')->with('error', 'Correction request not found or already resolved.');
        }

        $deliveryModel = new DeliveryRecordModel();
        $delivery = $deliveryModel->find((int) $request['delivery_record_id']);
        if (! $delivery) {
            return redirect()->to($returnTo ?: '/admin/history')->with('error', 'Delivery record not found.');
        }

        if (! empty($delivery['payroll_id'])) {
            return redirect()->to($returnTo ?: '/admin/deliveries/' . (int) $delivery['id'])->with('error', 'This delivery day is already locked into payroll. Reopen the payroll batch before applying the correction.');
        }

        $payload = json_decode((string) ($request['requested_payload_json'] ?? ''), true);
        if (! is_array($payload)) {
            return redirect()->to($returnTo ?: '/admin/deliveries/' . (int) $delivery['id'])->with('error', 'Correction payload is invalid.');
        }

        $deliveryPayload = [
            'allocated_parcels' => (int) ($payload['allocated_parcels'] ?? $delivery['allocated_parcels']),
            'successful_deliveries' => (int) ($payload['successful_deliveries'] ?? $delivery['successful_deliveries']),
            'failed_deliveries' => (int) ($payload['failed_deliveries'] ?? $delivery['failed_deliveries']),
            'expected_remittance' => round((float) ($payload['expected_remittance'] ?? $delivery['expected_remittance']), 2),
            'commission_rate' => round((float) ($payload['commission_rate'] ?? $delivery['commission_rate']), 2),
            'total_due' => round((float) ($payload['total_due'] ?? $delivery['total_due']), 2),
            'notes' => (string) ($payload['notes'] ?? $delivery['notes']),
            'last_admin_reason' => 'Applied correction request #' . $requestId . '. ' . trim((string) $request['reason']),
        ];

        $deliveryModel->update((int) $delivery['id'], $deliveryPayload);
        $requestModel->update($requestId, [
            'status' => 'APPLIED',
            'resolution_note' => 'Correction applied to delivery record.',
            'applied_at' => date('Y-m-d H:i:s'),
        ]);

        $this->logDeliveryAudit(new DeliveryAuditLogModel(), [
            'delivery_record_id' => (int) $delivery['id'],
            'rider_id' => (int) ($delivery['rider_id'] ?? 0),
            'action' => 'CORRECTION_REQUEST_APPLIED',
            'notes' => (string) $request['reason'],
            'details_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        return redirect()->to($returnTo ?: '/admin/deliveries/' . (int) $delivery['id'])->with('success', 'Correction request applied to the delivery record.');
    }

    public function rejectDeliveryCorrectionRequest(int $requestId)
    {
        $returnTo = $this->request->getPost('return_to') ?: '/admin/deliveries/' . (int) ($this->request->getPost('delivery_record_id') ?: 0);
        $requestModel = new DeliveryCorrectionRequestModel();
        $request = $requestModel->find($requestId);

        if (! $request || ($request['status'] ?? '') !== 'PENDING') {
            return redirect()->to($returnTo ?: '/admin/history')->with('error', 'Correction request not found or already resolved.');
        }

        $resolutionNote = trim((string) $this->request->getPost('resolution_note'));
        $requestModel->update($requestId, [
            'status' => 'REJECTED',
            'resolution_note' => $resolutionNote !== '' ? $resolutionNote : 'Correction request rejected.',
        ]);

        $this->logDeliveryAudit(new DeliveryAuditLogModel(), [
            'delivery_record_id' => (int) $request['delivery_record_id'],
            'action' => 'CORRECTION_REQUEST_REJECTED',
            'notes' => $resolutionNote !== '' ? $resolutionNote : 'Correction request rejected.',
        ]);

        return redirect()->to($returnTo ?: '/admin/deliveries/' . (int) $request['delivery_record_id'])->with('success', 'Correction request rejected.');
    }

    public function remittanceForm(int $deliveryRecordId)
    {
        $delivery = (new DeliveryRecordModel())
            ->select('delivery_records.*, riders.name, riders.rider_code, remittance_accounts.account_name AS remittance_account_name, remittance_accounts.account_number AS remittance_account_number')
            ->join('riders', 'riders.id = delivery_records.rider_id')
            ->join('remittance_accounts', 'remittance_accounts.id = delivery_records.remittance_account_id', 'left')
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
            'remittance_account_id' => ! empty($delivery['remittance_account_id']) ? (int) $delivery['remittance_account_id'] : null,
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
            ->select('remittances.*, riders.name, riders.rider_code, remittance_accounts.account_name AS remittance_account_name, remittance_accounts.account_number AS remittance_account_number')
            ->join('riders', 'riders.id = remittances.rider_id')
            ->join('remittance_accounts', 'remittance_accounts.id = remittances.remittance_account_id', 'left')
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
            'payroll_status' => 'GENERATED',
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

        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Unable to generate payroll for the selected cutoff.');
        }

        return redirect()->to('/admin/payroll')->with('success', 'Payroll generated for ' . $rider['name'] . ' covering ' . $startDate . ' to ' . $endDate . '.');
    }

    public function releasePayroll(int $id)
    {
        $payrollModel = new PayrollModel();
        $payroll = $payrollModel->find($id);

        if (! $payroll) {
            return redirect()->to('/admin/payroll')->with('error', 'Payroll not found.');
        }

        if (($payroll['payroll_status'] ?? 'GENERATED') === 'RECEIVED') {
            return redirect()->to('/admin/payroll')->with('error', 'This payroll is already confirmed as received by the rider.');
        }

        $rules = [
            'payout_method' => 'required|in_list[CASH,BANK_TRANSFER,E_WALLET,OTHER]',
            'payout_reference' => 'permit_empty|max_length[100]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $payrollModel->update($id, [
            'payroll_status' => 'RELEASED',
            'payout_method' => (string) $this->request->getPost('payout_method'),
            'payout_reference' => trim((string) $this->request->getPost('payout_reference')),
            'released_at' => date('Y-m-d H:i:s'),
            'released_by_user_id' => (int) session()->get('user_id'),
            'received_at' => null,
            'received_notes' => null,
        ]);

        return redirect()->to('/admin/payroll')->with('success', 'Payroll marked as released and ready for rider confirmation.');
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

    public function correctionsCsv()
    {
        $search = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));
        $startDate = trim((string) $this->request->getGet('start_date'));
        $endDate = trim((string) $this->request->getGet('end_date'));

        $rows = $this->buildCorrectionQuery($search, $status, $startDate, $endDate)->findAll();

        $lines = [
            ['Requested At', 'Delivery Date', 'Rider Code', 'Rider Name', 'Reason', 'Status', 'Payroll Lock', 'Requested Delivered', 'Requested Commission', 'Requested Expected Remittance'],
        ];

        foreach ($rows as $row) {
            $requested = json_decode((string) ($row['requested_payload_json'] ?? ''), true) ?: [];
            $lines[] = [
                (string) ($row['created_at'] ?? ''),
                (string) ($row['delivery_date'] ?? ''),
                (string) ($row['rider_code'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['reason'] ?? ''),
                (string) ($row['status'] ?? 'PENDING'),
                ! empty($row['payroll_id']) ? 'Locked' : 'Open',
                isset($requested['successful_deliveries']) ? (string) ((int) $requested['successful_deliveries']) : '',
                isset($requested['commission_rate']) ? number_format((float) $requested['commission_rate'], 2, '.', '') : '',
                isset($requested['expected_remittance']) ? number_format((float) $requested['expected_remittance'], 2, '.', '') : '',
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
            ->setHeader('Content-Disposition', 'attachment; filename="corrections-export.csv"')
            ->setBody($csv);
    }

    public function announcements()
    {
        $search = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));
        $startDate = trim((string) $this->request->getGet('start_date'));
        $endDate = trim((string) $this->request->getGet('end_date'));

        $announcementModel = $this->buildAnnouncementQuery($search, $status, $startDate, $endDate);
        $announcements = $announcementModel->paginate(15, 'announcements');

        return view('admin/announcements', array_merge(
            $this->adminBaseData('announcements'),
            [
                'announcements' => $announcements,
                'today' => date('Y-m-d'),
                'search' => $search,
                'status' => $status,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'pager' => $announcementModel->pager,
                'pageGroup' => 'announcements',
            ]
        ));
    }

    public function deliveryHistoryCsv()
    {
        $search = trim((string) $this->request->getGet('q'));
        $startDate = trim((string) $this->request->getGet('start_date'));
        $endDate = trim((string) $this->request->getGet('end_date'));
        $riderId = trim((string) $this->request->getGet('rider_id'));
        $remittanceStatus = trim((string) $this->request->getGet('remittance_status'));

        $records = $this->buildHistoryQuery($search, $startDate, $endDate, $riderId, $remittanceStatus)->findAll();

        $lines = [
            ['Date', 'Rider Code', 'Rider Name', 'Source', 'Allocated', 'Successful', 'Failed', 'Salary Earning', 'Expected Remittance', 'Remittance Status'],
        ];

        foreach ($records as $record) {
            $lines[] = [
                (string) $record['delivery_date'],
                (string) $record['rider_code'],
                (string) $record['name'],
                (string) (($record['entry_source'] ?? 'ADMIN_MANUAL') === 'RIDER_SUBMISSION' ? 'RIDER_SUBMISSION' : 'ADMIN_MANUAL'),
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
        $pendingCorrectionCount = (new DeliveryCorrectionRequestModel())
            ->where('status', 'PENDING')
            ->countAllResults();
        $currentUser = (new UserModel())->find((int) session()->get('user_id'));

        return [
            'activeTab' => $activeTab,
            'title' => 'Admin - J&T Rider Remittance & Payroll',
            'riders' => (new RiderModel())->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'pendingSubmissionCount' => $pendingSubmissionCount,
            'pendingCorrectionCount' => $pendingCorrectionCount,
            'queueSummary' => [
                'pending_submissions' => $pendingSubmissionCount,
                'pending_corrections' => $pendingCorrectionCount,
                'overdue_remittances' => count(array_filter($this->getPendingRemittances(), static fn (array $item): bool => $item['aging_days'] > 0)),
            ],
            'accountSecurity' => [
                'label' => ! empty($currentUser) && ! empty($currentUser['is_active']) ? 'Password secured' : 'Check account access',
                'tone' => ! empty($currentUser) && ! empty($currentUser['is_active']) ? 'success' : 'warning',
                'detail' => 'Change your password anytime from the sidebar.',
            ],
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

    private function formatRemittanceAccountLabel(array $row): string
    {
        $name = trim((string) ($row['remittance_account_name'] ?? ''));
        $number = trim((string) ($row['remittance_account_number'] ?? ''));

        if ($name === '') {
            return '-';
        }

        return $number !== '' ? $name . ' (' . $number . ')' : $name;
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

    private function buildRiderQuery(string $search, string $status): RiderModel
    {
        $builder = (new RiderModel())
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

        return $builder;
    }

    private function buildDeliveryQuery(string $search, string $source, string $deliveryDate): DeliveryRecordModel
    {
        $builder = (new DeliveryRecordModel())
            ->select('delivery_records.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = delivery_records.rider_id');

        if ($search !== '') {
            $builder->groupStart()
                ->like('riders.name', $search)
                ->orLike('riders.rider_code', $search)
                ->groupEnd();
        }

        if (in_array($source, ['RIDER_SUBMISSION', 'ADMIN_MANUAL'], true)) {
            $builder->where('delivery_records.entry_source', $source);
        }

        if ($deliveryDate !== '') {
            $builder->where('delivery_records.delivery_date', $deliveryDate);
        }

        return $builder
            ->orderBy('delivery_records.updated_at', 'DESC')
            ->orderBy('delivery_records.delivery_date', 'DESC')
            ->orderBy('delivery_records.id', 'DESC');
    }

    private function buildPayrollQuery(string $selectedRiderId, string $selectedPayrollMonth, string $selectedCutoff): PayrollModel
    {
        $builder = (new PayrollModel())
            ->select('payrolls.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = payrolls.rider_id')
            ->orderBy('end_date', 'DESC')
            ->orderBy('payrolls.id', 'DESC');

        if ($selectedRiderId !== '' && ctype_digit($selectedRiderId)) {
            $builder->where('payrolls.rider_id', (int) $selectedRiderId);
        }

        if ($selectedPayrollMonth !== '' && in_array($selectedCutoff, ['FIRST', 'SECOND'], true)) {
            [$filterStart, $filterEnd] = $this->getCutoffWindow($selectedPayrollMonth, $selectedCutoff);
            $builder
                ->where('payrolls.start_date', $filterStart)
                ->where('payrolls.end_date', $filterEnd);
        } elseif ($selectedPayrollMonth !== '') {
            $builder
                ->where('payrolls.start_date >=', $selectedPayrollMonth . '-01')
                ->where('payrolls.start_date <=', date('Y-m-t', strtotime($selectedPayrollMonth . '-01')));
        }

        return $builder;
    }

    private function buildAdjustmentQuery(string $search, string $type, string $status, string $startDate, string $endDate): PayrollAdjustmentModel
    {
        $builder = (new PayrollAdjustmentModel())
            ->select('payroll_adjustments.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = payroll_adjustments.rider_id');

        if ($search !== '') {
            $builder->groupStart()
                ->like('riders.name', $search)
                ->orLike('riders.rider_code', $search)
                ->orLike('payroll_adjustments.description', $search)
                ->orLike('payroll_adjustments.batch_reference', $search)
                ->groupEnd();
        }

        if (in_array($type, ['BONUS', 'DEDUCTION'], true)) {
            $builder->where('payroll_adjustments.type', $type);
        }

        if ($status === 'UNPAID') {
            $builder->where('payroll_adjustments.payroll_id', null);
        } elseif ($status === 'LOCKED') {
            $builder->where('payroll_adjustments.payroll_id IS NOT NULL', null, false);
        }

        if ($startDate !== '') {
            $builder->where('payroll_adjustments.adjustment_date >=', $startDate);
        }

        if ($endDate !== '') {
            $builder->where('payroll_adjustments.adjustment_date <=', $endDate);
        }

        return $builder
            ->orderBy('adjustment_date', 'DESC')
            ->orderBy('payroll_adjustments.id', 'DESC');
    }

    private function buildAnnouncementQuery(string $search, string $status, string $startDate, string $endDate): AnnouncementModel
    {
        $builder = new AnnouncementModel();

        if ($search !== '') {
            $builder->groupStart()
                ->like('title', $search)
                ->orLike('message', $search)
                ->groupEnd();
        }

        if ($status === 'ACTIVE') {
            $builder->where('is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $builder->where('is_active', 0);
        }

        if ($startDate !== '') {
            $builder->where('DATE(published_at) >=', $startDate);
        }

        if ($endDate !== '') {
            $builder->where('DATE(published_at) <=', $endDate);
        }

        return $builder
            ->orderBy('published_at', 'DESC')
            ->orderBy('id', 'DESC');
    }

    private function buildActivityQuery(string $search, string $role, string $startDate, string $endDate): DeliveryAuditLogModel
    {
        $builder = (new DeliveryAuditLogModel())
            ->select('delivery_audit_logs.*, riders.name, riders.rider_code, users.username')
            ->join('riders', 'riders.id = delivery_audit_logs.rider_id', 'left')
            ->join('users', 'users.id = delivery_audit_logs.actor_user_id', 'left');

        if ($search !== '') {
            $builder->groupStart()
                ->like('riders.name', $search)
                ->orLike('riders.rider_code', $search)
                ->orLike('users.username', $search)
                ->orLike('delivery_audit_logs.action', $search)
                ->orLike('delivery_audit_logs.notes', $search)
                ->groupEnd();
        }

        if (in_array($role, ['admin', 'rider'], true)) {
            $builder->where('delivery_audit_logs.actor_role', $role);
        }

        if ($startDate !== '') {
            $builder->where('DATE(delivery_audit_logs.created_at) >=', $startDate);
        }

        if ($endDate !== '') {
            $builder->where('DATE(delivery_audit_logs.created_at) <=', $endDate);
        }

        return $builder
            ->orderBy('delivery_audit_logs.created_at', 'DESC')
            ->orderBy('delivery_audit_logs.id', 'DESC');
    }

    private function buildCommissionRateHistoryQuery(string $search): RiderCommissionRateModel
    {
        $builder = (new RiderCommissionRateModel())
            ->select('rider_commission_rates.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = rider_commission_rates.rider_id');

        if ($search !== '') {
            $builder->groupStart()
                ->like('riders.name', $search)
                ->orLike('riders.rider_code', $search)
                ->groupEnd();
        }

        return $builder
            ->orderBy('effective_date', 'DESC')
            ->orderBy('rider_commission_rates.id', 'DESC');
    }

    private function buildHistoryQuery(
        string $search,
        string $startDate,
        string $endDate,
        string $riderId,
        string $remittanceStatus
    ): DeliveryRecordModel {
        $builder = (new DeliveryRecordModel())
            ->select('delivery_records.*, riders.name, riders.rider_code, remittances.id AS remittance_id, remittances.variance_type, remittances.supposed_remittance, remittances.actual_remitted, remittance_accounts.account_name AS remittance_account_name, remittance_accounts.account_number AS remittance_account_number')
            ->join('riders', 'riders.id = delivery_records.rider_id')
            ->join('remittance_accounts', 'remittance_accounts.id = delivery_records.remittance_account_id', 'left')
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
            ->orderBy('delivery_records.updated_at', 'DESC')
            ->orderBy('delivery_records.delivery_date', 'DESC')
            ->orderBy('delivery_records.id', 'DESC');
    }

    private function buildCorrectionQuery(string $search, string $status, string $startDate, string $endDate): DeliveryCorrectionRequestModel
    {
        $builder = (new DeliveryCorrectionRequestModel())
            ->select('delivery_correction_requests.*, delivery_records.delivery_date, delivery_records.payroll_id, riders.name, riders.rider_code')
            ->join('delivery_records', 'delivery_records.id = delivery_correction_requests.delivery_record_id')
            ->join('riders', 'riders.id = delivery_records.rider_id');

        if ($search !== '') {
            $builder->groupStart()
                ->like('riders.name', $search)
                ->orLike('riders.rider_code', $search)
                ->orLike('delivery_correction_requests.reason', $search)
                ->groupEnd();
        }

        if (in_array($status, ['PENDING', 'APPLIED', 'REJECTED'], true)) {
            $builder->where('delivery_correction_requests.status', $status);
        }

        if ($startDate !== '') {
            $builder->where('delivery_records.delivery_date >=', $startDate);
        }

        if ($endDate !== '') {
            $builder->where('delivery_records.delivery_date <=', $endDate);
        }

        return $builder
            ->orderBy('delivery_correction_requests.created_at', 'DESC')
            ->orderBy('delivery_correction_requests.id', 'DESC');
    }

    private function paginateArray(array $items, int $perPage, string $pageName): array
    {
        $page = max(1, (int) ($this->request->getGet($pageName) ?: 1));
        $total = count($items);
        $offset = ($page - 1) * $perPage;

        return [
            'rows' => array_slice($items, $offset, $perPage),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'pageCount' => max(1, (int) ceil($total / $perPage)),
            'pageName' => $pageName,
        ];
    }

    private function logDeliveryAudit(DeliveryAuditLogModel $auditModel, array $data): void
    {
        $auditModel->insert([
            'delivery_record_id' => $data['delivery_record_id'] ?? null,
            'delivery_submission_id' => $data['delivery_submission_id'] ?? null,
            'rider_id' => $data['rider_id'] ?? null,
            'actor_user_id' => (int) session()->get('user_id') ?: null,
            'actor_role' => (string) session()->get('role'),
            'action' => (string) ($data['action'] ?? 'UNKNOWN'),
            'notes' => $data['notes'] ?? null,
            'details_json' => $data['details_json'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
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
            ->select('delivery_records.*, riders.name, riders.rider_code, remittance_accounts.account_name AS remittance_account_name, remittance_accounts.account_number AS remittance_account_number, remittances.id AS remittance_id, remittances.variance_type')
            ->join('riders', 'riders.id = delivery_records.rider_id')
            ->join('remittance_accounts', 'remittance_accounts.id = delivery_records.remittance_account_id', 'left')
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

