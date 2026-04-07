<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-hero">
    <div>
        <h2 class="mb-0">Delivery History</h2>
        <p>Search previous rider-day records and inspect both salary earning and cash remittance expectation.</p>
        <div class="text-muted small">Rows are sorted by latest activity first, so newly added or corrected records appear at the top.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= site_url('/admin/history/export/csv?' . http_build_query(array_filter(['q' => $search, 'start_date' => $startDate, 'end_date' => $endDate, 'rider_id' => $selectedRiderId, 'remittance_status' => $remittanceStatus], static fn ($value) => $value !== ''))) ?>" class="btn btn-outline-dark">Export CSV</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= site_url('/admin/history') ?>" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search Rider</label>
                <input type="text" name="q" value="<?= esc($search) ?>" class="form-control" placeholder="Rider code or name">
            </div>
            <div class="col-md-2">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" value="<?= esc($startDate) ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" value="<?= esc($endDate) ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Rider</label>
                <div class="searchable-select" data-searchable-select>
                    <input type="text" class="form-control mb-2" placeholder="Filter rider list" data-search-target>
                    <select name="rider_id" class="form-select" data-search-source>
                        <option value="">All riders</option>
                        <?php foreach ($riders as $rider): ?>
                            <option value="<?= (int) $rider['id'] ?>" <?= (string) $selectedRiderId === (string) $rider['id'] ? 'selected' : '' ?>><?= esc($rider['rider_code']) ?> - <?= esc($rider['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">Remittance Status</label>
                <select name="remittance_status" class="form-select">
                    <option value="">All statuses</option>
                    <option value="NOT_RECORDED" <?= $remittanceStatus === 'NOT_RECORDED' ? 'selected' : '' ?>>Not Recorded</option>
                    <option value="PENDING" <?= $remittanceStatus === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                    <option value="BALANCED" <?= $remittanceStatus === 'BALANCED' ? 'selected' : '' ?>>Balanced</option>
                    <option value="SHORT" <?= $remittanceStatus === 'SHORT' ? 'selected' : '' ?>>Short</option>
                    <option value="OVER" <?= $remittanceStatus === 'OVER' ? 'selected' : '' ?>>Over</option>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100">Go</button>
            </div>
            <div class="col-md-2">
                <a href="<?= site_url('/admin/history') ?>" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Matched Records</div><div class="stat-value"><?= (int) $historySummary['records'] ?></div></div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Successful Parcels</div><div class="stat-value"><?= (int) $historySummary['successful'] ?></div></div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Salary Earnings</div><div class="stat-value">PHP <?= number_format((float) $historySummary['salary_earnings'], 2) ?></div></div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Expected Remittance</div><div class="stat-value">PHP <?= number_format((float) $historySummary['expected_remittance'], 2) ?></div></div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Rider</th>
                    <th>Source</th>
                    <th>Successful</th>
                    <th>Salary Earning</th>
                    <th>Expected Remittance</th>
                    <th>Remittance</th>
                    <th>Last Activity</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?= esc($record['delivery_date']) ?></td>
                        <td><?= esc($record['rider_code']) ?> - <?= esc($record['name']) ?></td>
                        <td>
                            <span class="badge text-bg-<?= ($record['entry_source'] ?? 'ADMIN_MANUAL') === 'RIDER_SUBMISSION' ? 'info' : 'secondary' ?>">
                                <?= esc(($record['entry_source'] ?? 'ADMIN_MANUAL') === 'RIDER_SUBMISSION' ? 'Rider Submission' : 'Admin Manual') ?>
                            </span>
                        </td>
                        <td><?= (int) $record['successful_deliveries'] ?></td>
                        <td>PHP <?= number_format((float) $record['total_due'], 2) ?></td>
                        <td>PHP <?= number_format((float) ($record['expected_remittance'] ?? 0), 2) ?></td>
                        <td><?= esc($record['variance_type'] ?? 'NOT RECORDED') ?></td>
                        <td><?= esc($record['updated_at'] ?: $record['created_at'] ?: '-') ?></td>
                        <td><a href="<?= site_url('/admin/deliveries/' . (int) $record['id']) ?>" class="btn btn-sm btn-outline-dark">View / Correct</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($records)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No delivery records matched the filters.</td></tr>
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
