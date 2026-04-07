<?php

namespace App\Controllers;

use App\Models\AnnouncementModel;
use App\Models\DeliveryAuditLogModel;
use App\Models\DeliveryRecordModel;
use App\Models\DeliverySubmissionModel;
use App\Models\PayrollModel;
use App\Models\RemittanceAccountModel;
use App\Models\RemittanceModel;
use App\Models\RiderModel;
use App\Models\ShortagePaymentModel;
use App\Models\UserModel;

class RiderController extends BaseController
{
    public function ownDashboard()
    {
        if (session()->get('role') === 'admin') {
            return redirect()->to('/admin');
        }

        $riderId = $this->resolveSessionRiderId();
        if ($riderId <= 0) {
            return redirect()->to('/login')->with('error', 'Rider account is not linked to a rider profile.');
        }

        return redirect()->to('/rider/' . $riderId);
    }

    public function dashboard(int $riderId)
    {
        $month = $this->request->getGet('month') ?: date('Y-m');
        $monthStart = date('Y-m-01', strtotime($month . '-01'));
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $rider = (new RiderModel())->find($riderId);
        if (! $rider) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Rider not found.');
        }
        if (! (bool) ($rider['is_active'] ?? true)) {
            session()->destroy();

            return redirect()->to('/login')->with('error', 'Rider account is inactive.');
        }

        $deliveryModel = new DeliveryRecordModel();
        $deliveries = $deliveryModel
            ->where('rider_id', $riderId)
            ->where('delivery_date >=', $monthStart)
            ->where('delivery_date <=', $monthEnd)
            ->orderBy('delivery_date', 'DESC')
            ->findAll();

        $remittances = (new RemittanceModel())
            ->where('rider_id', $riderId)
            ->where('delivery_date >=', $monthStart)
            ->where('delivery_date <=', $monthEnd)
            ->findAll();

        $monthlyShortages = array_filter($remittances, static fn (array $remittance): bool => ($remittance['variance_type'] ?? '') === 'SHORT');
        $monthlyShortageDeductions = round(array_sum(array_map(static fn (array $remittance): float => (float) ($remittance['variance_amount'] ?? 0), $monthlyShortages)), 2);
        $monthlyRepayments = round((float) ((new ShortagePaymentModel())
            ->selectSum('amount')
            ->where('rider_id', $riderId)
            ->where('payment_date >=', $monthStart)
            ->where('payment_date <=', $monthEnd)
            ->first()['amount'] ?? 0), 2);

        $allShortages = (new RemittanceModel())
            ->where('rider_id', $riderId)
            ->where('variance_type', 'SHORT')
            ->findAll();

        $outstandingShortageBalance = 0.0;
        foreach ($allShortages as $shortage) {
            $paidTotal = (float) ((new ShortagePaymentModel())
                ->selectSum('amount')
                ->where('remittance_id', (int) $shortage['id'])
                ->first()['amount'] ?? 0);
            $outstandingShortageBalance += max(0, round((float) $shortage['variance_amount'] - $paidTotal, 2));
        }

        $stats = [
            'allocated' => array_sum(array_column($deliveries, 'allocated_parcels')),
            'successful' => array_sum(array_column($deliveries, 'successful_deliveries')),
            'failed' => array_sum(array_column($deliveries, 'failed_deliveries')),
            'running_salary' => round(array_sum(array_column($deliveries, 'total_due')), 2),
            'expected_remittance' => round(array_sum(array_map(static fn (array $delivery): float => (float) ($delivery['expected_remittance'] ?? 0), $deliveries)), 2),
            'total_remitted' => round(array_sum(array_map(static fn (array $remittance): float => (float) ($remittance['total_remitted'] ?? 0), $remittances)), 2),
            'shortage_deductions' => $monthlyShortageDeductions,
            'shortage_repayments' => $monthlyRepayments,
            'projected_net' => round(array_sum(array_column($deliveries, 'total_due')) - $monthlyShortageDeductions + $monthlyRepayments, 2),
            'outstanding_shortage_balance' => round($outstandingShortageBalance, 2),
        ];

        $payrollHistory = (new PayrollModel())
            ->where('rider_id', $riderId)
            ->orderBy('end_date', 'DESC')
            ->findAll(6);

        $submissionHistory = (new DeliverySubmissionModel())
            ->select('delivery_submissions.*, remittance_accounts.account_name AS remittance_account_name, remittance_accounts.account_number AS remittance_account_number')
            ->join('remittance_accounts', 'remittance_accounts.id = delivery_submissions.remittance_account_id', 'left')
            ->where('rider_id', $riderId)
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll(10);

        $remittanceAccounts = (new RemittanceAccountModel())
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('account_name', 'ASC')
            ->findAll();

        $announcements = $this->getActiveAnnouncements();
        $latestAnnouncementPopup = null;
        $user = (new UserModel())->find((int) session()->get('user_id'));
        if (! empty($announcements)) {
            $latestAnnouncement = $announcements[0];
            if ((int) ($user['last_seen_announcement_id'] ?? 0) !== (int) $latestAnnouncement['id']) {
                $latestAnnouncementPopup = $latestAnnouncement;
            }
        }

