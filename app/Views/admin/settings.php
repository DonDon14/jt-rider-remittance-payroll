<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-hero">
    <div>
        <h2 class="mb-0">Settings</h2>
        <p>Manage rider commission rates and the remittance accounts riders can select during the collection request process.</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold">Manual Backup</div>
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <p class="mb-1">Generate and download a full SQL backup on demand.</p>
                    <p class="text-muted mb-0">Use this at the end of the day if you do not want paid automated backups yet.</p>
                </div>
                <form method="post" action="<?= site_url('/admin/settings/backup') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-dark">Download SQL Backup</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header fw-semibold">Add Remittance Account</div>
            <div class="card-body">
                <form method="post" action="<?= site_url('/admin/settings/remittance-accounts') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Account Name</label>
                        <input type="text" name="account_name" class="form-control" maxlength="120" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Number / Reference</label>
                        <input type="text" name="account_number" class="form-control" maxlength="80">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" maxlength="255" placeholder="Optional branch note or account owner.">
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" min="0" class="form-control" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="is_active" class="form-select" required>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-dark w-100 mt-3">Save Remittance Account</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header fw-semibold">Remittance Accounts</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Details</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($remittanceAccounts as $account): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= esc($account['account_name']) ?></div>
                                        <div class="small text-muted">Sort: <?= (int) ($account['sort_order'] ?? 0) ?></div>
                                    </td>
                                    <td>
                                        <form method="post" action="<?= site_url('/admin/settings/remittance-accounts/' . (int) $account['id']) ?>" class="row g-2 align-items-end">
                                            <?= csrf_field() ?>
                                            <div class="col-md-6">
                                                <label class="form-label small">Account Number / Reference</label>
                                                <input type="text" name="account_number" class="form-control form-control-sm" value="<?= esc((string) ($account['account_number'] ?? '')) ?>" maxlength="80">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small">Account Name</label>
                                                <input type="text" name="account_name" class="form-control form-control-sm" value="<?= esc((string) ($account['account_name'] ?? '')) ?>" maxlength="120" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small">Description</label>
                                                <input type="text" name="description" class="form-control form-control-sm" value="<?= esc((string) ($account['description'] ?? '')) ?>" maxlength="255">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Sort</label>
                                                <input type="number" name="sort_order" min="0" class="form-control form-control-sm" value="<?= (int) ($account['sort_order'] ?? 0) ?>">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Status</label>
                                                <select name="is_active" class="form-select form-select-sm" required>
                                                    <option value="1" <?= ! empty($account['is_active']) ? 'selected' : '' ?>>Active</option>
                                                    <option value="0" <?= empty($account['is_active']) ? 'selected' : '' ?>>Inactive</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <button class="btn btn-outline-dark btn-sm w-100">Update</button>
                                            </div>
                                        </form>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-<?= ! empty($account['is_active']) ? 'success' : 'secondary' ?>">
                                            <?= ! empty($account['is_active']) ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($remittanceAccounts)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-4">No remittance accounts configured yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header fw-semibold">Update Commission Rate</div>
            <div class="card-body">
                <form method="post" action="<?= site_url('/admin/settings/commission') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Rider</label>
                        <div class="searchable-select" data-searchable-select>
                            <input type="text" class="form-control mb-2" placeholder="Search rider code or name" data-search-target>
                            <select name="rider_id" class="form-select" required data-search-source>
                                <option value="">Select rider</option>
                                <?php foreach ($riders as $rider): ?>
                                    <option value="<?= (int) $rider['id'] ?>"><?= esc($rider['rider_code']) ?> - <?= esc($rider['name']) ?> | Current: PHP <?= esc($rider['display_commission_rate']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Commission Rate (PHP)</label>
                        <input type="number" name="commission_rate" step="0.01" min="0.01" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Effective Date</label>
                        <input type="date" name="effective_date" value="<?= esc($today) ?>" class="form-control" required>
                        <div class="form-text">This rate will apply only to delivery records on this date and after.</div>
                    </div>
                    <button class="btn btn-dark w-100">Save Commission Change</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Current Rider Rates</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Rider</th>
                            <th>Current Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riders as $rider): ?>
                            <tr>
                                <td><?= esc($rider['rider_code']) ?> - <?= esc($rider['name']) ?></td>
                                <td>PHP <?= esc($rider['display_commission_rate']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header fw-semibold">Commission Change History</div>
            <div class="card-body border-bottom">
                <form method="get" action="<?= site_url('/admin/settings') ?>" class="row g-2 align-items-end">
                    <div class="col-md-10">
                        <label class="form-label">Search</label>
                        <input type="text" name="q" value="<?= esc($historySearch ?? '') ?>" class="form-control" placeholder="Search rider code or name">
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-primary w-100">Go</button>
                    </div>
                    <div class="col-md-1">
                        <a href="<?= site_url('/admin/settings') ?>" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Effective Date</th>
                            <th>Rider</th>
                            <th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rateHistory as $item): ?>
                            <tr>
                                <td><?= esc($item['effective_date']) ?></td>
                                <td><?= esc($item['rider_code']) ?> - <?= esc($item['name']) ?></td>
                                <td>PHP <?= number_format((float) $item['commission_rate'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rateHistory)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">No commission history matched the current filter.</td></tr>
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
    </div>
</div>

<?= $this->endSection() ?>

