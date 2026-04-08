<?php

namespace App\Controllers\Api;

use App\Models\AnnouncementModel;
use App\Models\DeliveryAuditLogModel;
use App\Models\DeliveryRecordModel;
use App\Models\DeliverySubmissionModel;
use App\Models\PayrollModel;
use App\Models\RemittanceAccountModel;
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

        $paidDeliveries = array_values(array_filter($deliveries, static fn (array $delivery): bool => ! empty($delivery['payroll_id'])));
        $unpaidDeliveries = array_values(array_filter($deliveries, static fn (array $delivery): bool => empty($delivery['payroll_id'])));
        $paidDeliveryIds = array_map(static fn (array $delivery): int => (int) $delivery['id'], $paidDeliveries);
        $unpaidDeliveryIds = array_map(static fn (array $delivery): int => (int) $delivery['id'], $unpaidDeliveries);
        $paidDeliveryIdLookup = array_flip($paidDeliveryIds);
        $unpaidDeliveryIdLookup = array_flip($unpaidDeliveryIds);

        $paidShortageDeductions = round(array_sum(array_map(static function (array $remittance) use ($paidDeliveryIdLookup): float {
            if (($remittance['variance_type'] ?? '') !== 'SHORT') {
                return 0.0;
            }

            return isset($paidDeliveryIdLookup[(int) ($remittance['delivery_record_id'] ?? 0)])
                ? (float) ($remittance['variance_amount'] ?? 0)
                : 0.0;
        }, $remittances)), 2);
        $currentShortageDeductions = round(array_sum(array_map(static function (array $remittance) use ($unpaidDeliveryIdLookup): float {
            if (($remittance['variance_type'] ?? '') !== 'SHORT') {
                return 0.0;
            }

            return isset($unpaidDeliveryIdLookup[(int) ($remittance['delivery_record_id'] ?? 0)])
                ? (float) ($remittance['variance_amount'] ?? 0)
                : 0.0;
        }, $remittances)), 2);
        $paidRepayments = round((float) ((new ShortagePaymentModel())
            ->selectSum('amount')
            ->where('rider_id', $riderId)
            ->where('payment_date >=', $monthStart)
            ->where('payment_date <=', $monthEnd)
            ->where('payroll_id IS NOT NULL', null, false)
            ->first()['amount'] ?? 0), 2);
        $currentRepayments = round((float) ((new ShortagePaymentModel())
            ->selectSum('amount')
            ->where('rider_id', $riderId)
            ->where('payment_date >=', $monthStart)
            ->where('payment_date <=', $monthEnd)
            ->where('payroll_id', null)
            ->first()['amount'] ?? 0), 2);
        $monthEarnings = round(array_sum(array_column($deliveries, 'total_due')), 2);
        $currentPayable = round(array_sum(array_column($unpaidDeliveries, 'total_due')), 2);
        $paidEarnings = round(array_sum(array_column($paidDeliveries, 'total_due')), 2);

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
                'month_earnings' => $monthEarnings,
                'running_salary' => $currentPayable,
                'current_payable' => $currentPayable,
                'paid_earnings' => $paidEarnings,
                'expected_remittance' => round(array_sum(array_map(static fn (array $delivery): float => (float) ($delivery['expected_remittance'] ?? 0), $deliveries)), 2),
                'total_remitted' => round(array_sum(array_map(static fn (array $remittance): float => (float) ($remittance['total_remitted'] ?? 0), $remittances)), 2),
                'shortage_deductions' => $currentShortageDeductions,
                'shortage_repayments' => $currentRepayments,
                'paid_shortage_deductions' => $paidShortageDeductions,
                'paid_shortage_repayments' => $paidRepayments,
                'projected_net' => round($currentPayable - $currentShortageDeductions + $currentRepayments, 2),
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

        $pagination = $this->getPagination();
        $payrollModel = new PayrollModel();
        $builder = $payrollModel->where('rider_id', (int) $rider['id']);
        $total = $builder->countAllResults(false);
        $rows = $builder
            ->orderBy('end_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll($pagination['per_page'], $pagination['offset']);

        return $this->successList(array_map(static fn (array $row): array => [
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
        ], $rows), $pagination['page'], $pagination['per_page'], $total);
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

        $pagination = $this->getPagination();
        $submissionModel = new DeliverySubmissionModel();
        $builder = $submissionModel
            ->select('delivery_submissions.*, remittance_accounts.account_name AS remittance_account_name, remittance_accounts.account_number AS remittance_account_number')
            ->join('remittance_accounts', 'remittance_accounts.id = delivery_submissions.remittance_account_id', 'left')
            ->where('rider_id', (int) $rider['id']);
        $total = $builder->countAllResults(false);
        $rows = $builder
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll($pagination['per_page'], $pagination['offset']);

        return $this->successList(array_map(static fn (array $row): array => [
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
        ], $rows), $pagination['page'], $pagination['per_page'], $total);
    }

    public function announcements()
    {
        $user = $this->requireApiUser('rider');
        if (! is_array($user)) {
            return $user;
        }

        $today = date('Y-m-d H:i:s');
        $rows = (new AnnouncementModel())
            ->orderBy('published_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();

        $items = array_values(array_filter(array_map(static function (array $announcement) use ($today): ?array {
            if (! (bool) ($announcement['is_active'] ?? false)) {
                return null;
            }

            if (! empty($announcement['published_at']) && (string) $announcement['published_at'] > $today) {
                return null;
            }

            if (! empty($announcement['expires_at']) && (string) $announcement['expires_at'] < $today) {
                return null;
            }

            return [
                'id' => (int) $announcement['id'],
                'title' => (string) $announcement['title'],
                'message' => (string) $announcement['message'],
                'published_at' => (string) ($announcement['published_at'] ?? ''),
                'expires_at' => (string) ($announcement['expires_at'] ?? ''),
            ];
        }, $rows)));

        return $this->successList($items, 1, max(1, count($items)), count($items));
    }

    public function remittanceAccounts()
    {
        $user = $this->requireApiUser('rider');
        if (! is_array($user)) {
            return $user;
        }

        $rows = (new RemittanceAccountModel())
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('account_name', 'ASC')
            ->findAll();

        return $this->successList(array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'account_name' => (string) $row['account_name'],
            'account_number' => (string) ($row['account_number'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
        ], $rows), 1, max(1, count($rows)), count($rows));
    }

    public function storeSubmission()
    {
        $user = $this->requireApiUser('rider');
        if (! is_array($user)) {
            return $user;
        }

        $rider = $user['resolved_rider'] ?? null;
        if (! $rider) {
            return $this->failUnauthorized('Rider profile not found.');
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $rules = [
            'delivery_date' => 'required|valid_date[Y-m-d]',
            'allocated_parcels' => 'required|is_natural',
            'successful_deliveries' => 'required|is_natural',
            'expected_remittance' => 'required|decimal',
            'remittance_account_id' => 'required|is_natural_no_zero',
            'notes' => 'permit_empty|max_length[1000]',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->failValidation($this->validator->getErrors());
        }

        $allocated = (int) ($payload['allocated_parcels'] ?? 0);
        $successful = (int) ($payload['successful_deliveries'] ?? 0);
        if ($successful > $allocated) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Successful deliveries cannot exceed allocated parcels.',
            ]);
        }

        $expectedRemittance = round((float) ($payload['expected_remittance'] ?? 0), 2);
        if ($expectedRemittance < 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Expected remittance cannot be negative.',
            ]);
        }

        $remittanceAccountId = (int) ($payload['remittance_account_id'] ?? 0);
        $remittanceAccount = (new RemittanceAccountModel())
            ->where('id', $remittanceAccountId)
            ->where('is_active', 1)
            ->first();
        if (! $remittanceAccount) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Select a valid remittance account before submitting the request.',
            ]);
        }

        $submissionModel = new DeliverySubmissionModel();
        $deliveryDate = (string) ($payload['delivery_date'] ?? '');
        $existing = $submissionModel
            ->where('rider_id', (int) $rider['id'])
            ->where('delivery_date', $deliveryDate)
            ->where('status', 'PENDING')
            ->first();

        $submissionPayload = [
            'rider_id' => (int) $rider['id'],
            'delivery_date' => $deliveryDate,
            'allocated_parcels' => $allocated,
            'successful_deliveries' => $successful,
            'failed_deliveries' => max(0, $allocated - $successful),
            'expected_remittance' => $expectedRemittance,
            'remittance_account_id' => $remittanceAccountId,
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'status' => 'PENDING',
        ];

        $auditModel = new DeliveryAuditLogModel();
        $actorUserId = (int) $user['id'];
        if ($existing) {
            $submissionModel->update((int) $existing['id'], $submissionPayload);
            $auditModel->insert([
                'delivery_submission_id' => (int) $existing['id'],
                'rider_id' => (int) $rider['id'],
                'actor_user_id' => $actorUserId,
                'actor_role' => 'rider',
                'action' => 'RIDER_SUBMISSION_UPDATED',
                'notes' => 'Rider updated pending delivery submission via API.',
                'details_json' => json_encode([
                    'delivery_date' => $deliveryDate,
                    'successful_deliveries' => $successful,
                    'expected_remittance' => $expectedRemittance,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return $this->success([
                'message' => 'Delivery request updated.',
                'submission_id' => (int) $existing['id'],
            ]);
        }

        $submissionModel->insert($submissionPayload);
        $submissionId = (int) $submissionModel->getInsertID();
        $auditModel->insert([
            'delivery_submission_id' => $submissionId,
            'rider_id' => (int) $rider['id'],
            'actor_user_id' => $actorUserId,
            'actor_role' => 'rider',
            'action' => 'RIDER_SUBMISSION_CREATED',
            'notes' => 'Rider created delivery submission via API.',
            'details_json' => json_encode([
                'delivery_date' => $deliveryDate,
                'successful_deliveries' => $successful,
                'expected_remittance' => $expectedRemittance,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->success([
            'message' => 'Delivery request submitted.',
            'submission_id' => $submissionId,
        ], 201);
    }

    public function confirmPayrollReceipt(int $payrollId)
    {
        $user = $this->requireApiUser('rider');
        if (! is_array($user)) {
            return $user;
        }

        $rider = $user['resolved_rider'] ?? null;
        if (! $rider) {
            return $this->failUnauthorized('Rider profile not found.');
        }

        $payroll = (new PayrollModel())->find($payrollId);
        if (! $payroll || (int) ($payroll['rider_id'] ?? 0) !== (int) $rider['id']) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Payroll record not found.',
            ]);
        }

        if (($payroll['payroll_status'] ?? 'GENERATED') !== 'RELEASED') {
            return $this->response->setStatusCode(409)->setJSON([
                'status' => 'error',
                'message' => 'This payroll is not yet marked as released by admin.',
            ]);
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $rules = [
            'received_notes' => 'permit_empty|max_length[500]',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->failValidation($this->validator->getErrors());
        }

        (new PayrollModel())->update($payrollId, [
            'payroll_status' => 'RECEIVED',
            'received_at' => date('Y-m-d H:i:s'),
            'received_notes' => trim((string) ($payload['received_notes'] ?? '')),
        ]);

        return $this->success([
            'message' => 'Payroll receipt confirmed.',
            'payroll_id' => $payrollId,
        ]);
    }
}

