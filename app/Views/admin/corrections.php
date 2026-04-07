<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-hero">
    <div>
        <h2 class="mb-0">Correction Requests</h2>
        <p>Review branch-wide delivery correction requests and resolve them from one queue.</p>
    </div>
    <a href="<?= site_url('/admin/corrections/export/csv?' . http_build_query(array_filter([
        'q' => $search,
        'status' => $status,
        'start_date' => $startDate,
        'end_date' => $endDate,
    ], static fn ($value) => $value !== '' && $value !== null))) ?>" class="btn btn-outline-dark">Export CSV</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= site_url('/admin/corrections') ?>" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="q" value="<?= esc($search) ?>" class="form-control" placeholder="Rider code, rider name, or reason">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    <option value="PENDING" <?= $status === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                    <option value="APPLIED" <?= $status === 'APPLIED' ? 'selected' : '' ?>>Applied</option>
                    <option value="REJECTED" <?= $status === 'REJECTED' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" value="<?= esc($startDate) ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" value="<?= esc($endDate) ?>" class="form-control">
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100">Go</button>
            </div>
            <div class="col-md-1">
                <a href="<?= site_url('/admin/corrections') ?>" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card stat-card"><div class="card-body"><div class="stat-label">Pending</div><div class="stat-value"><?= (int) $summary['pending'] ?></div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body"><div class="stat-label">Applied</div><div class="stat-value"><?= (int) $summary['applied'] ?></div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body"><div class="stat-label">Rejected</div><div class="stat-value"><?= (int) $summary['rejected'] ?></div></div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Requested</th>
                    <th>Delivery Date</th>
                    <th>Rider</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Payroll Lock</th>
                    <th>Requested Changes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $requested = json_decode((string) ($row['requested_payload_json'] ?? ''), true) ?: []; ?>
                    <tr>
                        <td><?= esc($row['created_at'] ?? '-') ?></td>
                        <td><?= esc($row['delivery_date']) ?></td>
                        <td><?= esc($row['rider_code']) ?> - <?= esc($row['name']) ?></td>
                        <td><?= esc($row['reason']) ?></td>
                        <td>
                            <span class="badge text-bg-<?= ($row['status'] ?? 'PENDING') === 'APPLIED' ? 'success' : (($row['status'] ?? 'PENDING') === 'REJECTED' ? 'danger' : 'warning') ?>">
                                <?= esc($row['status'] ?? 'PENDING') ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge text-bg-<?= ! empty($row['payroll_id']) ? 'danger' : 'secondary' ?>">
                                <?= ! empty($row['payroll_id']) ? 'Locked' : 'Open' ?>
                            </span>
                        </td>
                        <td class="small text-muted">
                            <?php if ($requested !== []): ?>
                                <?php if (isset($requested['successful_deliveries'])): ?>Delivered: <?= (int) $requested['successful_deliveries'] ?><br><?php endif; ?>
                                <?php if (isset($requested['commission_rate'])): ?>Commission: PHP <?= number_format((float) $requested['commission_rate'], 2) ?><br><?php endif; ?>
                                <?php if (isset($requested['expected_remittance'])): ?>Expected: PHP <?= number_format((float) $requested['expected_remittance'], 2) ?><?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-2 align-items-start flex-wrap">
                                <?php if (($row['status'] ?? '') === 'PENDING'): ?>
                                    <form method="post" action="<?= site_url('/admin/delivery-corrections/' . (int) $row['id'] . '/apply') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_to" value="<?= current_url() . (! empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '') ?>">
                                        <input type="hidden" name="delivery_record_id" value="<?= (int) $row['delivery_record_id'] ?>">
                                        <button class="btn btn-sm btn-dark" <?= ! empty($row['payroll_id']) ? 'disabled title="Reopen payroll first."' : '' ?> onclick="return confirm('Apply this correction request?');">Apply</button>
                                    </form>
                                    <form method="post" action="<?= site_url('/admin/delivery-corrections/' . (int) $row['id'] . '/reject') ?>" class="d-flex gap-2 align-items-start flex-wrap">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_to" value="<?= current_url() . (! empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '') ?>">
                                        <input type="hidden" name="delivery_record_id" value="<?= (int) $row['delivery_record_id'] ?>">
                                        <input type="text" name="resolution_note" class="form-control form-control-sm" placeholder="Optional note">
                                        <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject this correction request?');">Reject</button>
                                    </form>
                                <?php endif; ?>
                                <a href="<?= site_url('/admin/deliveries/' . (int) $row['delivery_record_id']) ?>" class="btn btn-sm btn-outline-dark">Open Record</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No correction requests matched the filters.</td></tr>
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
