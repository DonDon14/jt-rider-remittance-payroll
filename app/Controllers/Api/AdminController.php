<?php

namespace App\Controllers\Api;

use App\Models\DeliveryAuditLogModel;
use App\Models\DeliveryRecordModel;
use App\Models\DeliverySubmissionModel;
use App\Models\PayrollModel;
use App\Models\RemittanceModel;
use App\Models\ShortagePaymentModel;

class AdminController extends BaseApiController
{
    public function pendingSubmissions()
    {
        $user = $this->requireApiUser('admin');
        if (! is_array($user)) {
            return $user;
        }

        $pagination = $this->getPagination();
        $submissionModel = new DeliverySubmissionModel();
        $builder = $submissionModel
            ->select('delivery_submissions.*, riders.name, riders.rider_code, remittance_accounts.account_name AS remittance_account_name, remittance_accounts.account_number AS remittance_account_number')
            ->join('riders', 'riders.id = delivery_submissions.rider_id')
            ->join('remittance_accounts', 'remittance_accounts.id = delivery_submissions.remittance_account_id', 'left')
            ->where('delivery_submissions.status', 'PENDING');
        $total = $builder->countAllResults(false);
        $rows = $builder
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('delivery_submissions.id', 'DESC')
            ->findAll($pagination['per_page'], $pagination['offset']);

        return $this->successList(array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'delivery_date' => (string) $row['delivery_date'],
            'rider' => [
                'id' => (int) $row['rider_id'],
                'rider_code' => (string) ($row['rider_code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
            ],
            'allocated_parcels' => (int) ($row['allocated_parcels'] ?? 0),
            'successful_deliveries' => (int) ($row['successful_deliveries'] ?? 0),
            'expected_remittance' => round((float) ($row['expected_remittance'] ?? 0), 2),
            'notes' => (string) ($row['notes'] ?? ''),
            'remittance_account' => [
                'name' => (string) ($row['remittance_account_name'] ?? ''),
                'number' => (string) ($row['remittance_account_number'] ?? ''),
            ],
        ], $rows), $pagination['page'], $pagination['per_page'], $total);
    }

    public function approveSubmission(int $submissionId)
    {
        $user = $this->requireApiUser('admin');
        if (! is_array($user)) {
            return $user;
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $rules = [
            'commission_rate' => 'required|decimal|greater_than[0]',
        ];
        if (! $this->validateData($payload, $rules)) {
            return $this->failValidation($this->validator->getErrors());
        }

        $submissionModel = new DeliverySubmissionModel();
        $submission = $submissionModel->find($submissionId);
        if (! $submission || ($submission['status'] ?? '') !== 'PENDING') {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Delivery submission not found or already processed.',
            ]);
        }

        $commissionRate = round((float) ($payload['commission_rate'] ?? 0), 2);
        $successful = (int) ($submission['successful_deliveries'] ?? 0);
        $totalDue = round($successful * $commissionRate, 2);

        $deliveryModel = new DeliveryRecordModel();
        $auditModel = new DeliveryAuditLogModel();
        $db = db_connect();
        $db->transStart();

        $existing = $deliveryModel
            ->where('rider_id', (int) $submission['rider_id'])
            ->where('delivery_date', (string) $submission['delivery_date'])
            ->first();

        $deliveryPayload = [
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
            'created_by_user_id' => (int) $user['id'],
            'last_admin_reason' => 'Approved rider submission via API.',
        ];

