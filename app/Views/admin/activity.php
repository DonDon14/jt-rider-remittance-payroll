<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$actionLabels = [
    'RIDER_SUBMISSION_CREATED' => 'Rider submitted a delivery record',
    'RIDER_SUBMISSION_UPDATED' => 'Rider updated a pending delivery submission',
    'ADMIN_MANUAL_CREATE' => 'Admin manually created a delivery record',
    'ADMIN_MANUAL_UPDATE' => 'Admin manually updated a delivery record',
    'DELIVERY_CREATED_FROM_SUBMISSION' => 'Admin approved a rider submission and created a delivery record',
    'DELIVERY_UPDATED_FROM_SUBMISSION' => 'Admin approved a rider submission and updated a delivery record',
    'SUBMISSION_APPROVED' => 'Admin approved a rider submission',
    'SUBMISSION_REJECTED' => 'Admin rejected a rider submission',
    'CORRECTION_REQUEST_CREATED' => 'A correction request was filed',
    'CORRECTION_REQUEST_APPLIED' => 'A correction request was applied',
    'CORRECTION_REQUEST_REJECTED' => 'A correction request was rejected',
];
?>

<div class="page-hero">
    <div>
        <h2 class="mb-0">Activity Log</h2>
        <p>Review branch-wide delivery workflow activity. This log currently covers submissions, approvals, manual delivery entry, and corrections.</p>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= site_url('/admin/activity') ?>" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Search</label>
                <input type="text" name="q" value="<?= esc($search ?? '') ?>" class="form-control" placeholder="Rider, actor, action, or note">
            </div>
            <div class="col-md-2">
                <label class="form-label">Actor Role</label>
                <select name="role" class="form-select">
                    <option value="">All roles</option>
                    <option value="admin" <?= ($role ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="rider" <?= ($role ?? '') === 'rider' ? 'selected' : '' ?>>Rider</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" value="<?= esc($startDate ?? '') ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" value="<?= esc($endDate ?? '') ?>" class="form-control">
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100">Go</button>
            </div>
            <div class="col-md-1">
                <a href="<?= site_url('/admin/activity') ?>" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Event</th>
                    <th>Rider</th>
                    <th>Actor</th>
                    <th>Notes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= esc($row['created_at'] ?? '-') ?></td>
                        <td><?= esc($actionLabels[$row['action'] ?? ''] ?? ucwords(strtolower(str_replace('_', ' ', (string) ($row['action'] ?? 'Activity'))))) ?></td>
                        <td><?= ! empty($row['rider_code']) ? esc($row['rider_code']) . ' - ' . esc($row['name']) : '-' ?></td>
                        <td>
                            <?php if (($row['actor_role'] ?? '') === 'admin'): ?>
                                Admin<?= ! empty($row['username']) ? ': ' . esc($row['username']) : '' ?>
                            <?php elseif (($row['actor_role'] ?? '') === 'rider'): ?>
                                Rider<?= ! empty($row['username']) ? ': ' . esc($row['username']) : '' ?>
                            <?php else: ?>
                                System
                            <?php endif; ?>
                        </td>
                        <td><?= esc($row['notes'] ?: '-') ?></td>
                        <td>
                            <?php if (! empty($row['delivery_record_id'])): ?>
                                <a href="<?= site_url('/admin/deliveries/' . (int) $row['delivery_record_id']) ?>" class="btn btn-sm btn-outline-dark">Open Record</a>
                            <?php elseif (! empty($row['delivery_submission_id'])): ?>
                                <a href="<?= site_url('/admin/remittances') ?>" class="btn btn-sm btn-outline-dark">Open Queue</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No activity matched the current filter.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (! empty($pager)): ?>
    <div class="mt-3">
        <?= $pager->links($pageGroup) ?>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
