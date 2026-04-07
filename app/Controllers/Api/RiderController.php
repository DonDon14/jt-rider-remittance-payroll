<?php

namespace App\Controllers\Api;

use App\Models\DeliveryRecordModel;
use App\Models\DeliverySubmissionModel;
use App\Models\PayrollModel;
use App\Models\RemittanceModel;
use App\Models\ShortagePaymentModel;

class RiderController extends BaseApiController
{
    public function profile()
    {
        $user = $this->requireApiUser('rider');
        if (! is_array($user)) {
            return $user;
        }

        $rider = $user['resolved_rider'] ?? null;
        if (! $rider) {
            return $this->failUnauthorized('Rider profile not found.');
        }

        return $this->success([
            'user' => [
                'id' => (int) $user['id'],
                'username' => (string) $user['username'],
                'role' => (string) $user['role'],
            ],
            'rider' => [
                'id' => (int) $rider['id'],
                'rider_code' => (string) $rider['rider_code'],
                'name' => (string) $rider['name'],
                'contact_number' => (string) ($rider['contact_number'] ?? ''),
                'commission_rate' => round((float) ($rider['commission_rate'] ?? 0), 2),
            ],
        ]);
    }

    public function dashboard()
    {
        $user = $this->requireApiUser('rider');
        if (! is_array($user)) {
            return $user;
        }

        $rider = $user['resolved_rider'] ?? null;
        if (! $rider) {
            return $this->failUnauthorized('Rider profile not found.');
        }

        $month = trim((string) $this->request->getGet('month'));
        if (! preg_match('/^\d{4}\-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $monthStart = date('Y-m-01', strtotime($month . '-01'));
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $riderId = (int) $rider['id'];

        $deliveries = (new DeliveryRecordModel())
            ->where('rider_id', $riderId)
            ->where('delivery_date >=', $monthStart)
            ->where('delivery_date <=', $monthEnd)
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

        return $this->success([
            'month' => $month,
            'range' => [
                'start' => $monthStart,
                'end' => $monthEnd,
            ],
            'stats' => [
                'allocated' => array_sum(array_column($deliveries, 'allocated_parcels')),
                'successful' => array_sum(array_column($deliveries, 'successful_deliveries')),
                'failed' => array_sum(array_column($deliveries, 'failed_deliveries')),
                'running_salary' => round(array_sum(array_column($deliveries, 'total_due')), 2),
                'expected_remittance' => round(array_sum(array_map(static fn (array $delivery): float => (float) ($delivery['expected_remittance'] ?? 0), $deliveries)), 2),
                'total_remitted' => round(array_sum(array_map(static fn (array $remittance): float => (float) ($remittance['total_remitted'] ?? 0), $remittances)), 2),
                'shortage_deductions' => $monthlyShortageDeductions,
                'shortage_repayments' => $monthlyRepayments,
                'projected_net' => round(array_sum(array_column($deliveries, 'total_due')) - $monthlyShortageDeductions + $monthlyRepayments, 2),
            ],
        ]);
    }

    public function payrolls()
    {
        $user = $this->requireApiUser('rider');
        if (! is_array($user)) {
            return $user;
        }

        $rider = $user['resolved_rider'] ?? null;
        if (! $rider) {
            return $this->failUnauthorized('Rider profile not found.');
        }

        $rows = (new PayrollModel())
            ->where('rider_id', (int) $rider['id'])
            ->orderBy('end_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll(20);

        return $this->success([
            'items' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'start_date' => (string) ($row['start_date'] ?? $row['month_year']),
                'end_date' => (string) ($row['end_date'] ?? $row['month_year']),
                'gross_earnings' => round((float) ($row['gross_earnings'] ?? 0), 2),
                'net_pay' => round((float) ($row['net_pay'] ?? 0), 2),
                'payroll_status' => (string) ($row['payroll_status'] ?? 'GENERATED'),
                'payout_method' => (string) ($row['payout_method'] ?? ''),
                'payout_reference' => (string) ($row['payout_reference'] ?? ''),
                'released_at' => (string) ($row['released_at'] ?? ''),
                'received_at' => (string) ($row['received_at'] ?? ''),
                'received_notes' => (string) ($row['received_notes'] ?? ''),
            ], $rows),
        ]);
    }

    public function submissions()
    {
        $user = $this->requireApiUser('rider');
        if (! is_array($user)) {
            return $user;
        }

        $rider = $user['resolved_rider'] ?? null;
        if (! $rider) {
            return $this->failUnauthorized('Rider profile not found.');
        }

        $rows = (new DeliverySubmissionModel())
            ->select('delivery_submissions.*, remittance_accounts.account_name AS remittance_account_name, remittance_accounts.account_number AS remittance_account_number')
            ->join('remittance_accounts', 'remittance_accounts.id = delivery_submissions.remittance_account_id', 'left')
            ->where('rider_id', (int) $rider['id'])
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll(20);

        return $this->success([
            'items' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'delivery_date' => (string) $row['delivery_date'],
                'allocated_parcels' => (int) ($row['allocated_parcels'] ?? 0),
                'successful_deliveries' => (int) ($row['successful_deliveries'] ?? 0),
                'failed_deliveries' => (int) ($row['failed_deliveries'] ?? 0),
                'expected_remittance' => round((float) ($row['expected_remittance'] ?? 0), 2),
                'status' => (string) ($row['status'] ?? 'PENDING'),
                'remittance_account' => [
                    'name' => (string) ($row['remittance_account_name'] ?? ''),
                    'number' => (string) ($row['remittance_account_number'] ?? ''),
                ],
                'notes' => (string) ($row['notes'] ?? ''),
            ], $rows),
        ]);
    }
}
