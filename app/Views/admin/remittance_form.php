<?= $this->extend($layout ?? 'layouts/main') ?>
<?= $this->section('content') ?>

<?php
$entries = $entries ?? [];
$currentTotal = (float) ($remittance['total_remitted'] ?? 0);
$currentCash = (float) ($remittance['cash_remitted'] ?? 0);
$currentGcash = (float) ($remittance['gcash_remitted'] ?? 0);
$expectedTotal = (float) ($delivery['expected_remittance'] ?? 0);
$entryModeLabel = $entries === [] ? 'Initial Remittance' : 'Supplemental Remittance';
$entryActionLabel = $entries === [] ? 'Save Initial Remittance' : 'Add Supplemental Remittance';
?>

<?php if (empty($isModal)): ?>
    <div class="page-hero">
        <div>
            <h2 class="mb-0">Remittance Entry</h2>
            <p>Record the rider's remittance in pieces when needed, while the system keeps a running total for the day.</p>
        </div>
        <a href="<?= site_url('/admin/remittances') ?>" class="btn btn-outline-secondary">Back to Remittances</a>
    </div>
<?php else: ?>
    <div class="mb-3">
        <h3 class="mb-1">Remittance Entry</h3>
        <p class="text-muted mb-0">Record the rider's remittance in pieces when needed, while the system keeps a running total for the day.</p>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-3"><strong>Rider:</strong> <?= esc($delivery['rider_code']) ?> - <?= esc($delivery['name']) ?></div>
            <div class="col-md-3"><strong>Date:</strong> <?= esc($delivery['delivery_date']) ?></div>
            <div class="col-md-3"><strong>Expected Remittance:</strong> PHP <?= number_format($expectedTotal, 2) ?></div>
            <div class="col-md-3"><strong>Salary Earning:</strong> PHP <?= number_format((float) $delivery['total_due'], 2) ?></div>
            <div class="col-12"><strong>Selected Remittance Account:</strong> <?= esc(trim((string) ($delivery['remittance_account_name'] ?? '')) !== '' ? (($delivery['remittance_account_name'] ?? '') . (! empty($delivery['remittance_account_number']) ? ' (' . $delivery['remittance_account_number'] . ')' : '')) : '-') ?></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="stat-label">Current Collected Total</div><div class="stat-value">PHP <?= number_format($currentTotal, 2) ?></div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="stat-label">Collected Cash</div><div class="stat-value">PHP <?= number_format($currentCash, 2) ?></div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="stat-label">Collected GCash</div><div class="stat-value">PHP <?= number_format($currentGcash, 2) ?></div></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <h5 class="mb-3"><?= esc($entryModeLabel) ?></h5>
        <form method="post" action="<?= site_url('/admin/remittance/' . (int) $delivery['id']) ?><?= ! empty($isModal) ? '?modal=1' : '' ?>" data-remittance-form data-current-total="<?= esc(number_format($currentTotal, 2, '.', '')) ?>">
            <?= csrf_field() ?>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label"><strong>Expected Remittance</strong></label>
                    <input type="number" step="0.01" value="<?= esc(number_format($expectedTotal, 2, '.', '')) ?>" class="form-control" readonly data-supposed-remittance>
                    <div class="form-text">This is the full day total expected from the rider.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><strong>GCash Remitted</strong></label>
                    <input type="number" step="0.01" min="0" name="gcash_remitted" placeholder="0.00" value="" class="form-control" data-gcash-remitted>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><strong>Cash Remitted</strong></label>
                    <input type="number" step="0.01" min="0" name="cash_remitted" placeholder="Auto-filled from denominations below" value="" class="form-control" data-cash-remitted>
                    <div class="form-text">This updates live from the denomination counts below. If no denominations are entered, you can still type the cash amount manually.</div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label"><strong>GCash Reference</strong></label>
                    <input type="text" name="gcash_reference" maxlength="100" placeholder="Reference number, sender, or note" value="" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><strong>Entry Note</strong></label>
                    <input type="text" name="entry_notes" maxlength="255" placeholder="Optional note for this remittance piece" value="" class="form-control">
                </div>
            </div>

            <hr>
            <h5 class="mb-3">Cash Denomination Count (Optional)</h5>

            <div class="row g-2">
                <?php foreach ($denominations as $field => $value): ?>
                    <div class="col-md-4">
                        <label class="form-label"><?= $value < 1 ? '25c' : 'PHP ' . number_format($value, 2) ?></label>
                        <input type="number" min="0" name="<?= esc($field) ?>" value="0" class="form-control" data-denom data-value="<?= esc($value) ?>">
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4 d-flex flex-wrap gap-2 align-items-center">
                <div class="alert alert-light border mb-0">Cash from Denominations: <strong data-cash-total>PHP 0.00</strong></div>
                <div class="alert alert-light border mb-0">This Entry: <strong data-entry-total>PHP 0.00</strong></div>
                <div class="alert alert-light border mb-0">Projected Grand Total: <strong data-total-remitted>PHP <?= number_format($currentTotal, 2) ?></strong></div>
                <div class="alert alert-light border mb-0">Projected Status: <span data-variance-status class="badge text-bg-secondary"><?= esc((string) ($remittance['variance_type'] ?? 'PENDING')) ?></span></div>
                <button class="btn btn-primary"><?= esc($entryActionLabel) ?></button>
                <?php if (! empty($remittance['id'])): ?>
                    <a href="<?= site_url('/admin/remittance/pdf/' . (int) $remittance['id']) ?>" class="btn btn-dark" target="_blank">Download Receipt PDF</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header fw-semibold">Remittance Piece History</div>
    <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Type</th>
                    <th>Cash</th>
                    <th>GCash</th>
                    <th>Total</th>
                    <th>Reference / Note</th>
                    <th>Recorded</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td><?= (int) ($entry['entry_sequence'] ?? 0) ?></td>
                        <td><?= esc((string) ($entry['entry_type'] ?? 'ENTRY')) ?></td>
                        <td>PHP <?= number_format((float) ($entry['cash_remitted'] ?? 0), 2) ?></td>
                        <td>PHP <?= number_format((float) ($entry['gcash_remitted'] ?? 0), 2) ?></td>
                        <td>PHP <?= number_format((float) ($entry['total_remitted'] ?? 0), 2) ?></td>
                        <td>
                            <div><?= esc((string) ($entry['gcash_reference'] ?? '-')) ?></div>
                            <?php if (! empty($entry['notes'])): ?>
                                <div class="small text-muted"><?= esc((string) $entry['notes']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= esc((string) ($entry['created_at'] ?? '-')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($entries === []): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No remittance pieces recorded yet for this rider-day.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