          if ($existing) {
              if (! empty($existing['payroll_id'])) {
                  $db->transRollback();

                  return $this->response->setStatusCode(409)->setJSON([
                      'status' => 'error',
                      'message' => 'This delivery day is already locked into payroll.',
                  ]);
              }

              $existingRemittance = (new RemittanceModel())->where('delivery_record_id', (int) $existing['id'])->first();
              if ($existingRemittance && ! in_array((string) ($existingRemittance['variance_type'] ?? 'PENDING'), ['PENDING'], true)) {
                  $db->transRollback();

                  return $this->response->setStatusCode(409)->setJSON([
                      'status' => 'error',
                      'message' => 'This delivery day already has a finalized remittance record. Use the correction workflow instead.',
                  ]);
              }

              $deliveryId = (int) $existing['id'];
              $deliveryModel->update($deliveryId, $deliveryPayload);
          } else {
              $deliveryId = (int) $deliveryModel->insert($deliveryPayload);
          }

          $remittanceModel = new RemittanceModel();
          $pendingPayload = [
              'rider_id' => (int) ($deliveryPayload['rider_id'] ?? 0),
              'delivery_record_id' => $deliveryId,
              'delivery_date' => (string) ($deliveryPayload['delivery_date'] ?? date('Y-m-d')),
              'remittance_account_id' => ! empty($deliveryPayload['remittance_account_id']) ? (int) $deliveryPayload['remittance_account_id'] : null,
              'cash_remitted' => 0,
              'gcash_remitted' => 0,
              'gcash_reference' => null,
              'denom_025' => 0,
              'denom_1' => 0,
              'denom_5' => 0,
              'denom_10' => 0,
              'denom_20' => 0,
              'denom_50' => 0,
              'denom_100' => 0,
              'denom_500' => 0,
              'denom_1000' => 0,
              'total_due' => (float) ($deliveryPayload['total_due'] ?? 0),
              'total_remitted' => 0,
              'supposed_remittance' => isset($deliveryPayload['expected_remittance']) ? (float) $deliveryPayload['expected_remittance'] : null,
              'actual_remitted' => null,
              'variance_amount' => 0,
              'variance_type' => 'PENDING',
          ];
          $existingPendingRemittance = $remittanceModel->where('delivery_record_id', $deliveryId)->first();
          if ($existingPendingRemittance) {
              $remittanceModel->update((int) $existingPendingRemittance['id'], $pendingPayload);
          } else {
              $remittanceModel->insert($pendingPayload);
          }

          $submissionModel->update($submissionId, [
              'status' => 'APPROVED',
            'processed_delivery_record_id' => $deliveryId,
        ]);

        $auditModel->insert([
            'delivery_record_id' => $deliveryId,
            'delivery_submission_id' => $submissionId,
            'rider_id' => (int) $submission['rider_id'],
            'actor_user_id' => (int) $user['id'],
            'actor_role' => 'admin',
            'action' => $existing ? 'DELIVERY_UPDATED_FROM_SUBMISSION' : 'DELIVERY_CREATED_FROM_SUBMISSION',
            'notes' => 'Admin approved rider submission via API.',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        if (! $db->transStatus()) {
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Unable to approve the delivery submission.',
            ]);
        }

