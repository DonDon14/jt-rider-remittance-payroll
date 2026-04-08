<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$formatAccountLabel = static function (array $row): string {
    $name = trim((string) ($row['remittance_account_name'] ?? $row['account_name'] ?? ''));
    $number = trim((string) ($row['remittance_account_number'] ?? $row['account_number'] ?? ''));

    if ($name === '') {
        return '-';
    }

    return $number !== '' ? $name . ' (' . $number . ')' : $name;
};
?>

<div class="page-hero">
    <div>
        <h2 class="mb-0">Rider Portal</h2>
        <p>Review delivery output, expected remittance totals, salary earnings, and shortage accountability.</p>
    </div>
    <div class="d-flex gap-2">
        <form method="get" class="d-flex gap-2">
            <input type="month" name="month" value="<?= esc($month) ?>" class="form-control">
            <button class="btn btn-primary">Apply</button>
        </form>
        <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#deliveryRequestModal">Submit Delivery Record</button>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h5 class="mb-1"><?= esc($rider['rider_code']) ?> - <?= esc($rider['name']) ?></h5>
            <div class="text-muted">Commission per successful parcel: PHP <?= number_format((float) $rider['commission_rate'], 2) ?></div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <div class="badge text-bg-light px-3 py-2">Month: <?= esc(date('F Y', strtotime($month . '-01'))) ?></div>
            <div class="badge text-bg-dark px-3 py-2">Current Payable Net: PHP <?= number_format((float) $stats['projected_net'], 2) ?></div>
        </div>
    </div>
</div>

<?php if (! empty($accountSecurity)): ?>
    <div class="alert alert-<?= esc($accountSecurity['tone'] ?? 'secondary') ?> mb-3 d-flex justify-content-between align-items-center">
        <div>
            <div class="fw-semibold">Account Security: <?= esc($accountSecurity['label'] ?? 'Status available') ?></div>
            <div class="small mb-0"><?= esc($accountSecurity['detail'] ?? '') ?></div>
        </div>
        <a href="<?= site_url('/change-password') ?>" class="btn btn-sm btn-outline-dark">Change Password</a>
    </div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3" id="riderPortalTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab">Overview</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="deliveries-tab" data-bs-toggle="tab" data-bs-target="#deliveries-pane" type="button" role="tab">Deliveries</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests-pane" type="button" role="tab">Requests</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="payroll-tab" data-bs-toggle="tab" data-bs-target="#payroll-pane" type="button" role="tab">Payroll</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="announcements-tab" data-bs-toggle="tab" data-bs-target="#announcements-pane" type="button" role="tab">Announcements</button>
    </li>
</ul>