        return view('rider/dashboard', [
            'rider' => $rider,
            'deliveries' => $deliveries,
            'stats' => $stats,
            'month' => $month,
            'payrollHistory' => $payrollHistory,
            'submissionHistory' => $submissionHistory,
            'remittanceAccounts' => $remittanceAccounts,
            'announcements' => array_slice($announcements, 0, 6),
            'latestAnnouncementPopup' => $latestAnnouncementPopup,
            'accountSecurity' => [
                'label' => ! empty($user) && ! empty($user['is_active']) ? 'Password secured' : 'Check account access',
                'tone' => ! empty($user) && ! empty($user['is_active']) ? 'success' : 'warning',
                'detail' => 'Use Change Password in the top bar if you need to update your login.',
            ],
        ]);
    }

    public function storeDeliverySubmission()
    {
        $riderId = $this->resolveSessionRiderId();
        if ($riderId <= 0) {
            return redirect()->to('/login')->with('error', 'Rider account is not linked to a rider profile.');
        }

        $rules = [
            'delivery_date' => 'required|valid_date[Y-m-d]',
            'allocated_parcels' => 'required|is_natural',
            'successful_deliveries' => 'required|is_natural',
            'expected_remittance' => 'required|decimal',
            'remittance_account_id' => 'required|is_natural_no_zero',
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
        if ($expectedRemittance < 0) {
            return redirect()->back()->withInput()->with('error', 'Expected remittance cannot be negative.');
        }

        $remittanceAccountId = (int) $this->request->getPost('remittance_account_id');
        $remittanceAccount = (new RemittanceAccountModel())
            ->where('id', $remittanceAccountId)
            ->where('is_active', 1)
            ->first();
        if (! $remittanceAccount) {
            return redirect()->back()->withInput()->with('error', 'Select a valid remittance account before submitting the request.');
        }

        $submissionModel = new DeliverySubmissionModel();
        $deliveryDate = (string) $this->request->getPost('delivery_date');
        $existing = $submissionModel
            ->where('rider_id', $riderId)
            ->where('delivery_date', $deliveryDate)
            ->where('status', 'PENDING')
            ->first();

        $payload = [
            'rider_id' => $riderId,
            'delivery_date' => $deliveryDate,
            'allocated_parcels' => $allocated,
            'successful_deliveries' => $successful,
            'failed_deliveries' => max(0, $allocated - $successful),
            'expected_remittance' => $expectedRemittance,
            'remittance_account_id' => $remittanceAccountId,
            'notes' => trim((string) $this->request->getPost('notes')),
            'status' => 'PENDING',
        ];

        if ($existing) {
            $submissionModel->update((int) $existing['id'], $payload);
            (new DeliveryAuditLogModel())->insert([
                'delivery_submission_id' => (int) $existing['id'],
                'rider_id' => $riderId,
                'actor_user_id' => (int) session()->get('user_id'),
                'actor_role' => 'rider',
                'action' => 'RIDER_SUBMISSION_UPDATED',
                'notes' => 'Rider updated pending delivery submission.',
                'details_json' => json_encode([
                    'delivery_date' => $deliveryDate,
                    'successful_deliveries' => $successful,
                    'expected_remittance' => $expectedRemittance,
                    'remittance_account' => $this->formatRemittanceAccountLabel($remittanceAccount),
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return redirect()->to('/rider-dashboard')->with('success', 'Your delivery record request was updated. It now appears in your submitted requests and in Admin > Remittances > Rider Submitted Delivery Requests.');
        }

        $submissionModel->insert($payload);
        $submissionId = (int) $submissionModel->getInsertID();
        (new DeliveryAuditLogModel())->insert([
            'delivery_submission_id' => $submissionId,
            'rider_id' => $riderId,
            'actor_user_id' => (int) session()->get('user_id'),
            'actor_role' => 'rider',
            'action' => 'RIDER_SUBMISSION_CREATED',
            'notes' => 'Rider created delivery submission.',
            'details_json' => json_encode([
                'delivery_date' => $deliveryDate,
                'successful_deliveries' => $successful,
                'expected_remittance' => $expectedRemittance,
                'remittance_account' => $this->formatRemittanceAccountLabel($remittanceAccount),
            ], JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

        return redirect()->to('/rider-dashboard')->with('success', 'Your delivery record request was submitted. It now appears in your submitted requests and in Admin > Remittances > Rider Submitted Delivery Requests.');
    }

    public function markAnnouncementRead(int $announcementId)
    {
        $announcement = (new AnnouncementModel())->find($announcementId);
        if (! $announcement) {
            return redirect()->to('/rider-dashboard')->with('error', 'Announcement not found.');
        }

        (new UserModel())->update((int) session()->get('user_id'), [
            'last_seen_announcement_id' => $announcementId,
        ]);

        return redirect()->to('/rider-dashboard');
    }

    private function resolveSessionRiderId(): int
    {
        $sessionRiderId = (int) session()->get('rider_id');
        if ($sessionRiderId > 0) {
            $rider = (new RiderModel())->find($sessionRiderId);
            if ($rider) {
                return $sessionRiderId;
            }
        }

        $user = (new UserModel())->find((int) session()->get('user_id'));
        if (! $user || ($user['role'] ?? '') !== 'rider') {
            return 0;
        }

        $resolvedRiderId = (int) ($user['rider_id'] ?? 0);
        if ($resolvedRiderId <= 0) {
            $username = strtolower(trim((string) ($user['username'] ?? '')));
            if ($username !== '') {
                foreach ((new RiderModel())->findAll() as $rider) {
                    if (strtolower((string) ($rider['rider_code'] ?? '')) === $username) {
                        $resolvedRiderId = (int) $rider['id'];
                        break;
                    }
                }
            }
        }

        if ($resolvedRiderId > 0) {
            if ((int) ($user['rider_id'] ?? 0) !== $resolvedRiderId) {
                (new UserModel())->update((int) $user['id'], ['rider_id' => $resolvedRiderId]);
            }
            session()->set('rider_id', $resolvedRiderId);
        }

        return $resolvedRiderId;
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

    private function formatRemittanceAccountLabel(array $account): string
    {
        $label = trim((string) ($account['account_name'] ?? ''));
        $number = trim((string) ($account['account_number'] ?? ''));

        return $number !== '' ? $label . ' (' . $number . ')' : $label;
    }
}
