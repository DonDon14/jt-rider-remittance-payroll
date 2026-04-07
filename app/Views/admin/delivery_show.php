<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$actionLabels = [
    'RIDER_SUBMISSION_CREATED' => 'Rider submitted a delivery request',
    'RIDER_SUBMISSION_UPDATED' => 'Rider updated a delivery request',
    'SUBMISSION_APPROVED' => 'Admin approved the rider request',
    'SUBMISSION_REJECTED' => 'Admin rejected the rider request',
    'DELIVERY_CREATED_FROM_SUBMISSION' => 'Official delivery record created from rider request',
    'DELIVERY_UPDATED_FROM_SUBMISSION' => 'Official delivery record updated from rider request',
    'ADMIN_MANUAL_CREATE' => 'Admin manually created the delivery record',
    'ADMIN_MANUAL_UPDATE' => 'Admin manually updated the delivery record',
    'CORRECTION_REQUEST_CREATED' => 'Correction request was submitted',
    'CORRECTION_REQUEST_APPLIED' => 'Correction request was applied',
    'CORRECTION_REQUEST_REJECTED' => 'Correction request was rejected',
];

$actorLabels = [
    'admin' => 'Admin',
    'rider' => 'Rider',
    'system' => 'System',
    '' => 'System',
];

$detailLabels = [
    'delivery_date' => 'Delivery Date',
    'allocated_parcels' => 'Allocated Parcels',
    'successful_deliveries' => 'Successful Deliveries',
    'failed_deliveries' => 'Failed Deliveries',
    'expected_remittance' => 'Expected Remittance',
    'commission_rate' => 'Commission Used',
    'total_due' => 'Salary Earning',
    'entry_source' => 'Source',
    'notes' => 'Notes',
    'remittance_account' => 'Remittance Account',
];

$formatAuditValue = static function (string $key, $value): string {
    if ($value === null || $value === '') {
        return '-';
    }

    if (in_array($key, ['expected_remittance', 'commission_rate', 'total_due'], true)) {
        return 'PHP ' . number_format((float) $value, 2);
    }

    if ($key === 'entry_source') {
        return $value === 'RIDER_SUBMISSION' ? 'Rider Submission' : 'Admin Manual Entry';
    }

    return (string) $value;
};
?>

