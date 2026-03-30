<?php

namespace App\Controllers;

use App\Models\AnnouncementModel;
use App\Models\DeliveryRecordModel;
use App\Models\DeliverySubmissionModel;
use App\Models\PayrollModel;
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

        $riderId = (int) session()->get('rider_id');

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
            ->where('rider_id', $riderId)
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll(10);

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
            'announcements' => array_slice($announcements, 0, 6),
            'latestAnnouncementPopup' => $latestAnnouncementPopup,
        ]);
    }

    public function storeDeliverySubmission()
    {
        $riderId = (int) session()->get('rider_id');
        if ($riderId <= 0) {
            return redirect()->to('/login')->with('error', 'Rider account is not linked to a rider profile.');
        }

        $rules = [
            'delivery_date' => 'required|valid_date[Y-m-d]',
            'allocated_parcels' => 'required|is_natural',
            'successful_deliveries' => 'required|is_natural',
            'expected_remittance' => 'required|decimal',
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
            'notes' => trim((string) $this->request->getPost('notes')),
            'status' => 'PENDING',
        ];

        if ($existing) {
            $submissionModel->update((int) $existing['id'], $payload);

            return redirect()->to('/rider-dashboard')->with('success', 'Your delivery record request was updated. It now appears in your submitted requests and in Admin > Remittances > Rider Submitted Delivery Requests.');
        }

        $submissionModel->insert($payload);

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
