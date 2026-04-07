<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-hero">
    <div>
        <h2 class="mb-0">Payroll Adjustments</h2>
        <p>Add bonuses or deductions to a single rider or to all riders. These records lock into payroll once included.</p>
    </div>
    <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#adjustmentModal">New Adjustment</button>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= site_url('/admin/adjustments') ?>" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="q" value="<?= esc($search ?? '') ?>" class="form-control" placeholder="Rider, description, or batch reference">
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">All types</option>
                    <option value="BONUS" <?= ($type ?? '') === 'BONUS' ? 'selected' : '' ?>>Bonus</option>
                    <option value="DEDUCTION" <?= ($type ?? '') === 'DEDUCTION' ? 'selected' : '' ?>>Deduction</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    <option value="UNPAID" <?= ($status ?? '') === 'UNPAID' ? 'selected' : '' ?>>Unpaid</option>
                    <option value="LOCKED" <?= ($status ?? '') === 'LOCKED' ? 'selected' : '' ?>>Locked to Payroll</option>
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
                <a href="<?= site_url('/admin/adjustments') ?>" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header fw-semibold">Adjustment History</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Rider</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Description</th>
                    <th>Batch</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adjustments as $adjustment): ?>
                    <tr>
                        <td><?= esc($adjustment['adjustment_date']) ?></td>
                        <td><?= esc($adjustment['rider_code']) ?> - <?= esc($adjustment['name']) ?></td>
                        <td><?= esc($adjustment['type']) ?></td>
                        <td>PHP <?= number_format((float) $adjustment['amount'], 2) ?></td>
                        <td><?= esc($adjustment['description']) ?></td>
                        <td><?= esc($adjustment['batch_reference'] ?: '-') ?></td>
                        <td><span class="badge <?= empty($adjustment['payroll_id']) ? 'text-bg-warning text-dark' : 'text-bg-success' ?>"><?= empty($adjustment['payroll_id']) ? 'UNPAID' : 'LOCKED TO PAYROLL' ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($adjustments)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No adjustments matched the current filter.</td></tr>
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

<div class="modal fade" id="adjustmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Adjustment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?= site_url('/admin/adjustments') ?>" data-adjustment-form>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label d-block">Apply To</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="adjustment_scope" id="adjustmentScopeSingle" value="SINGLE" checked data-adjustment-scope>
                            <label class="form-check-label" for="adjustmentScopeSingle">Single rider</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="adjustment_scope" id="adjustmentScopeAll" value="ALL" data-adjustment-scope>
                            <label class="form-check-label" for="adjustmentScopeAll">All riders</label>
                        </div>
                    </div>
                    <div class="mb-3" data-adjustment-rider-group>
                        <label class="form-label">Rider</label>
                        <div class="searchable-select" data-searchable-select>
                            <input type="text" class="form-control mb-2" placeholder="Search rider code or name" data-search-target>
                            <select name="rider_id" class="form-select" required data-search-source data-adjustment-rider-select>
                                <option value="">Select rider</option>
                                <?php foreach ($riders as $rider): ?>
                                    <option value="<?= (int) $rider['id'] ?>"><?= esc($rider['rider_code']) ?> - <?= esc($rider['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="adjustment_date" class="form-control" value="<?= esc($today) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            <option value="BONUS">Bonus</option>
                            <option value="DEDUCTION">Deduction</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" name="amount" step="0.01" min="0.01" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" maxlength="255" required>
                    </div>
                    <button class="btn btn-dark w-100">Save Adjustment</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
