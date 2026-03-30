<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-hero">
    <div>
        <h2 class="mb-0">Remittance Collection</h2>
        <p>Review each rider-day's expected cash collection and compare it against what the rider actually remitted.</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold">Rider Submitted Delivery Requests</div>
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
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-warning"
                                        data-bs-toggle="modal"
                                        data-bs-target="#remittanceModal"
                                        data-modal-title="Review Rider Submission"
                                        data-modal-url="<?= site_url('/admin/delivery-submissions/' . (int) $submission['id']) . '?modal=1' ?>"
                                        data-modal-frame
                                    >Review &amp; Collect</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($pendingSubmissions)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No rider submitted requests waiting for approval.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">Pending Remittance Queue</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Rider</th>
                            <th>Expected Remittance</th>
                            <th>Aging</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingDeliveries as $record): ?>
                            <tr>
                                <td><?= esc($record['delivery_date']) ?></td>
                                <td><?= esc($record['rider_code']) ?> - <?= esc($record['name']) ?></td>
                                <td>PHP <?= number_format((float) ($record['expected_remittance'] ?? 0), 2) ?></td>
                                <td><?= (int) $record['aging_days'] ?> day(s)</td>
                                <td>
                                    <span class="badge <?= $record['pending_status'] === 'OVERDUE' ? 'text-bg-danger' : 'text-bg-warning' ?>">
                                        <?= esc($record['pending_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#remittanceModal"
                                        data-modal-title="Remittance Entry"
                                        data-modal-url="<?= site_url('/admin/remittance/' . (int) $record['id']) . '?modal=1' ?>"
                                        data-modal-frame
                                    >Collect</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($pendingDeliveries)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No pending remittances.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">Recent Remittance History</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Rider</th>
                            <th>Expected</th>
                            <th>Remitted</th>
                            <th>Outcome</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRemittances as $item): ?>
                            <tr>
                                <td><?= esc($item['delivery_date']) ?></td>
                                <td><?= esc($item['rider_code']) ?> - <?= esc($item['name']) ?></td>
                                <td>PHP <?= number_format((float) ($item['supposed_remittance'] ?? 0), 2) ?></td>
                                <td>PHP <?= number_format((float) $item['total_remitted'], 2) ?></td>
                                <td><span class="badge <?= 'badge-' . strtolower($item['variance_type']) ?>"><?= esc($item['settlement_status']) ?></span></td>
                                <td><a href="<?= site_url('/admin/remittance/pdf/' . (int) $item['id']) ?>" target="_blank" class="btn btn-sm btn-warning">Receipt</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentRemittances)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No remittances recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="remittanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" data-modal-title-display>Remittance Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe class="modal-iframe" data-modal-iframe src="about:blank" title="Remittance Entry"></iframe>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

