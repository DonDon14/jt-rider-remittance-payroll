<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-hero">
    <div>
        <h2 class="mb-0">Operations Dashboard</h2>
        <p>Monitor rider activity, cash remittance accuracy, shortage exposure, payroll readiness, and rider-submitted requests from one place.</p>
    </div>
    <div class="text-muted">Today: <?= esc($today) ?></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl"><div class="card stat-card"><div class="card-body"><div class="stat-label">Riders</div><div class="stat-value"><?= (int) $summary['riders'] ?></div></div></div></div>
    <div class="col-md-6 col-xl"><div class="card stat-card"><div class="card-body"><div class="stat-label">Today's Delivered Parcels</div><div class="stat-value"><?= (int) $summary['today_deliveries'] ?></div></div></div></div>
    <div class="col-md-6 col-xl"><div class="card stat-card"><div class="card-body"><div class="stat-label">Today's Salary Earnings</div><div class="stat-value">PHP <?= number_format((float) $summary['today_salary_earnings'], 2) ?></div></div></div></div>
    <div class="col-md-6 col-xl"><div class="card stat-card"><div class="card-body"><div class="stat-label">Expected Remittance</div><div class="stat-value">PHP <?= number_format((float) $summary['today_expected_remittance'], 2) ?></div></div></div></div>
    <div class="col-md-6 col-xl"><div class="card stat-card"><div class="card-body"><div class="stat-label">Today's Remitted</div><div class="stat-value">PHP <?= number_format((float) $summary['today_remitted'], 2) ?></div></div></div></div>
    <div class="col-md-6 col-xl"><div class="card stat-card"><div class="card-body"><div class="stat-label">Open Shortages</div><div class="stat-value"><?= (int) $summary['open_shortages'] ?></div></div></div></div>
    <div class="col-md-6 col-xl"><div class="card stat-card"><div class="card-body"><div class="stat-label">Pending Rider Requests</div><div class="stat-value"><?= (int) $summary['pending_submission_requests'] ?></div></div></div></div>
</div>

<div class="card mb-4">
    <div class="card-header fw-semibold">Quick Actions</div>
    <div class="card-body">
        <div class="quick-actions">
            <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#dashboardRiderModal">Add Rider</button>
            <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#dashboardDeliveryModal">New Delivery</button>
            <a href="<?= site_url('/admin/remittances') ?>" class="btn btn-outline-dark">Collect Remittance</a>
            <button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#dashboardAdjustmentModal">Add Adjustment</button>
            <button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#dashboardPayrollModal">Generate Payroll</button>
            <a href="<?= site_url('/admin/announcements') ?>" class="btn btn-outline-dark">Manage Announcements</a>
        </div>
    </div>
</div>