<div class="page-hero">
    <div>
        <h2 class="mb-0">Delivery Record Detail</h2>
        <p>Review the rider-day record, cash remittance result, payroll assignment, and any correction workflow for this day.</p>
    </div>
    <a href="<?= site_url('/admin/history') ?>" class="btn btn-outline-secondary">Back to History</a>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Delivery Details</div>
            <div class="card-body">
                <div><strong>Rider:</strong> <?= esc($record['rider_code']) ?> - <?= esc($record['name']) ?></div>
                <div><strong>Contact:</strong> <?= esc($record['contact_number'] ?: '-') ?></div>
                <div><strong>Date:</strong> <?= esc($record['delivery_date']) ?></div>
                <div><strong>Allocated:</strong> <?= (int) $record['allocated_parcels'] ?></div>
                <div><strong>Successful:</strong> <?= (int) $record['successful_deliveries'] ?></div>
                <div><strong>Failed:</strong> <?= (int) $record['failed_deliveries'] ?></div>
                <div><strong>Commission Rate Applied:</strong> PHP <?= number_format((float) ($record['applied_commission_rate'] ?? 0), 2) ?></div>
                <div><strong>Salary Earning:</strong> PHP <?= number_format((float) $record['total_due'], 2) ?></div>
                <div><strong>Expected Remittance:</strong> PHP <?= number_format((float) ($record['expected_remittance'] ?? 0), 2) ?></div>
                <div><strong>Remittance Account:</strong> <?= esc(trim((string) ($record['remittance_account_name'] ?? '')) !== '' ? (($record['remittance_account_name'] ?? '') . (! empty($record['remittance_account_number']) ? ' (' . $record['remittance_account_number'] . ')' : '')) : '-') ?></div>
                <div><strong>Notes:</strong> <?= esc($record['notes'] ?: '-') ?></div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header fw-semibold">Record Provenance</div>
            <div class="card-body">
                <div><strong>Source:</strong> <?= esc(($record['entry_source'] ?? 'ADMIN_MANUAL') === 'RIDER_SUBMISSION' ? 'Rider Submission' : 'Admin Manual Entry') ?></div>
                <div><strong>Source Submission ID:</strong> <?= ! empty($record['source_submission_id']) ? (int) $record['source_submission_id'] : '-' ?></div>
                <div><strong>Submission Status:</strong> <?= esc($record['submission_status'] ?? '-') ?></div>
                <div><strong>Last Admin Reason:</strong> <?= esc($record['last_admin_reason'] ?: '-') ?></div>
                <div><strong>Submission Account:</strong> <?= esc(trim((string) ($record['submission_remittance_account_name'] ?? '')) !== '' ? (($record['submission_remittance_account_name'] ?? '') . (! empty($record['submission_remittance_account_number']) ? ' (' . $record['submission_remittance_account_number'] . ')' : '')) : '-') ?></div>
                <?php if (! empty($record['submission_notes'])): ?>
                    <div class="mt-2"><strong>Submission Notes:</strong><br><?= nl2br(esc($record['submission_notes'])) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header fw-semibold">Correction Workflow</div>
            <div class="card-body">
                <p class="text-muted mb-3">Use this instead of silently editing a delivery day. Requested corrections are logged and must be explicitly applied.</p>
                <?php if (! empty($record['payroll_id'])): ?>
                    <div class="alert alert-warning mb-3">This delivery day is already locked into payroll. You can still file a correction request, but it cannot be applied until the payroll batch is reopened.</div>
                <?php endif; ?>
                <form method="post" action="<?= site_url('/admin/deliveries/' . (int) $record['id'] . '/corrections') ?>">
                    <?= csrf_field() ?>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Allocated Parcels</label>
                            <input type="number" min="0" name="allocated_parcels" class="form-control" value="<?= esc(old('allocated_parcels', (string) $record['allocated_parcels'])) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Successful Deliveries</label>
                            <input type="number" min="0" name="successful_deliveries" class="form-control" value="<?= esc(old('successful_deliveries', (string) $record['successful_deliveries'])) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Commission Used (PHP)</label>
                            <input type="number" step="0.01" min="0.01" name="commission_rate" class="form-control" value="<?= esc(old('commission_rate', number_format((float) ($record['commission_rate'] ?? 0), 2, '.', ''))) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expected Remittance (PHP)</label>
                            <input type="number" step="0.01" min="0" name="expected_remittance" class="form-control" value="<?= esc(old('expected_remittance', number_format((float) ($record['expected_remittance'] ?? 0), 2, '.', ''))) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notes</label>
                            <input type="text" name="notes" class="form-control" maxlength="1000" value="<?= esc(old('notes', (string) ($record['notes'] ?? ''))) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Correction Reason</label>
                            <textarea name="correction_reason" class="form-control" rows="3" maxlength="1000" required><?= esc(old('correction_reason')) ?></textarea>
                            <div class="form-text">State why the current record is wrong and what needs to be corrected.</div>
                        </div>
                    </div>
                    <button class="btn btn-dark mt-3">Submit Correction Request</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Remittance Status</div>
            <div class="card-body">
                <?php if ($remittance): ?>
                    <div><strong>Status:</strong> <?= esc($remittance['variance_type']) ?></div>
                    <div><strong>Expected Remittance:</strong> PHP <?= number_format((float) ($remittance['supposed_remittance'] ?? ($record['expected_remittance'] ?? 0)), 2) ?></div>
                    <div><strong>Actual Remitted:</strong> <?= $remittance['actual_remitted'] !== null ? 'PHP ' . number_format((float) $remittance['actual_remitted'], 2) : 'PHP ' . number_format((float) $remittance['total_remitted'], 2) ?></div>
                    <div><strong>Variance:</strong> PHP <?= number_format((float) $remittance['variance_amount'], 2) ?></div>
                    <div><strong>Remittance Account:</strong> <?= esc(trim((string) ($record['remittance_account_name'] ?? '')) !== '' ? (($record['remittance_account_name'] ?? '') . (! empty($record['remittance_account_number']) ? ' (' . $record['remittance_account_number'] . ')' : '')) : '-') ?></div>
                    <div class="mt-3"><a href="<?= site_url('/admin/remittance/' . (int) $record['id']) ?>" class="btn btn-sm btn-outline-primary">Open Remittance</a></div>
                <?php else: ?>
                    <div class="text-muted">No remittance record yet.</div>
                    <div class="mt-3"><a href="<?= site_url('/admin/remittance/' . (int) $record['id']) ?>" class="btn btn-sm btn-primary">Collect Remittance</a></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header fw-semibold">Payroll Assignment</div>
            <div class="card-body">
                <?php if ($payroll): ?>
                    <div><strong>Coverage:</strong> <?= esc($payroll['start_date'] ?? $payroll['month_year']) ?> to <?= esc($payroll['end_date'] ?? $payroll['month_year']) ?></div>
                    <div><strong>Net Pay Snapshot:</strong> PHP <?= number_format((float) $payroll['net_pay'], 2) ?></div>
                    <div class="mt-3"><a href="<?= site_url('/admin/payroll/' . (int) $payroll['id'] . '/pdf') ?>" target="_blank" class="btn btn-sm btn-outline-dark">Open Payslip</a></div>
                <?php else: ?>
                    <div class="text-muted">This delivery day has not yet been assigned to a payroll batch.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header fw-semibold">Correction Requests</div>
            <div class="card-body">
                <?php if (! empty($correctionRequests)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($correctionRequests as $request): ?>
                            <?php $requested = json_decode((string) ($request['requested_payload_json'] ?? ''), true) ?: []; ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                    <div>
                                        <div class="fw-semibold">Request #<?= (int) $request['id'] ?></div>
                                        <div class="text-muted small"><?= esc($request['created_at'] ?? '-') ?></div>
                                    </div>
                                    <span class="badge text-bg-<?= ($request['status'] ?? 'PENDING') === 'APPLIED' ? 'success' : (($request['status'] ?? 'PENDING') === 'REJECTED' ? 'danger' : 'warning') ?>">
                                        <?= esc($request['status'] ?? 'PENDING') ?>
                                    </span>
                                </div>
                                <div><strong>Reason:</strong> <?= esc($request['reason']) ?></div>
                                <?php if ($requested !== []): ?>
                                    <div class="small text-muted mt-1">
                                        Requested changes:
                                        <?php foreach ($requested as $key => $value): ?>
                                            <?php if (isset($detailLabels[$key])): ?>
                                                <div><?= esc($detailLabels[$key]) ?>: <?= esc($formatAuditValue($key, $value)) ?></div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (! empty($request['resolution_note'])): ?>
                                    <div class="mt-1"><strong>Resolution:</strong> <?= esc($request['resolution_note']) ?></div>
                                <?php endif; ?>
                                <?php if (($request['status'] ?? '') === 'PENDING'): ?>
                                    <div class="d-flex gap-2 mt-3">
                                        <form method="post" action="<?= site_url('/admin/delivery-corrections/' . (int) $request['id'] . '/apply') ?>">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-dark" onclick="return confirm('Apply this correction request to the delivery record?');">Apply</button>
                                        </form>
                                        <form method="post" action="<?= site_url('/admin/delivery-corrections/' . (int) $request['id'] . '/reject') ?>" class="flex-grow-1">
                                            <?= csrf_field() ?>
                                            <div class="input-group input-group-sm">
                                                <input type="text" name="resolution_note" class="form-control" placeholder="Optional rejection note">
                                                <button class="btn btn-outline-danger" onclick="return confirm('Reject this correction request?');">Reject</button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-muted">No correction requests for this delivery record.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header fw-semibold">Audit Trail</div>
            <div class="card-body">
                <?php if (! empty($auditLogs)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($auditLogs as $log): ?>
                            <?php $details = json_decode((string) ($log['details_json'] ?? ''), true) ?: []; ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <div class="fw-semibold"><?= esc($actionLabels[$log['action']] ?? ucwords(strtolower(str_replace('_', ' ', (string) $log['action'])))) ?></div>
                                        <div class="text-muted small"><?= esc($actorLabels[$log['actor_role'] ?? ''] ?? ucfirst((string) ($log['actor_role'] ?? 'System'))) ?></div>
                                        <div><?= esc($log['notes'] ?: '-') ?></div>
                                        <?php if ($details !== []): ?>
                                            <div class="small text-muted mt-1">
                                                <?php foreach ($details as $key => $value): ?>
                                                    <?php if (isset($detailLabels[$key])): ?>
                                                        <div><?= esc($detailLabels[$key]) ?>: <?= esc($formatAuditValue($key, $value)) ?></div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted small text-nowrap"><?= esc($log['created_at'] ?: '-') ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-muted">No audit trail yet for this delivery record.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