        return $this->success([
            'message' => 'Submission approved.',
            'delivery_record_id' => $deliveryId,
        ]);
    }

    public function rejectSubmission(int $submissionId)
    {
        $user = $this->requireApiUser('admin');
        if (! is_array($user)) {
            return $user;
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $submissionModel = new DeliverySubmissionModel();
        $submission = $submissionModel->find($submissionId);
        if (! $submission || ($submission['status'] ?? '') !== 'PENDING') {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Delivery submission not found or already processed.',
            ]);
        }

        $rejectionNote = trim((string) ($payload['rejection_note'] ?? ''));
        $notes = trim((string) ($submission['notes'] ?? ''));
        if ($rejectionNote !== '') {
            $notes = trim($notes . PHP_EOL . 'Admin rejection note: ' . $rejectionNote);
        }

        $submissionModel->update($submissionId, [
            'status' => 'REJECTED',
            'notes' => $notes,
        ]);

        (new DeliveryAuditLogModel())->insert([
            'delivery_submission_id' => $submissionId,
            'rider_id' => (int) ($submission['rider_id'] ?? 0),
            'actor_user_id' => (int) $user['id'],
            'actor_role' => 'admin',
            'action' => 'SUBMISSION_REJECTED',
            'notes' => $rejectionNote !== '' ? $rejectionNote : 'Submission rejected without additional note.',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->success(['message' => 'Submission rejected.']);
    }

    public function pendingRemittances()
    {
        $user = $this->requireApiUser('admin');
        if (! is_array($user)) {
            return $user;
        }

        $pagination = $this->getPagination();
        $today = strtotime(date('Y-m-d'));
        $deliveryModel = new DeliveryRecordModel();
        $builder = $deliveryModel
            ->select('delivery_records.*, riders.name, riders.rider_code, remittance_accounts.account_name AS remittance_account_name, remittance_accounts.account_number AS remittance_account_number, remittances.id AS remittance_id, remittances.variance_type')
            ->join('riders', 'riders.id = delivery_records.rider_id')
            ->join('remittance_accounts', 'remittance_accounts.id = delivery_records.remittance_account_id', 'left')
            ->join('remittances', 'remittances.delivery_record_id = delivery_records.id', 'left')
            ->where('delivery_records.payroll_id', null)
            ->groupStart()
                ->where('remittances.id', null)
                ->orWhere('remittances.variance_type', 'PENDING')
            ->groupEnd();
        $total = $builder->countAllResults(false);
        $rows = $builder
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('delivery_records.id', 'DESC')
            ->findAll($pagination['per_page'], $pagination['offset']);

        $items = array_map(static function (array $row) use ($today): array {
            $deliveryTs = strtotime((string) $row['delivery_date']);
            $agingDays = max(0, (int) floor(($today - $deliveryTs) / 86400));

            return [
                'delivery_record_id' => (int) $row['id'],
                'delivery_date' => (string) $row['delivery_date'],
                'aging_days' => $agingDays,
                'pending_status' => ($row['variance_type'] ?? null) === 'PENDING'
                    ? 'AWAITING EXPECTED TOTAL'
                    : ($agingDays > 0 ? 'OVERDUE' : 'DUE TODAY'),
                'rider' => [
                    'id' => (int) $row['rider_id'],
                    'rider_code' => (string) ($row['rider_code'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                ],
                'expected_remittance' => round((float) ($row['expected_remittance'] ?? 0), 2),
                'remittance_account' => [
                    'name' => (string) ($row['remittance_account_name'] ?? ''),
                    'number' => (string) ($row['remittance_account_number'] ?? ''),
                ],
            ];
        }, $rows);

        return $this->successList($items, $pagination['page'], $pagination['per_page'], $total);
    }

    public function shortages()
    {
        $user = $this->requireApiUser('admin');
        if (! is_array($user)) {
            return $user;
        }

        $pagination = $this->getPagination();
        $remittanceModel = new RemittanceModel();
        $builder = $remittanceModel
            ->select('remittances.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = remittances.rider_id')
            ->where('remittances.variance_type', 'SHORT');
        $total = $builder->countAllResults(false);
        $shortages = $builder
            ->orderBy('delivery_date', 'DESC')
            ->orderBy('remittances.id', 'DESC')
            ->findAll($pagination['per_page'], $pagination['offset']);

        $paymentRows = $shortages === [] ? [] : (new ShortagePaymentModel())
            ->select('remittance_id, SUM(amount) AS paid_total')
            ->whereIn('remittance_id', array_column($shortages, 'id'))
            ->groupBy('remittance_id')
            ->findAll();

        $paidMap = [];
        foreach ($paymentRows as $row) {
            $paidMap[(int) $row['remittance_id']] = round((float) $row['paid_total'], 2);
        }

        $items = array_map(static function (array $shortage) use ($paidMap): array {
            $paidAmount = $paidMap[(int) $shortage['id']] ?? 0.0;
            $outstanding = max(0, round((float) ($shortage['variance_amount'] ?? 0) - $paidAmount, 2));

            return [
                'remittance_id' => (int) $shortage['id'],
                'delivery_date' => (string) ($shortage['delivery_date'] ?? ''),
                'rider' => [
                    'id' => (int) $shortage['rider_id'],
                    'rider_code' => (string) ($shortage['rider_code'] ?? ''),
                    'name' => (string) ($shortage['name'] ?? ''),
                ],
                'variance_amount' => round((float) ($shortage['variance_amount'] ?? 0), 2),
                'paid_amount' => $paidAmount,
                'outstanding_balance' => $outstanding,
                'shortage_status' => $outstanding > 0 ? 'OPEN' : 'SETTLED',
            ];
        }, $shortages);

        return $this->successList($items, $pagination['page'], $pagination['per_page'], $total);
    }

    public function payrolls()
    {
        $user = $this->requireApiUser('admin');
        if (! is_array($user)) {
            return $user;
        }

        $pagination = $this->getPagination();
        $status = trim((string) $this->request->getGet('payroll_status'));
        $payrollModel = new PayrollModel();
        $builder = $payrollModel
            ->select('payrolls.*, riders.name, riders.rider_code')
            ->join('riders', 'riders.id = payrolls.rider_id');

        if (in_array($status, ['GENERATED', 'RELEASED', 'RECEIVED'], true)) {
            $builder->where('payrolls.payroll_status', $status);
        }

        $total = $builder->countAllResults(false);
        $rows = $builder
            ->orderBy('end_date', 'DESC')
            ->orderBy('payrolls.id', 'DESC')
            ->findAll($pagination['per_page'], $pagination['offset']);

        return $this->successList(array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'start_date' => (string) ($row['start_date'] ?? $row['month_year']),
            'end_date' => (string) ($row['end_date'] ?? $row['month_year']),
            'payroll_status' => (string) ($row['payroll_status'] ?? 'GENERATED'),
            'net_pay' => round((float) ($row['net_pay'] ?? 0), 2),
            'gross_earnings' => round((float) ($row['gross_earnings'] ?? 0), 2),
            'payout_method' => (string) ($row['payout_method'] ?? ''),
            'payout_reference' => (string) ($row['payout_reference'] ?? ''),
            'released_at' => (string) ($row['released_at'] ?? ''),
            'received_at' => (string) ($row['received_at'] ?? ''),
            'rider' => [
                'id' => (int) $row['rider_id'],
                'rider_code' => (string) ($row['rider_code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
            ],
        ], $rows), $pagination['page'], $pagination['per_page'], $total);
    }

    public function releasePayroll(int $id)
    {
        $user = $this->requireApiUser('admin');
        if (! is_array($user)) {
            return $user;
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $rules = [
            'payout_method' => 'required|in_list[CASH,BANK_TRANSFER,E_WALLET,OTHER]',
            'payout_reference' => 'permit_empty|max_length[100]',
        ];
        if (! $this->validateData($payload, $rules)) {
            return $this->failValidation($this->validator->getErrors());
        }

        $payrollModel = new PayrollModel();
        $payroll = $payrollModel->find($id);
        if (! $payroll) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Payroll not found.',
            ]);
        }

        if (($payroll['payroll_status'] ?? 'GENERATED') === 'RECEIVED') {
            return $this->response->setStatusCode(409)->setJSON([
                'status' => 'error',
                'message' => 'This payroll is already confirmed as received by the rider.',
            ]);
        }

        $payrollModel->update($id, [
            'payroll_status' => 'RELEASED',
            'payout_method' => (string) ($payload['payout_method'] ?? ''),
            'payout_reference' => trim((string) ($payload['payout_reference'] ?? '')),
            'released_at' => date('Y-m-d H:i:s'),
            'released_by_user_id' => (int) $user['id'],
            'received_at' => null,
            'received_notes' => null,
        ]);

        return $this->success(['message' => 'Payroll marked as released.']);
    }
}
