<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-hero">
    <div>
        <h2 class="mb-0">Shortage Ledger</h2>
        <p>Track all remittance shortages, monitor remaining balances, and archive fully settled cases for later reference.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header fw-semibold">Open Shortages</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Rider</th>
                            <th>Shortage</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($openShortages as $item): ?>
                            <tr>
                                <td><?= esc($item['delivery_date']) ?></td>
                                <td>
                                    <div class="fw-semibold mb-2"><?= esc($item['rider_code']) ?> - <?= esc($item['name']) ?></div>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-dark"
                                        data-bs-toggle="modal"
                                        data-bs-target="#shortageRemittanceModal"
                                        data-modal-title="Remittance Entry"
                                        data-modal-url="<?= site_url('/admin/remittance/' . (int) $item['delivery_record_id']) . '?modal=1' ?>"
                                        data-modal-frame
                                    >Open Remittance</button>
                                </td>
                                <td>PHP <?= number_format((float) $item['variance_amount'], 2) ?></td>
                                <td>PHP <?= number_format((float) $item['paid_amount'], 2) ?></td>
                                <td>PHP <?= number_format((float) $item['outstanding_balance'], 2) ?></td>
                                <td>
                                    <span class="badge text-bg-danger">OPEN</span>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="6" class="bg-light">
                                    <form method="post" action="<?= site_url('/admin/shortages/' . (int) $item['id'] . '/payment') ?>" class="row g-2 align-items-end">
                                        <?= csrf_field() ?>
                                        <div class="col-md-3">
                                            <label class="form-label mb-1">Payment Date</label>
                                            <input type="date" name="payment_date" value="<?= esc($today) ?>" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label mb-1">Amount</label>
                                            <input type="number" step="0.01" min="0.01" max="<?= esc($item['outstanding_balance']) ?>" name="amount" class="form-control form-control-sm" placeholder="0.00" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label mb-1">Notes</label>
                                            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Partial payment, full settlement, etc.">
                                        </div>
                                        <div class="col-md-2">
                                            <button class="btn btn-sm btn-dark w-100">Record Payment</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($openShortages)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No open shortage records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">Payment History</div>
            <div class="list-group list-group-flush">
                <?php foreach ($paymentHistory as $payment): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between gap-2">
                            <div>
                                <div class="fw-semibold"><?= esc($payment['rider_code']) ?> - <?= esc($payment['name']) ?></div>
                                <div class="small text-muted"><?= esc($payment['payment_date']) ?></div>
                                <?php if (! empty($payment['notes'])): ?>
                                    <div class="small"><?= esc($payment['notes']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="fw-semibold">PHP <?= number_format((float) $payment['amount'], 2) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($paymentHistory)): ?>
                    <div class="list-group-item text-muted">No shortage payments recorded yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold">Archived Settled Shortages</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Rider</th>
                            <th>Shortage</th>
                            <th>Paid</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($archivedShortages as $item): ?>
                            <tr>
                                <td><?= esc($item['delivery_date']) ?></td>
                                <td><?= esc($item['rider_code']) ?> - <?= esc($item['name']) ?></td>
                                <td>PHP <?= number_format((float) $item['variance_amount'], 2) ?></td>
                                <td>PHP <?= number_format((float) $item['paid_amount'], 2) ?></td>
                                <td><span class="badge text-bg-success">SETTLED</span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($archivedShortages)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No settled shortages archived yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="shortageRemittanceModal" tabindex="-1" aria-hidden="true">
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