<div class="quick-grid mb-4">
    <div class="card">
        <div class="card-body">
            <h5>Rider Requests</h5>
            <p class="text-muted">Review rider-submitted delivery requests before remittance collection. Pending now: <?= (int) $summary['pending_submission_requests'] ?>.</p>
            <a href="<?= site_url('/admin/remittances') ?>" class="btn btn-outline-dark">Open Requests</a>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h5>Delivery History</h5>
            <p class="text-muted">Search previous rider-day records and inspect salary earnings against expected remittances.</p>
            <a href="<?= site_url('/admin/history') ?>" class="btn btn-outline-dark">Open History</a>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h5>Payroll Adjustments</h5>
            <p class="text-muted">Add bonuses and deductions that lock into payroll just like operational records.</p>
            <a href="<?= site_url('/admin/adjustments') ?>" class="btn btn-outline-dark">Open Adjustments</a>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h5>Analytics</h5>
            <p class="text-muted">Review trends, rider rankings, and daily delivery output.</p>
            <a href="<?= site_url('/admin/analytics') ?>" class="btn btn-outline-dark">Open Analytics</a>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header fw-semibold">Pending Rider Delivery Requests</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Rider</th>
                    <th>Allocated</th>
                    <th>Successful</th>
                    <th>Expected Remittance</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingSubmissions as $submission): ?>
                    <tr>
                        <td><?= esc($submission['delivery_date']) ?></td>
                        <td><?= esc($submission['rider_code']) ?> - <?= esc($submission['name']) ?></td>
                        <td><?= (int) $submission['allocated_parcels'] ?></td>
                        <td><?= (int) $submission['successful_deliveries'] ?></td>
                        <td>PHP <?= number_format((float) ($submission['expected_remittance'] ?? 0), 2) ?></td>
                        <td><a href="<?= site_url('/admin/remittances') ?>" class="btn btn-sm btn-outline-dark">Open Queue</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($pendingSubmissions)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No rider submitted delivery requests waiting for admin review.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card rank-card h-100">
            <div class="card-header fw-semibold">Top Performing Riders</div>
            <div class="list-group list-group-flush">
                <?php foreach ($topRiders as $index => $rider): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold"><?= $index + 1 ?>. <?= esc($rider['rider_code']) ?> - <?= esc($rider['name']) ?></div>
                            <div class="metric-note">Success rate: <?= number_format((float) $rider['success_rate'], 2) ?>%</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold"><?= (int) $rider['successful_total'] ?> delivered</div>
                            <div class="metric-note">PHP <?= number_format((float) $rider['earning_total'], 2) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($topRiders)): ?>
                    <div class="list-group-item text-muted">No performance data yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card rank-card h-100">
            <div class="card-header fw-semibold">Lower Performing Riders</div>
            <div class="list-group list-group-flush">
                <?php foreach ($lowRiders as $index => $rider): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold"><?= $index + 1 ?>. <?= esc($rider['rider_code']) ?> - <?= esc($rider['name']) ?></div>
                            <div class="metric-note">Success rate: <?= number_format((float) $rider['success_rate'], 2) ?>%</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold"><?= (int) $rider['successful_total'] ?> delivered</div>
                            <div class="metric-note">PHP <?= number_format((float) $rider['earning_total'], 2) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($lowRiders)): ?>
                    <div class="list-group-item text-muted">No performance data yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header fw-semibold">Recent Delivery Records</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Rider</th>
                            <th>Successful</th>
                            <th>Salary Earning</th>
                            <th>Expected Remittance</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentDeliveries as $record): ?>
                            <tr>
                                <td><?= esc($record['delivery_date']) ?></td>
                                <td><?= esc($record['rider_code']) ?> - <?= esc($record['name']) ?></td>
                                <td><?= (int) $record['successful_deliveries'] ?></td>
                                <td>PHP <?= number_format((float) $record['total_due'], 2) ?></td>
                                <td>PHP <?= number_format((float) ($record['expected_remittance'] ?? 0), 2) ?></td>
                                <td><a href="<?= site_url('/admin/deliveries/' . (int) $record['id']) ?>" class="btn btn-sm btn-outline-primary">Details</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentDeliveries)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No delivery records yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header fw-semibold">Recent Remittance Outcomes</div>
            <div class="list-group list-group-flush">
                <?php foreach ($recentRemittances as $item): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-semibold"><?= esc($item['rider_code']) ?> - <?= esc($item['name']) ?></div>
                                <div class="text-muted small"><?= esc($item['delivery_date']) ?></div>
                            </div>
                            <span class="badge <?= 'badge-' . strtolower($item['variance_type']) ?>"><?= esc($item['variance_type']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($recentRemittances)): ?>
                    <div class="list-group-item text-muted">No remittances recorded yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header fw-semibold">Active Announcements</div>
    <div class="list-group list-group-flush">
        <?php foreach ($announcements as $announcement): ?>
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="fw-semibold"><?= esc($announcement['title']) ?></div>
                        <div class="text-muted small mb-1">Published <?= esc(date('Y-m-d', strtotime((string) $announcement['published_at']))) ?></div>
                        <div><?= esc($announcement['message']) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($announcements)): ?>
            <div class="list-group-item text-muted">No active announcements.</div>
        <?php endif; ?>
    </div>
