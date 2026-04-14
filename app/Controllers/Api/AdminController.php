<?php

namespace App\Controllers\Api;

use App\Models\DeliveryAuditLogModel;
use App\Models\DeliveryRecordModel;
use App\Models\DeliverySubmissionModel;
use App\Models\PayrollAdjustmentModel;
use App\Models\PayrollModel;
use App\Models\RemittanceEntryModel;
use App\Models\RemittanceModel;
use App\Models\RiderModel;
use App\Models\ShortagePaymentModel;

class AdminController extends BaseApiController
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

    public function pendingSubmissionDetail(int $submissionId)
    {
        $user = $this->requireApiUser('admin');
        if (! is_array($user)) {
            return $user;
        }

        $submission = $this->findSubmissionWithRelations($submissionId);
        if (! $submission || ($submission['status'] ?? '') !== 'PENDING') {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Delivery submission not found or already processed.',
            ]);
        }

        return $this->success([
            'submission' => $this->formatSubmissionRow($submission),
        ]);
    }

    public function remittanceDetail(int $deliveryRecordId)
    {
        $user = $this->requireApiUser('admin');
        if (! is_array($user)) {
            return $user;
        }

        $delivery = (new DeliveryRecordModel())
            ->select('delivery_records.*, riders.name, riders.rider_code, remittance_accounts.account_name AS remittance_account_name, remittance_accounts.account_number AS remittance_account_number')
            ->join('riders', 'riders.id = delivery_records.rider_id')
            ->join('remittance_accounts', 'remittance_accounts.id = delivery_records.remittance_account_id', 'left')
            ->where('delivery_records.id', $deliveryRecordId)
            ->first();

        if (! $delivery) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Delivery record not found.',
            ]);
        }

        $remittance = (new RemittanceModel())->where('delivery_record_id', $deliveryRecordId)->first();
        $entries = $remittance ? $this->getRemittanceEntriesForSummary((int) $remittance['id']) : [];

        return $this->success([
            'delivery' => [
                'id' => (int) $delivery['id'],
                'delivery_date' => (string) $delivery['delivery_date'],
                'allocated_parcels' => (int) ($delivery['allocated_parcels'] ?? 0),
                'successful_deliveries' => (int) ($delivery['successful_deliveries'] ?? 0),
                'failed_deliveries' => (int) ($delivery['failed_deliveries'] ?? 0),
                'expected_remittance' => round((float) ($delivery['expected_remittance'] ?? 0), 2),
                'total_due' => round((float) ($delivery['total_due'] ?? 0), 2),
                'rider' => [
                    'id' => (int) $delivery['rider_id'],
                    'rider_code' => (string) ($delivery['rider_code'] ?? ''),
                    'name' => (string) ($delivery['name'] ?? ''),
                ],
                'remittance_account' => [
                    'id' => ! empty($delivery['remittance_account_id']) ? (int) $delivery['remittance_account_id'] : null,
                    'name' => (string) ($delivery['remittance_account_name'] ?? ''),
                    'number' => (string) ($delivery['remittance_account_number'] ?? ''),
                ],
            ],
            'remittance' => $remittance ? [
                'id' => (int) $remittance['id'],
                'cash_remitted' => round((float) ($remittance['cash_remitted'] ?? 0), 2),
                'gcash_remitted' => round((float) ($remittance['gcash_remitted'] ?? 0), 2),
                'total_remitted' => round((float) ($remittance['total_remitted'] ?? 0), 2),
                'supposed_remittance' => round((float) ($remittance['supposed_remittance'] ?? 0), 2),
                'variance_amount' => round((float) ($remittance['variance_amount'] ?? 0), 2),
                'variance_type' => (string) ($remittance['variance_type'] ?? 'PENDING'),
                'gcash_reference' => (string) ($remittance['gcash_reference'] ?? ''),
            ] : null,
            'entries' => array_map(fn (array $entry): array => $this->formatEntryRow($entry), $entries),
            'denominations' => $this->denominations,
        ]);
    }

    public function saveRemittance(int $deliveryRecordId)
    {
        $user = $this->requireApiUser('admin');
        if (! is_array($user)) {
            return $user;
        }

        $delivery = (new DeliveryRecordModel())->find($deliveryRecordId);
        if (! $delivery) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Delivery record not found.',
            ]);
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $totals = $this->calculateRemittanceTotalsFromPayload($payload);
        $cashInput = trim((string) ($payload['cash_remitted'] ?? ''));
        $gcashInput = trim((string) ($payload['gcash_remitted'] ?? ''));
        $gcashReference = trim((string) ($payload['gcash_reference'] ?? ''));
        $entryNotes = trim((string) ($payload['entry_notes'] ?? ''));
        $cashRemitted = $totals['cash_total'] > 0
            ? $totals['cash_total']
            : ($cashInput === '' ? 0.0 : (float) $cashInput);
        $gcashRemitted = $gcashInput === '' ? 0.0 : (float) $gcashInput;

        if ($cashRemitted < 0 || $gcashRemitted < 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Remittance amounts cannot be negative.',
            ]);
        }

        $entryTotal = round($cashRemitted + $gcashRemitted, 2);
        if ($entryTotal <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Enter the cash, GCash, or denomination counts for the remittance piece you are recording.',
            ]);
        }

        $summary = $this->syncPendingRemittanceForDelivery($deliveryRecordId, array_merge($delivery, ['delivery_record_id' => $deliveryRecordId]));
        if (! $summary) {
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Unable to prepare the remittance summary for this delivery day.',
            ]);
        }

        $entryModel = new RemittanceEntryModel();
        $existingEntries = $this->getRemittanceEntriesForSummary((int) $summary['id']);
        $entryModel->insert(array_merge($totals['denoms'], [
            'remittance_id' => (int) $summary['id'],
            'remittance_account_id' => ! empty($delivery['remittance_account_id']) ? (int) $delivery['remittance_account_id'] : null,
            'entry_type' => $existingEntries === [] ? 'INITIAL' : 'SUPPLEMENTAL',
            'entry_sequence' => count($existingEntries) + 1,
            'cash_remitted' => $cashRemitted,
            'gcash_remitted' => $gcashRemitted,
            'gcash_reference' => $gcashReference !== '' ? $gcashReference : null,
            'total_remitted' => $entryTotal,
            'notes' => $entryNotes !== '' ? $entryNotes : null,
            'created_by_user_id' => (int) $user['id'],
        ]));

        $updatedRemittance = $this->syncPendingRemittanceForDelivery($deliveryRecordId, array_merge($delivery, ['delivery_record_id' => $deliveryRecordId]));

        return $this->success([
            'message' => count($existingEntries) > 0 ? 'Supplemental remittance recorded.' : 'Remittance saved.',
            'remittance' => $updatedRemittance ? [
                'id' => (int) $updatedRemittance['id'],
                'cash_remitted' => round((float) ($updatedRemittance['cash_remitted'] ?? 0), 2),
                'gcash_remitted' => round((float) ($updatedRemittance['gcash_remitted'] ?? 0), 2),
                'total_remitted' => round((float) ($updatedRemittance['total_remitted'] ?? 0), 2),
                'variance_amount' => round((float) ($updatedRemittance['variance_amount'] ?? 0), 2),
                'variance_type' => (string) ($updatedRemittance['variance_type'] ?? 'PENDING'),
            ] : null,
            'entries' => array_map(fn (array $entry): array => $this->formatEntryRow($entry), $this->getRemittanceEntriesForSummary((int) ($updatedRemittance['id'] ?? $summary['id']))),
        ]);
    }

    public function deletePendingRemittance(int $deliveryRecordId)
    {
        $user = $this->requireApiUser('admin');
        if (! is_array($user)) {
            return $user;
        }

        $deliveryModel = new DeliveryRecordModel();
        $delivery = $deliveryModel->find($deliveryRecordId);
        if (! $delivery) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Delivery record not found.',
            ]);
        }

        if (! empty($delivery['payroll_id'])) {
            return $this->response->setStatusCode(409)->setJSON([
                'status' => 'error',
                'message' => 'This delivery day is already locked into payroll and cannot be deleted.',
            ]);
        }

        $remittance = (new RemittanceModel())->where('delivery_record_id', $deliveryRecordId)->first();
        if ($remittance && ($remittance['variance_type'] ?? 'PENDING') !== 'PENDING') {
            return $this->response->setStatusCode(409)->setJSON([
                'status' => 'error',
                'message' => 'Only pending remittance records can be deleted.',
            ]);
        }

        $submissionModel = new DeliverySubmissionModel();
        $submission = null;
        if (! empty($delivery['source_submission_id'])) {
            $submission = $submissionModel->find((int) $delivery['source_submission_id']);
        }
        if (! $submission) {
            $submission = $submissionModel
                ->where('processed_delivery_record_id', $deliveryRecordId)
                ->where('status', 'APPROVED')
                ->first();
        }

        $db = db_connect();
        $db->transStart();

        if ($submission && ($submission['status'] ?? '') === 'APPROVED') {
            $submissionModel->update((int) $submission['id'], [
                'status' => 'PENDING',
                'processed_delivery_record_id' => null,
            ]);
        }

        (new DeliveryAuditLogModel())->insert([
            'delivery_record_id' => (int) $delivery['id'],
            'delivery_submission_id' => $submission ? (int) $submission['id'] : null,
            'rider_id' => (int) ($delivery['rider_id'] ?? 0),
            'actor_user_id' => (int) $user['id'],
            'actor_role' => 'admin',
            'action' => 'PENDING_REMITTANCE_DELETED',
            'notes' => 'Admin deleted pending remittance queue item via API.',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $deliveryModel->delete($deliveryRecordId);
        $db->transComplete();

        if (! $db->transStatus()) {
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Unable to delete the pending remittance.',
            ]);
        }

        return $this->success([
            'message' => 'Pending remittance deleted.',
            'delivery_record_id' => $deliveryRecordId,
        ]);
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

    public function recordShortagePayment(int $remittanceId)
    {
        $user = $this->requireApiUser('admin');
        if (! is_array($user)) {
            return $user;
        }

        $remittance = (new RemittanceModel())->find($remittanceId);
        if (! $remittance || ($remittance['variance_type'] ?? '') !== 'SHORT') {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Shortage record not found.',
            ]);
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $rules = [
            'payment_date' => 'required|valid_date[Y-m-d]',
            'amount' => 'required|decimal|greater_than[0]',
            'notes' => 'permit_empty|max_length[500]',
        ];
        if (! $this->validateData($payload, $rules)) {
            return $this->failValidation($this->validator->getErrors());
        }

        $outstanding = $this->findShortageOutstandingBalance($remittanceId, (float) ($remittance['variance_amount'] ?? 0));
        if ($outstanding === null) {
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Unable to load shortage balance.',
            ]);
        }

        $amount = round((float) ($payload['amount'] ?? 0), 2);
        if ($amount > $outstanding + 0.00001) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Payment exceeds the remaining shortage balance.',
            ]);
        }

        (new ShortagePaymentModel())->insert([
            'remittance_id' => $remittanceId,
            'rider_id' => (int) ($remittance['rider_id'] ?? 0),
            'payment_date' => (string) ($payload['payment_date'] ?? ''),
            'amount' => $amount,
            'notes' => trim((string) ($payload['notes'] ?? '')),
        ]);

        $remaining = $this->findShortageOutstandingBalance($remittanceId, (float) ($remittance['variance_amount'] ?? 0));

        return $this->success([
            'message' => 'Shortage payment recorded.',
            'remittance_id' => $remittanceId,
            'remaining_balance' => $remaining,
            'shortage_status' => ($remaining ?? 0) > 0 ? 'OPEN' : 'SETTLED',
        ]);
    }

    public function riders()
    {
        $user = $this->requireApiUser('admin');
        if (! is_array($user)) {
            return $user;
        }

        $rows = (new RiderModel())
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();

        return $this->success([
            'items' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'rider_code' => (string) ($row['rider_code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'commission_rate' => round((float) ($row['commission_rate'] ?? 0), 2),
            ], $rows),
        ]);
    }

    public function generatePayroll()
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
            'rider_id' => 'required|is_natural_no_zero',
            'payroll_month' => 'required|regex_match[/^\d{4}\-\d{2}$/]',
            'cutoff_period' => 'required|in_list[FIRST,SECOND]',
        ];
        if (! $this->validateData($payload, $rules)) {
            return $this->failValidation($this->validator->getErrors());
        }

        $riderId = (int) ($payload['rider_id'] ?? 0);
        $payrollMonth = (string) ($payload['payroll_month'] ?? '');
        $cutoffPeriod = (string) ($payload['cutoff_period'] ?? '');
        [$startDate, $endDate] = $this->getCutoffWindow($payrollMonth, $cutoffPeriod);

        $rider = (new RiderModel())->find($riderId);
        if (! $rider) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Rider not found.',
            ]);
        }

        $payrollModel = new PayrollModel();
        $duplicateRange = $payrollModel
            ->where('rider_id', $riderId)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->first();
        if ($duplicateRange) {
            return $this->response->setStatusCode(409)->setJSON([
                'status' => 'error',
                'message' => 'A payroll for this rider and date range already exists.',
            ]);
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
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'No unpaid delivery days, shortage repayments, or payroll adjustments were found in that date range.',
            ]);
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
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Unable to generate payroll for the selected cutoff.',
            ]);
        }

        return $this->success([
            'message' => 'Payroll generated for ' . $rider['name'] . ' covering ' . $startDate . ' to ' . $endDate . '.',
            'payroll' => [
                'id' => $payrollId,
                'rider_id' => $riderId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'net_pay' => $netPay,
                'gross_earnings' => $grossEarnings,
                'shortage_deductions' => $shortageDeductions,
                'shortage_payments_received' => $shortagePaymentsReceived,
                'bonus_total' => $bonusTotal,
                'deduction_total' => $deductionTotal,
                'payroll_status' => 'GENERATED',
            ],
        ]);
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
    private function findSubmissionWithRelations(int $submissionId): ?array
    {
        return (new DeliverySubmissionModel())
            ->select('delivery_submissions.*, riders.name, riders.rider_code, riders.commission_rate, remittance_accounts.account_name AS remittance_account_name, remittance_accounts.account_number AS remittance_account_number')
            ->join('riders', 'riders.id = delivery_submissions.rider_id')
            ->join('remittance_accounts', 'remittance_accounts.id = delivery_submissions.remittance_account_id', 'left')
            ->where('delivery_submissions.id', $submissionId)
            ->first();
    }

    private function formatSubmissionRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'delivery_date' => (string) $row['delivery_date'],
            'rider' => [
                'id' => (int) $row['rider_id'],
                'rider_code' => (string) ($row['rider_code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
            ],
            'allocated_parcels' => (int) ($row['allocated_parcels'] ?? 0),
            'successful_deliveries' => (int) ($row['successful_deliveries'] ?? 0),
            'failed_deliveries' => (int) ($row['failed_deliveries'] ?? 0),
            'expected_remittance' => round((float) ($row['expected_remittance'] ?? 0), 2),
            'commission_rate' => round((float) ($row['commission_rate'] ?? 0), 2),
            'notes' => (string) ($row['notes'] ?? ''),
            'remittance_account' => [
                'id' => ! empty($row['remittance_account_id']) ? (int) $row['remittance_account_id'] : null,
                'name' => (string) ($row['remittance_account_name'] ?? ''),
                'number' => (string) ($row['remittance_account_number'] ?? ''),
            ],
        ];
    }

    private function calculateRemittanceTotalsFromPayload(array $payload): array
    {
        $cashTotal = 0.0;
        $counts = [];
        foreach ($this->denominations as $field => $value) {
            $count = max(0, (int) ($payload[$field] ?? 0));
            $counts[$field] = $count;
            $cashTotal += $count * $value;
        }

        return [
            'cash_total' => round($cashTotal, 2),
            'denoms' => $counts,
        ];
    }

    private function buildRemittanceAggregatePayload(array $deliveryPayload, array $entries): array
    {
        $denomTotals = array_fill_keys(array_keys($this->denominations), 0);
        $cashRemitted = 0.0;
        $gcashRemitted = 0.0;
        $latestReference = null;

        foreach ($entries as $entry) {
            foreach (array_keys($this->denominations) as $field) {
                $denomTotals[$field] += (int) ($entry[$field] ?? 0);
            }

            $cashRemitted += (float) ($entry['cash_remitted'] ?? 0);
            $gcashRemitted += (float) ($entry['gcash_remitted'] ?? 0);
            $reference = trim((string) ($entry['gcash_reference'] ?? ''));
            if ($reference !== '') {
                $latestReference = $reference;
            }
        }

        $supposedRemittance = isset($deliveryPayload['expected_remittance']) ? (float) $deliveryPayload['expected_remittance'] : null;
        $totalRemitted = round($cashRemitted + $gcashRemitted, 2);
        $variance = 0.0;
        $varianceType = 'PENDING';
        $actualRemitted = null;

        if ($entries !== []) {
            $actualRemitted = $totalRemitted;
            if ($supposedRemittance !== null) {
                $variance = round($totalRemitted - $supposedRemittance, 2);
                $varianceType = 'BALANCED';
                if ($variance > 0.005) {
                    $varianceType = 'OVER';
                } elseif ($variance < -0.005) {
                    $varianceType = 'SHORT';
                }
            }
        }

        return array_merge($denomTotals, [
            'rider_id' => (int) ($deliveryPayload['rider_id'] ?? 0),
            'delivery_record_id' => (int) ($deliveryPayload['delivery_record_id'] ?? 0),
            'delivery_date' => (string) ($deliveryPayload['delivery_date'] ?? date('Y-m-d')),
            'remittance_account_id' => ! empty($deliveryPayload['remittance_account_id']) ? (int) $deliveryPayload['remittance_account_id'] : null,
            'cash_remitted' => round($cashRemitted, 2),
            'gcash_remitted' => round($gcashRemitted, 2),
            'gcash_reference' => $latestReference,
            'total_due' => round((float) ($deliveryPayload['total_due'] ?? 0), 2),
            'total_remitted' => $entries === [] ? 0.0 : $totalRemitted,
            'supposed_remittance' => $supposedRemittance,
            'actual_remitted' => $actualRemitted,
            'variance_amount' => abs($variance),
            'variance_type' => $varianceType,
        ]);
    }

    private function getRemittanceEntriesForSummary(int $remittanceId): array
    {
        return (new RemittanceEntryModel())
            ->select('remittance_entries.*, remittance_accounts.account_name AS remittance_account_name, remittance_accounts.account_number AS remittance_account_number')
            ->join('remittance_accounts', 'remittance_accounts.id = remittance_entries.remittance_account_id', 'left')
            ->where('remittance_id', $remittanceId)
            ->orderBy('entry_sequence', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    private function syncPendingRemittanceForDelivery(int $deliveryId, array $deliveryPayload): ?array
    {
        $remittanceModel = new RemittanceModel();
        $existing = $remittanceModel->where('delivery_record_id', $deliveryId)->first();
        $entries = $existing ? $this->getRemittanceEntriesForSummary((int) $existing['id']) : [];
        $summaryPayload = $this->buildRemittanceAggregatePayload(array_merge($deliveryPayload, ['delivery_record_id' => $deliveryId]), $entries);

        if ($existing) {
            $remittanceModel->update((int) $existing['id'], $summaryPayload);
            return $remittanceModel->find((int) $existing['id']);
        }

        $remittanceId = (int) $remittanceModel->insert($summaryPayload);
        return $remittanceModel->find($remittanceId);
    }

    private function formatEntryRow(array $entry): array
    {
        $denoms = [];
        foreach (array_keys($this->denominations) as $field) {
            $denoms[$field] = (int) ($entry[$field] ?? 0);
        }

        return [
            'id' => (int) $entry['id'],
            'entry_type' => (string) ($entry['entry_type'] ?? 'ENTRY'),
            'entry_sequence' => (int) ($entry['entry_sequence'] ?? 0),
            'cash_remitted' => round((float) ($entry['cash_remitted'] ?? 0), 2),
            'gcash_remitted' => round((float) ($entry['gcash_remitted'] ?? 0), 2),
            'gcash_reference' => (string) ($entry['gcash_reference'] ?? ''),
            'total_remitted' => round((float) ($entry['total_remitted'] ?? 0), 2),
            'notes' => (string) ($entry['notes'] ?? ''),
            'created_at' => (string) ($entry['created_at'] ?? ''),
            'remittance_account' => [
                'id' => ! empty($entry['remittance_account_id']) ? (int) $entry['remittance_account_id'] : null,
                'name' => (string) ($entry['remittance_account_name'] ?? ''),
                'number' => (string) ($entry['remittance_account_number'] ?? ''),
            ],
            'denominations' => $denoms,
        ];
    }

    private function formatRemittanceAccountLabel(array $row): string
    {
        $name = trim((string) ($row['remittance_account_name'] ?? ''));
        $number = trim((string) ($row['remittance_account_number'] ?? ''));

        if ($name === '' && $number === '') {
            return '';
        }

        return $number !== '' ? $name . ' (' . $number . ')' : $name;
    }

    private function findShortageOutstandingBalance(int $remittanceId, float $varianceAmount): ?float
    {
        $paidTotal = (new ShortagePaymentModel())
            ->selectSum('amount')
            ->where('remittance_id', $remittanceId)
            ->first();

        if (! is_array($paidTotal)) {
            return null;
        }

        $paid = round((float) ($paidTotal['amount'] ?? 0), 2);

        return max(0.0, round($varianceAmount - $paid, 2));
    }

    private function getCutoffWindow(string $payrollMonth, string $cutoffPeriod): array
    {
        $monthStart = $payrollMonth . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $cutoff = strtoupper($cutoffPeriod) === 'SECOND' ? 'SECOND' : 'FIRST';

        if ($cutoff === 'FIRST') {
            return [$monthStart, $payrollMonth . '-15'];
        }

        return [$payrollMonth . '-16', $monthEnd];
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
}

