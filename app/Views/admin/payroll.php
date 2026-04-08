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

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card stat-card border-dark">
            <div class="card-body">
                <div class="stat-label">Total Payables Outstanding</div>
                <div class="stat-value">PHP <?= number_format((float) ($payablesSummary['total_outstanding'] ?? 0), 2) ?></div>
                <div class="text-muted">Unbatched payables plus generated and released payroll not yet rider-confirmed.</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Unbatched Payables</div>
                <div class="stat-value">PHP <?= number_format((float) ($payablesSummary['unbatched'] ?? 0), 2) ?></div>
                <div class="text-muted">Unlocked delivery earnings and adjustments not yet in a payroll run.</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Generated Payrolls</div>
                <div class="stat-value"><?= (int) ($payoutSummary['GENERATED']['count'] ?? 0) ?></div>
                <div class="text-muted">PHP <?= number_format((float) ($payoutSummary['GENERATED']['net_total'] ?? 0), 2) ?> pending release</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Released Payrolls</div>
                <div class="stat-value"><?= (int) ($payoutSummary['RELEASED']['count'] ?? 0) ?></div>
                <div class="text-muted">PHP <?= number_format((float) ($payoutSummary['RELEASED']['net_total'] ?? 0), 2) ?> awaiting rider confirmation</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Received Payrolls</div>
                <div class="stat-value"><?= (int) ($payoutSummary['RECEIVED']['count'] ?? 0) ?></div>
                <div class="text-muted">PHP <?= number_format((float) ($payoutSummary['RECEIVED']['net_total'] ?? 0), 2) ?> confirmed by riders</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span>Unpaid Salary Summary By Cutoff</span>
                <span class="small text-muted">Owner view of salaries still waiting to be generated or released</span>
            </div>
            <div class="card-body border-bottom">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Payroll Month</label>
                        <input type="month" name="unpaid_month" class="form-control" value="<?= esc($selectedUnpaidMonth) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cutoff Period</label>
                        <select name="unpaid_cutoff" class="form-select">
                            <option value="FIRST" <?= $selectedUnpaidCutoff === 'FIRST' ? 'selected' : '' ?>>1 to 15</option>
                            <option value="SECOND" <?= $selectedUnpaidCutoff === 'SECOND' ? 'selected' : '' ?>>16 to Month-End</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-dark">Preview</button>
                    </div>
                    <div class="col-md-4 d-grid">
                        <a href="<?= site_url('/admin/payroll/unpaid-export/csv?' . http_build_query([
                            'unpaid_month' => $selectedUnpaidMonth,
                            'unpaid_cutoff' => $selectedUnpaidCutoff,
                        ])) ?>" class="btn btn-outline-dark">Download Unpaid Salary CSV</a>
                    </div>
                </form>
            </div>
            <div class="card-body bg-light border-bottom">
                <div class="row g-3">
                    <div class="col-md-3"><div class="stat-label">Coverage</div><div class="fw-semibold"><?= esc($unpaidSummary['start_date']) ?> to <?= esc($unpaidSummary['end_date']) ?></div></div>
                    <div class="col-md-2"><div class="stat-label">Riders</div><div class="fw-semibold"><?= (int) ($unpaidSummary['summary']['rider_count'] ?? 0) ?></div></div>
                    <div class="col-md-2"><div class="stat-label">Gross</div><div class="fw-semibold">PHP <?= number_format((float) ($unpaidSummary['summary']['gross_total'] ?? 0), 2) ?></div></div>
                    <div class="col-md-2"><div class="stat-label">Shortages</div><div class="fw-semibold">PHP <?= number_format((float) ($unpaidSummary['summary']['shortage_total'] ?? 0), 2) ?></div></div>
                    <div class="col-md-3"><div class="stat-label">Net Payables</div><div class="fw-semibold">PHP <?= number_format((float) ($unpaidSummary['summary']['net_total'] ?? 0), 2) ?></div></div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Rider</th>
                            <th>Successful</th>
                            <th>Gross</th>
                            <th>Bonus</th>
                            <th>Deduction</th>
                            <th>Shortage</th>
                            <th>Repayments</th>
                            <th>Net Pay</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unpaidSummary['rows'] as $row): ?>
                            <tr>
                                <td><?= esc($row['rider_code']) ?> - <?= esc($row['name']) ?></td>
                                <td><?= (int) ($row['total_successful'] ?? 0) ?></td>
                                <td>PHP <?= number_format((float) ($row['gross_earnings'] ?? 0), 2) ?></td>
                                <td>PHP <?= number_format((float) ($row['bonus_total'] ?? 0), 2) ?></td>
                                <td>PHP <?= number_format((float) ($row['deduction_total'] ?? 0), 2) ?></td>
                                <td>PHP <?= number_format((float) ($row['shortage_deductions'] ?? 0), 2) ?></td>
                                <td>PHP <?= number_format((float) ($row['shortage_payments_received'] ?? 0), 2) ?></td>
                                <td class="fw-semibold">PHP <?= number_format((float) ($row['net_pay'] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($unpaidSummary['rows'])): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No unpaid salary items were found for this cutoff.</td></tr>
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
            <div class="col-md-3">
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
            <div class="col-md-2">
                <label class="form-label">Payroll Month</label>
                <input type="month" name="payroll_month" class="form-control" value="<?= esc($selectedPayrollMonth) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Cutoff Period</label>
                <select name="cutoff_period" class="form-select">
                    <option value="">All cutoff periods</option>
                    <option value="FIRST" <?= $selectedCutoff === 'FIRST' ? 'selected' : '' ?>>1 to 15</option>
                    <option value="SECOND" <?= $selectedCutoff === 'SECOND' ? 'selected' : '' ?>>16 to Month-End</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Payout Status</label>
                <select name="payroll_status" class="form-select">
                    <option value="">All statuses</option>
                    <option value="GENERATED" <?= ($selectedPayrollStatus ?? '') === 'GENERATED' ? 'selected' : '' ?>>Generated</option>
                    <option value="RELEASED" <?= ($selectedPayrollStatus ?? '') === 'RELEASED' ? 'selected' : '' ?>>Released</option>
                    <option value="RECEIVED" <?= ($selectedPayrollStatus ?? '') === 'RECEIVED' ? 'selected' : '' ?>>Received</option>
                </select>
            </div>
            <div class="col-md-1 d-grid gap-2">
                <button class="btn btn-dark">Apply</button>
                <a href="<?= site_url('/admin/payroll') ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
            <div class="col-md-2 d-grid">
                <a href="<?= site_url('/admin/payroll/export/csv?' . http_build_query(array_filter([
                    'rider_id' => $selectedRiderId,
                    'payroll_month' => $selectedPayrollMonth,
                    'cutoff_period' => $selectedCutoff,
                    'payroll_status' => $selectedPayrollStatus ?? '',
                ], static fn ($value) => $value !== ''))) ?>" class="btn btn-outline-dark">Export CSV</a>
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
                            <th>Status</th>
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
                            <?php $status = (string) ($item['payroll_status'] ?? 'GENERATED'); ?>
                            <tr>
                                <td><?= esc($item['start_date'] ?? $item['month_year']) ?> to <?= esc($item['end_date'] ?? $item['month_year']) ?></td>
                                <td><?= esc($item['rider_code']) ?> - <?= esc($item['name']) ?></td>
                                <td>
                                    <span class="badge <?= $status === 'RECEIVED' ? 'badge-over' : ($status === 'RELEASED' ? 'badge-balanced' : 'badge-short') ?>">
                                        <?= esc($status) ?>
                                    </span>
                                    <?php if (! empty($item['released_at'])): ?>
                                        <div class="small text-muted mt-1">Released <?= esc($item['released_at']) ?></div>
                                    <?php endif; ?>
                                    <?php if (! empty($item['received_at'])): ?>
                                        <div class="small text-muted">Received <?= esc($item['received_at']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $item['total_successful'] ?></td>
                                <td>PHP <?= number_format((float) ($item['bonus_total'] ?? 0), 2) ?></td>
                                <td>PHP <?= number_format((float) ($item['deduction_total'] ?? 0), 2) ?></td>
                                <td>PHP <?= number_format((float) ($item['shortage_deductions'] ?? 0), 2) ?></td>
                                <td>PHP <?= number_format((float) ($item['shortage_payments_received'] ?? 0), 2) ?></td>
                                <td>PHP <?= number_format((float) $item['net_pay'], 2) ?></td>
                                <td>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="<?= site_url('/admin/payroll/' . (int) $item['id'] . '/pdf') ?>" target="_blank" class="btn btn-sm btn-outline-dark">Payslip</a>
                                        <?php if ($status !== 'RECEIVED'): ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#releasePayrollModal"
                                                data-payroll-id="<?= (int) $item['id'] ?>"
                                                data-rider-label="<?= esc($item['rider_code'] . ' - ' . $item['name'], 'attr') ?>"
                                                data-payroll-range="<?= esc(($item['start_date'] ?? $item['month_year']) . ' to ' . ($item['end_date'] ?? $item['month_year']), 'attr') ?>"
                                                data-payout-method="<?= esc((string) ($item['payout_method'] ?? ''), 'attr') ?>"
                                                data-payout-reference="<?= esc((string) ($item['payout_reference'] ?? ''), 'attr') ?>"
                                            >
                                                <?= $status === 'RELEASED' ? 'Update Release' : 'Release Salary' ?>
                                            </button>
                                        <?php endif; ?>
                                        <form method="post" action="<?= site_url('/admin/payroll/' . (int) $item['id'] . '/reopen') ?>" onsubmit="return confirm('Reopen this payroll? This will release the locked delivery days, adjustments, and shortage payments so the payroll can be regenerated.');">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-danger">Reopen</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($payrolls)): ?>
                            <tr><td colspan="10" class="text-center text-muted py-4">No payroll runs matched the current filter.</td></tr>
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

<div class="modal fade" id="releasePayrollModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Release Salary</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-3" data-release-summary>Select a payroll run to release.</div>
                <form method="post" action="<?= site_url('/admin/payroll/0/release') ?>" id="releasePayrollForm">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Payout Method</label>
                        <select name="payout_method" class="form-select" required>
                            <option value="">Select payout method</option>
                            <option value="CASH">Cash</option>
                            <option value="BANK_TRANSFER">Bank Transfer</option>
                            <option value="E_WALLET">E-Wallet</option>
                            <option value="OTHER">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference</label>
                        <input type="text" name="payout_reference" class="form-control" maxlength="100" placeholder="Optional release note, transfer ref, or voucher number">
                    </div>
                    <button class="btn btn-dark w-100">Save Release Status</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const releaseModal = document.getElementById('releasePayrollModal');
    if (!releaseModal) {
        return;
    }

    const form = document.getElementById('releasePayrollForm');
    const summary = releaseModal.querySelector('[data-release-summary]');
    const methodSelect = form.querySelector('[name="payout_method"]');
    const referenceInput = form.querySelector('[name="payout_reference"]');

    releaseModal.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        if (!trigger) {
            return;
        }

        const payrollId = trigger.getAttribute('data-payroll-id') || '0';
        const riderLabel = trigger.getAttribute('data-rider-label') || 'Unknown rider';
        const payrollRange = trigger.getAttribute('data-payroll-range') || '';
        const payoutMethod = trigger.getAttribute('data-payout-method') || '';
        const payoutReference = trigger.getAttribute('data-payout-reference') || '';

        form.action = '<?= site_url('/admin/payroll') ?>/' + payrollId + '/release';
        summary.textContent = riderLabel + ' | ' + payrollRange;
        methodSelect.value = payoutMethod;
        referenceInput.value = payoutReference;
    });
});
</script>
<?= $this->endSection() ?>