</div>
<div class="modal fade" id="dashboardRiderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Rider</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?= site_url('/admin/riders') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label">Rider Code</label>
                        <input type="text" name="rider_code" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Commission Per Parcel (PHP)</label>
                        <input type="number" step="0.01" min="0" name="commission_rate" class="form-control" value="13.00" required>
                    </div>
                    <button class="btn btn-success w-100">Save Rider</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dashboardDeliveryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Delivery Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?= site_url('/admin/deliveries') ?>" data-delivery-form>
                    <?= csrf_field() ?>
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label">Rider</label>
                            <div class="searchable-select" data-searchable-select>
                                <input type="text" class="form-control mb-2" placeholder="Search rider code or name" data-search-target>
                                <select name="rider_id" class="form-select" required data-search-source data-delivery-rider>
                                    <option value="">Select rider</option>
                                    <?php foreach ($riders as $rider): ?>
                                        <option value="<?= (int) $rider['id'] ?>" data-commission="<?= esc(number_format((float) $rider['commission_rate'], 2, '.', '')) ?>"><?= esc($rider['rider_code']) ?> - <?= esc($rider['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date</label>
                            <input type="date" name="delivery_date" value="<?= esc($today) ?>" class="form-control" required>
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
                        <div class="col-md-4">
                            <label class="form-label">Commission Used (PHP)</label>
                            <input type="number" step="0.01" min="0.01" name="commission_rate" class="form-control" value="13.00" required data-delivery-commission>
                            <div class="form-text">Adjust this if the rider handled a different area rate today.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expected Remittance (PHP)</label>
                            <input type="number" step="0.01" min="0" name="expected_remittance" class="form-control" required>
                            <div class="form-text">Enter the total cash collection the rider should remit for that day.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <button class="btn btn-primary mt-3 w-100">Save Delivery Record</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dashboardAdjustmentModal" tabindex="-1" aria-hidden="true">
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
                            <input class="form-check-input" type="radio" name="adjustment_scope" id="dashboardAdjustmentScopeSingle" value="SINGLE" checked data-adjustment-scope>
                            <label class="form-check-label" for="dashboardAdjustmentScopeSingle">Single rider</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="adjustment_scope" id="dashboardAdjustmentScopeAll" value="ALL" data-adjustment-scope>
                            <label class="form-check-label" for="dashboardAdjustmentScopeAll">All riders</label>
                        </div>
                    </div>
                    <div class="mb-3" data-adjustment-rider-group>
                        <label class="form-label">Rider</label>
                        <div class="searchable-select" data-searchable-select>
                            <input type="text" class="form-control mb-2" placeholder="Search rider code or name" data-search-target>
                            <select name="rider_id" class="form-select" required data-search-source data-adjustment-rider-select>
                                <option value="">Select rider</option>
                                <?php foreach ($riders as $rider): ?>
                                    <option value="<?= (int) $rider['id'] ?>" data-commission="<?= esc(number_format((float) $rider['commission_rate'], 2, '.', '')) ?>"><?= esc($rider['rider_code']) ?> - <?= esc($rider['name']) ?></option>
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

<div class="modal fade" id="dashboardPayrollModal" tabindex="-1" aria-hidden="true">
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
                            <select name="rider_id" class="form-select" required data-search-source data-delivery-rider>
                                <option value="">Select rider</option>
                                <?php foreach ($riders as $rider): ?>
                                    <option value="<?= (int) $rider['id'] ?>" data-commission="<?= esc(number_format((float) $rider['commission_rate'], 2, '.', '')) ?>"><?= esc($rider['rider_code']) ?> - <?= esc($rider['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payroll Month</label>
                        <input type="month" name="payroll_month" class="form-control" value="<?= esc(date('Y-m', strtotime($today))) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cutoff Period</label>
                        <select name="cutoff_period" class="form-select" required>
                            <option value="FIRST" <?= (int) date('j', strtotime($today)) <= 15 ? 'selected' : '' ?>>1 to 15</option>
                            <option value="SECOND" <?= (int) date('j', strtotime($today)) > 15 ? 'selected' : '' ?>>16 to Month-End</option>
                        </select>
                    </div>
                    <button class="btn btn-dark w-100">Generate Payroll</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>