<div class="tab-content" id="riderPortalTabsContent">
    <div class="tab-pane fade show active" id="overview-pane" role="tabpanel" aria-labelledby="overview-tab" tabindex="0">
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-md-2 text-center">
                        <?php if (! empty($rider['profile_photo_path'])): ?>
                            <img src="<?= esc(site_url((string) $rider['profile_photo_path'])) ?>" alt="<?= esc($rider['name']) ?>" style="width:96px;height:96px;border-radius:50%;object-fit:cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" style="width:96px;height:96px;font-size:36px;"><?= esc(strtoupper(substr((string) $rider['name'], 0, 1))) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-10">
                        <div class="row g-3">
                            <div class="col-md-4"><div class="small text-muted">Branch</div><div class="fw-semibold"><?= esc($rider['branch_name'] ?? '-') ?></div></div>
                            <div class="col-md-4"><div class="small text-muted">Address</div><div class="fw-semibold"><?= esc($rider['address'] ?? '-') ?></div></div>
                            <div class="col-md-4"><div class="small text-muted">Contact</div><div class="fw-semibold"><?= esc($rider['contact_number'] ?? '-') ?></div></div>
                            <div class="col-md-4"><div class="small text-muted">Emergency Contact</div><div class="fw-semibold"><?= esc(($rider['emergency_contact_name'] ?? '') !== '' ? (($rider['emergency_contact_name'] ?? '') . ' - ' . ($rider['emergency_contact_number'] ?? '-')) : '-') ?></div></div>
                            <div class="col-md-4"><div class="small text-muted">Government ID</div><div class="fw-semibold"><?= esc($rider['government_id_number'] ?? '-') ?></div></div>
                            <div class="col-md-4"><div class="small text-muted">Hire Date</div><div class="fw-semibold"><?= esc($rider['hire_date'] ?? '-') ?></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card salary-focus-card mb-3">
            <div class="card-body">
                <div class="stat-label">Current Payable For <?= esc($paydayPreview['label'] ?? date('F Y', strtotime($month . '-01'))) ?></div>
                <div class="salary-focus-value">PHP <?= number_format((float) $stats['current_payable'], 2) ?></div>
                <div class="text-muted">
                    Coverage: <?= esc($paydayPreview['start_date'] ?? $month . '-01') ?> to <?= esc($paydayPreview['effective_end_date'] ?? $month . '-01') ?>.
                    <?php if (! empty($paydayPreview['payout_date'])): ?>
                        Expected payout day: <?= esc($paydayPreview['payout_date']) ?>.
                    <?php endif; ?>
                    Paid or released salary stays in Payroll History.
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Allocated</div><div class="stat-value"><?= (int) $stats['allocated'] ?></div></div></div></div>
            <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Successful</div><div class="stat-value"><?= (int) $stats['successful'] ?></div></div></div></div>
            <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Total Earned This Month</div><div class="stat-value">PHP <?= number_format((float) $stats['month_earnings'], 2) ?></div></div></div></div>
            <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Already In Payroll</div><div class="stat-value">PHP <?= number_format((float) $stats['paid_earnings'], 2) ?></div></div></div></div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="card"><div class="card-body"><div class="stat-label">Expected Remittance</div><div class="stat-value">PHP <?= number_format((float) $stats['expected_remittance'], 2) ?></div></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><div class="stat-label">Total Remitted</div><div class="stat-value">PHP <?= number_format((float) $stats['total_remitted'], 2) ?></div></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><div class="stat-label">Current Shortage Deductions</div><div class="stat-value">PHP <?= number_format((float) $stats['shortage_deductions'], 2) ?></div></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><div class="stat-label">Current Repayments</div><div class="stat-value">PHP <?= number_format((float) $stats['shortage_repayments'], 2) ?></div></div></div></div>
        </div>

        <div class="row g-3">
            <div class="col-md-4"><div class="card"><div class="card-body"><div class="stat-label">Outstanding Shortage Balance</div><div class="stat-value">PHP <?= number_format((float) $stats['outstanding_shortage_balance'], 2) ?></div></div></div></div>
            <div class="col-md-4"><div class="card"><div class="card-body"><div class="stat-label">Payroll-Locked Shortage Deductions</div><div class="stat-value">PHP <?= number_format((float) ($stats['paid_shortage_deductions'] ?? 0), 2) ?></div></div></div></div>
            <div class="col-md-4"><div class="card"><div class="card-body"><div class="stat-label">Payroll-Locked Repayments</div><div class="stat-value">PHP <?= number_format((float) ($stats['paid_shortage_repayments'] ?? 0), 2) ?></div></div></div></div>
        </div>
    </div>

    <div class="tab-pane fade" id="deliveries-pane" role="tabpanel" aria-labelledby="deliveries-tab" tabindex="0">
        <div class="card">
            <div class="card-header fw-semibold">Delivery History</div>
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Allocated</th>
                            <th>Successful</th>
                            <th>Failed</th>
                            <th>Expected Remittance</th>
                            <th>Salary Earning</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deliveries as $item): ?>
                            <tr>
                                <td><?= esc($item['delivery_date']) ?></td>
                                <td><?= (int) $item['allocated_parcels'] ?></td>
                                <td><?= (int) $item['successful_deliveries'] ?></td>
                                <td><?= (int) $item['failed_deliveries'] ?></td>
                                <td>PHP <?= number_format((float) ($item['expected_remittance'] ?? 0), 2) ?></td>
                                <td>PHP <?= number_format((float) $item['total_due'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($deliveries)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No records for this month.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="requests-pane" role="tabpanel" aria-labelledby="requests-tab" tabindex="0">
        <div class="card">
            <div class="card-header fw-semibold">Submitted Delivery Requests</div>
            <div class="list-group list-group-flush">
                <?php foreach ($submissionHistory as $submission): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-semibold"><?= esc($submission['delivery_date']) ?></div>
                                <div class="small text-muted">Allocated <?= (int) $submission['allocated_parcels'] ?> | Successful <?= (int) $submission['successful_deliveries'] ?></div>
                                <div class="small text-muted">Expected Remittance: PHP <?= number_format((float) ($submission['expected_remittance'] ?? 0), 2) ?></div>
                                <div class="small text-muted">Remittance account: <?= esc($formatAccountLabel($submission)) ?></div>
                            </div>
                            <span class="badge <?= ($submission['status'] ?? '') === 'APPROVED' ? 'badge-over' : (($submission['status'] ?? '') === 'REJECTED' ? 'badge-short' : 'badge-balanced') ?>"><?= esc($submission['status']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($submissionHistory)): ?>
                    <div class="list-group-item text-muted">No submitted delivery requests yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="payroll-pane" role="tabpanel" aria-labelledby="payroll-tab" tabindex="0">
        <div class="card">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center"><span>Recent Payroll History</span><span class="small text-muted">Paid salary stays here after release or receipt</span></div>
            <div class="list-group list-group-flush">
                <?php foreach ($payrollHistory as $payroll): ?>
                    <?php $payrollStatus = (string) ($payroll['payroll_status'] ?? 'GENERATED'); ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-semibold"><?= esc($payroll['start_date'] ?? $payroll['month_year']) ?> to <?= esc($payroll['end_date'] ?? $payroll['month_year']) ?></div>
                                <div class="small text-muted">Net Pay: PHP <?= number_format((float) $payroll['net_pay'], 2) ?></div>
                                <div class="small text-muted">Gross Earnings: PHP <?= number_format((float) ($payroll['gross_earnings'] ?? 0), 2) ?></div>
                                <div class="small text-muted">Outstanding Shortage: PHP <?= number_format((float) ($payroll['outstanding_shortage_balance'] ?? 0), 2) ?></div>
                                <?php if (! empty($payroll['payout_method'])): ?>
                                    <div class="small text-muted">Payout Method: <?= esc(str_replace('_', ' ', (string) $payroll['payout_method'])) ?></div>
                                <?php endif; ?>
                                <?php if (! empty($payroll['payout_reference'])): ?>
                                    <div class="small text-muted">Reference: <?= esc($payroll['payout_reference']) ?></div>
                                <?php endif; ?>
                                <?php if (! empty($payroll['released_at'])): ?>
                                    <div class="small text-muted">Released: <?= esc($payroll['released_at']) ?></div>
                                <?php endif; ?>
                                <?php if (! empty($payroll['received_at'])): ?>
                                    <div class="small text-muted">Received: <?= esc($payroll['received_at']) ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="badge <?= $payrollStatus === 'RECEIVED' ? 'badge-over' : ($payrollStatus === 'RELEASED' ? 'badge-balanced' : 'badge-short') ?>"><?= esc($payrollStatus) ?></span>
                        </div>
                        <div class="mt-3 d-flex gap-2 flex-wrap">
                            <a href="<?= site_url('/rider/payroll/' . (int) $payroll['id'] . '/pdf') ?>" class="btn btn-sm btn-outline-dark" target="_blank">Download Payslip</a>
                            <?php if ($payrollStatus === 'RELEASED'): ?>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-dark"
                                    data-bs-toggle="modal"
                                    data-bs-target="#confirmPayrollModal"
                                    data-payroll-id="<?= (int) $payroll['id'] ?>"
                                    data-payroll-range="<?= esc(($payroll['start_date'] ?? $payroll['month_year']) . ' to ' . ($payroll['end_date'] ?? $payroll['month_year']), 'attr') ?>"
                                    data-net-pay="<?= esc(number_format((float) ($payroll['net_pay'] ?? 0), 2, '.', ''), 'attr') ?>"
                                    data-gross-earnings="<?= esc(number_format((float) ($payroll['gross_earnings'] ?? 0), 2, '.', ''), 'attr') ?>"
                                    data-shortage="<?= esc(number_format((float) ($payroll['shortage_deductions'] ?? 0), 2, '.', ''), 'attr') ?>"
                                    data-repayments="<?= esc(number_format((float) ($payroll['shortage_payments_received'] ?? 0), 2, '.', ''), 'attr') ?>"
                                    data-payout-method="<?= esc(str_replace('_', ' ', (string) ($payroll['payout_method'] ?? '')), 'attr') ?>"
                                    data-payout-reference="<?= esc((string) ($payroll['payout_reference'] ?? ''), 'attr') ?>"
                                    data-released-at="<?= esc((string) ($payroll['released_at'] ?? ''), 'attr') ?>"
                                >
                                    Review &amp; Confirm
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($payrollHistory)): ?>
                    <div class="list-group-item text-muted">No payroll history yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="announcements-pane" role="tabpanel" aria-labelledby="announcements-tab" tabindex="0">
        <div class="card">
            <div class="card-header fw-semibold">Branch Announcements</div>
            <div class="list-group list-group-flush">
                <?php foreach ($announcements as $announcement): ?>
                    <div class="list-group-item">
                        <div class="fw-semibold"><?= esc($announcement['title']) ?></div>
                        <div class="small text-muted mb-1">Published <?= esc(date('Y-m-d', strtotime((string) $announcement['published_at']))) ?></div>
                        <div><?= esc($announcement['message']) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($announcements)): ?>
                    <div class="list-group-item text-muted">No active announcements.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deliveryRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Submit Delivery Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?= site_url('/rider/delivery-submissions') ?>" data-delivery-form>
                    <?= csrf_field() ?>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Date</label>
                            <input type="date" name="delivery_date" value="<?= esc(date('Y-m-d')) ?>" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Allocated Parcels</label>
                            <input type="number" min="0" name="allocated_parcels" class="form-control" required data-allocated>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Successful Deliveries</label>
                            <input type="number" min="0" name="successful_deliveries" class="form-control" required data-successful>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Failed Deliveries</label>
                            <input type="number" min="0" class="form-control" readonly data-failed>
                            <div class="form-text">Auto-calculated as allocated minus successful.</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Expected Remittance (PHP)</label>
                            <input type="number" step="0.01" min="0" name="expected_remittance" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Remittance Account Used</label>
                            <select name="remittance_account_id" class="form-select" required>
                                <option value="">Select the J&amp;T-connected account used for this remittance</option>
                                <?php foreach ($remittanceAccounts as $account): ?>
                                    <option value="<?= (int) $account['id'] ?>"><?= esc($formatAccountLabel($account)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Choose which main remittance account you used so the branch can track collections correctly.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Optional notes for the admin about your delivery day."></textarea>
                        </div>
                    </div>
                    <button class="btn btn-dark mt-3 w-100" <?= empty($remittanceAccounts) ? 'disabled' : '' ?>>Submit For Admin Review</button>
                    <?php if (empty($remittanceAccounts)): ?>
                        <div class="alert alert-warning mt-3 mb-0">No remittance accounts are configured yet. Ask the admin to add them in Settings before submitting.</div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmPayrollModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Salary Release Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-3">Review the released payroll details below before confirming receipt.</div>
                <div class="border rounded p-3 bg-light mb-3">
                    <div><strong>Coverage:</strong> <span data-payroll-range-display>-</span></div>
                    <div><strong>Net Pay:</strong> PHP <span data-payroll-net-display>0.00</span></div>
                    <div><strong>Gross Earnings:</strong> PHP <span data-payroll-gross-display>0.00</span></div>
                    <div><strong>Shortage Deductions:</strong> PHP <span data-payroll-shortage-display>0.00</span></div>
                    <div><strong>Repayments:</strong> PHP <span data-payroll-repayment-display>0.00</span></div>
                    <div><strong>Payout Method:</strong> <span data-payroll-method-display>-</span></div>
                    <div><strong>Reference:</strong> <span data-payroll-reference-display>-</span></div>
                    <div><strong>Released At:</strong> <span data-payroll-released-display>-</span></div>
                </div>
                <form method="post" action="<?= site_url('/rider/payroll/0/confirm') ?>" id="confirmPayrollForm">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Optional Note</label>
                        <textarea name="received_notes" class="form-control form-control-sm" rows="3" maxlength="500" placeholder="Optional note about receiving this salary."></textarea>
                    </div>
                    <button class="btn btn-dark w-100">Confirm Salary Received</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (! empty($latestAnnouncementPopup)): ?>
    <div class="modal fade" id="announcementPopupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="fw-semibold mb-1"><?= esc($latestAnnouncementPopup['title']) ?></div>
                    <div class="small text-muted mb-2">Published <?= esc(date('Y-m-d', strtotime((string) $latestAnnouncementPopup['published_at']))) ?></div>
                    <p class="mb-0"><?= esc($latestAnnouncementPopup['message']) ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const payrollModal = document.getElementById('confirmPayrollModal');
    if (payrollModal) {
        const form = document.getElementById('confirmPayrollForm');
        const map = {
            range: payrollModal.querySelector('[data-payroll-range-display]'),
            net: payrollModal.querySelector('[data-payroll-net-display]'),
            gross: payrollModal.querySelector('[data-payroll-gross-display]'),
            shortage: payrollModal.querySelector('[data-payroll-shortage-display]'),
            repayment: payrollModal.querySelector('[data-payroll-repayment-display]'),
            method: payrollModal.querySelector('[data-payroll-method-display]'),
            reference: payrollModal.querySelector('[data-payroll-reference-display]'),
            released: payrollModal.querySelector('[data-payroll-released-display]'),
        };
        const fillPayrollModal = (payload) => {
            form.action = '<?= site_url('/rider/payroll') ?>/' + (payload.id || '0') + '/confirm';
            map.range.textContent = payload.range || '-';
            map.net.textContent = payload.net || '0.00';
            map.gross.textContent = payload.gross || '0.00';
            map.shortage.textContent = payload.shortage || '0.00';
            map.repayment.textContent = payload.repayment || '0.00';
            map.method.textContent = payload.method || '-';
            map.reference.textContent = payload.reference || '-';
            map.released.textContent = payload.released || '-';
        };

        payrollModal.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            if (!trigger) {
                return;
            }

            fillPayrollModal({
                id: trigger.getAttribute('data-payroll-id') || '0',
                range: trigger.getAttribute('data-payroll-range') || '-',
                net: trigger.getAttribute('data-net-pay') || '0.00',
                gross: trigger.getAttribute('data-gross-earnings') || '0.00',
                shortage: trigger.getAttribute('data-shortage') || '0.00',
                repayment: trigger.getAttribute('data-repayments') || '0.00',
                method: trigger.getAttribute('data-payout-method') || '-',
                reference: trigger.getAttribute('data-payout-reference') || '-',
                released: trigger.getAttribute('data-released-at') || '-',
            });
        });

        <?php if (! empty($latestReleasedPayroll) && empty($latestAnnouncementPopup)): ?>
        fillPayrollModal({
            id: '<?= (int) $latestReleasedPayroll['id'] ?>',
            range: '<?= esc(($latestReleasedPayroll['start_date'] ?? $latestReleasedPayroll['month_year']) . ' to ' . ($latestReleasedPayroll['end_date'] ?? $latestReleasedPayroll['month_year']), 'js') ?>',
            net: '<?= esc(number_format((float) ($latestReleasedPayroll['net_pay'] ?? 0), 2, '.', ''), 'js') ?>',
            gross: '<?= esc(number_format((float) ($latestReleasedPayroll['gross_earnings'] ?? 0), 2, '.', ''), 'js') ?>',
            shortage: '<?= esc(number_format((float) ($latestReleasedPayroll['shortage_deductions'] ?? 0), 2, '.', ''), 'js') ?>',
            repayment: '<?= esc(number_format((float) ($latestReleasedPayroll['shortage_payments_received'] ?? 0), 2, '.', ''), 'js') ?>',
            method: '<?= esc(str_replace('_', ' ', (string) ($latestReleasedPayroll['payout_method'] ?? '')), 'js') ?>',
            reference: '<?= esc((string) ($latestReleasedPayroll['payout_reference'] ?? ''), 'js') ?>',
            released: '<?= esc((string) ($latestReleasedPayroll['released_at'] ?? ''), 'js') ?>',
        });
        new bootstrap.Modal(payrollModal).show();
        <?php endif; ?>
    }
});
</script>
<?php if (! empty($latestAnnouncementPopup)): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('announcementPopupModal');
    if (!modalElement) {
        return;
    }

    const modalInstance = new bootstrap.Modal(modalElement);
    modalInstance.show();
});
</script>
<?php endif; ?>
<?= $this->endSection() ?>



