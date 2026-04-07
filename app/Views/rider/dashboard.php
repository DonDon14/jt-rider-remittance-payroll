<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$formatAccountLabel = static function (array $row): string {
    $name = trim((string) ($row['remittance_account_name'] ?? ''));
    $number = trim((string) ($row['remittance_account_number'] ?? ''));

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
            <div class="badge text-bg-dark px-3 py-2">Projected Net: PHP <?= number_format((float) $stats['projected_net'], 2) ?></div>
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
        <div class="card salary-focus-card mb-3">
            <div class="card-body">
                <div class="stat-label">Running Salary For <?= esc(date('F Y', strtotime($month . '-01'))) ?></div>
                <div class="salary-focus-value">PHP <?= number_format((float) $stats['running_salary'], 2) ?></div>
                <div class="text-muted">This is the current salary total from successful deliveries before deductions and added repayments.</div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Allocated</div><div class="stat-value"><?= (int) $stats['allocated'] ?></div></div></div></div>
            <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Successful</div><div class="stat-value"><?= (int) $stats['successful'] ?></div></div></div></div>
            <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Expected Remittance</div><div class="stat-value">PHP <?= number_format((float) $stats['expected_remittance'], 2) ?></div></div></div></div>
            <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Outstanding Shortage</div><div class="stat-value">PHP <?= number_format((float) $stats['outstanding_shortage_balance'], 2) ?></div></div></div></div>
        </div>

        <div class="row g-3">
            <div class="col-md-4"><div class="card"><div class="card-body"><div class="stat-label">Total Remitted</div><div class="stat-value">PHP <?= number_format((float) $stats['total_remitted'], 2) ?></div></div></div></div>
            <div class="col-md-4"><div class="card"><div class="card-body"><div class="stat-label">Month Shortage Deductions</div><div class="stat-value">PHP <?= number_format((float) $stats['shortage_deductions'], 2) ?></div></div></div></div>
            <div class="col-md-4"><div class="card"><div class="card-body"><div class="stat-label">Month Repayments</div><div class="stat-value">PHP <?= number_format((float) $stats['shortage_repayments'], 2) ?></div></div></div></div>
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
            <div class="card-header fw-semibold">Recent Payroll History</div>
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
                        <?php if ($payrollStatus === 'RELEASED'): ?>
                            <form method="post" action="<?= site_url('/rider/payroll/' . (int) $payroll['id'] . '/confirm') ?>" class="mt-3">
                                <?= csrf_field() ?>
                                <div class="mb-2">
                                    <textarea name="received_notes" class="form-control form-control-sm" rows="2" maxlength="500" placeholder="Optional note about receiving this salary."></textarea>
                                </div>
                                <button class="btn btn-sm btn-dark" onclick="return confirm('Confirm that you have received this payroll amount?');">Confirm Salary Received</button>
                            </form>
                        <?php endif; ?>
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
