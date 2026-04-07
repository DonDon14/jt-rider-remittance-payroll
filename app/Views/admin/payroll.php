<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-hero">
    <div>
        <h2 class="mb-0">Payroll Processing</h2>
        <p>Payroll follows the standard rider cutoffs: 1 to 15 and 16 to month-end. Generate by cutoff so delivery days, adjustments, and shortage repayments lock into one salary run.</p>
    </div>
    <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#payrollGenerateModal">Generate Payroll</button>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold">General Salary Summary By Cutoff</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Cutoff</th>
                            <th>Riders Paid</th>
                            <th>Gross Salaries</th>
                            <th>Bonuses</th>
                            <th>Deductions</th>
                            <th>Shortages</th>
                            <th>Repayments</th>
                            <th>Net Salaries</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cutoffSummaries as $summary): ?>
                            <tr>
                                <td><?= esc($summary['start_date']) ?> to <?= esc($summary['end_date']) ?></td>
                                <td><?= (int) $summary['rider_count'] ?></td>
                                <td>PHP <?= number_format((float) ($summary['gross_total'] ?? 0), 2) ?></td>
                                <td>PHP <?= number_format((float) ($summary['bonus_total'] ?? 0), 2) ?></td>
                                <td>PHP <?= number_format((float) ($summary['deduction_total'] ?? 0), 2) ?></td>
                                <td>PHP <?= number_format((float) ($summary['shortage_total'] ?? 0), 2) ?></td>
                                <td>PHP <?= number_format((float) ($summary['repayment_total'] ?? 0), 2) ?></td>
                                <td class="fw-semibold">PHP <?= number_format((float) ($summary['net_total'] ?? 0), 2) ?></td>
                                <td><a href="<?= site_url('/admin/payroll/summary/pdf?start_date=' . rawurlencode((string) $summary['start_date']) . '&end_date=' . rawurlencode((string) $summary['end_date'])) ?>" target="_blank" class="btn btn-sm btn-outline-dark">Download</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($cutoffSummaries)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">No payroll summaries yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header fw-semibold">Filter Payroll History</div>
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Rider</label>
                <div class="searchable-select" data-searchable-select>
                    <input type="text" class="form-control mb-2" placeholder="Search rider code or name" data-search-target>
                    <select name="rider_id" class="form-select" data-search-source>
                        <option value="">All riders</option>
                        <?php foreach ($riders as $rider): ?>
                            <option value="<?= (int) $rider['id'] ?>" <?= (string) $selectedRiderId === (string) $rider['id'] ? 'selected' : '' ?>><?= esc($rider['rider_code']) ?> - <?= esc($rider['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Payroll Month</label>
                <input type="month" name="payroll_month" class="form-control" value="<?= esc($selectedPayrollMonth) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Cutoff Period</label>
                <select name="cutoff_period" class="form-select">
                    <option value="">All cutoff periods</option>
                    <option value="FIRST" <?= $selectedCutoff === 'FIRST' ? 'selected' : '' ?>>1 to 15</option>
                    <option value="SECOND" <?= $selectedCutoff === 'SECOND' ? 'selected' : '' ?>>16 to Month-End</option>
                </select>
            </div>
            <div class="col-md-2 d-grid gap-2">
                <button class="btn btn-dark">Apply</button>
                <a href="<?= site_url('/admin/payroll') ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold">Payroll History</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date Range</th>
                            <th>Rider</th>
                            <th>Successful</th>
                            <th>Bonus</th>
                            <th>Deduction</th>
                            <th>Shortage</th>
                            <th>Repayments</th>
                            <th>Net Pay</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payrolls as $item): ?>
                            <tr>
                                <td><?= esc($item['start_date'] ?? $item['month_year']) ?> to <?= esc($item['end_date'] ?? $item['month_year']) ?></td>
                                <td><?= esc($item['rider_code']) ?> - <?= esc($item['name']) ?></td>
                                <td><?= (int) $item['total_successful'] ?></td>
                                <td>PHP <?= number_format((float) ($item['bonus_total'] ?? 0), 2) ?></td>
                                <td>PHP <?= number_format((float) ($item['deduction_total'] ?? 0), 2) ?></td>
                                <td>PHP <?= number_format((float) ($item['shortage_deductions'] ?? 0), 2) ?></td>
                                <td>PHP <?= number_format((float) ($item['shortage_payments_received'] ?? 0), 2) ?></td>
                                <td>PHP <?= number_format((float) $item['net_pay'], 2) ?></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="<?= site_url('/admin/payroll/' . (int) $item['id'] . '/pdf') ?>" target="_blank" class="btn btn-sm btn-outline-dark">Payslip</a>
                                        <form method="post" action="<?= site_url('/admin/payroll/' . (int) $item['id'] . '/reopen') ?>" onsubmit="return confirm('Reopen this payroll? This will release the locked delivery days, adjustments, and shortage payments so the payroll can be regenerated.');">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-danger">Reopen</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($payrolls)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">No payroll runs matched the current filter.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (! empty($pager)): ?>
    <div class="mt-3">
        <?= $pager->links($pageGroup) ?>
    </div>
<?php endif; ?>

<div class="modal fade" id="payrollGenerateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Payroll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?= site_url('/admin/payroll/generate') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Rider</label>
                        <div class="searchable-select" data-searchable-select>
                            <input type="text" class="form-control mb-2" placeholder="Search rider code or name" data-search-target>
                            <select name="rider_id" class="form-select" required data-search-source>
                                <option value="">Select rider</option>
                                <?php foreach ($riders as $rider): ?>
                                    <option value="<?= (int) $rider['id'] ?>"><?= esc($rider['rider_code']) ?> - <?= esc($rider['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payroll Month</label>
                        <input type="month" name="payroll_month" class="form-control" value="<?= esc($defaultPayrollMonth) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cutoff Period</label>
                        <select name="cutoff_period" class="form-select" required>
                            <option value="FIRST" <?= $defaultCutoff === 'FIRST' ? 'selected' : '' ?>>1 to 15</option>
                            <option value="SECOND" <?= $defaultCutoff === 'SECOND' ? 'selected' : '' ?>>16 to Month-End</option>
                        </select>
                    </div>
                    <button class="btn btn-dark w-100">Generate Payroll</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

