<?= $this->extend($layout ?? 'layouts/main') ?>
<?= $this->section('content') ?>

<?php if (empty($isModal)): ?>
    <div class="page-hero">
        <div>
            <h2 class="mb-0">Remittance Entry</h2>
            <p>Compare the rider's actual remittance against the day's expected cash collection.</p>
        </div>
        <a href="<?= site_url('/admin/remittances') ?>" class="btn btn-outline-secondary">Back to Remittances</a>
    </div>
<?php else: ?>
    <div class="mb-3">
        <h3 class="mb-1">Remittance Entry</h3>
        <p class="text-muted mb-0">Compare the rider's actual remittance against the day's expected cash collection.</p>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-3"><strong>Rider:</strong> <?= esc($delivery['rider_code']) ?> - <?= esc($delivery['name']) ?></div>
            <div class="col-md-3"><strong>Date:</strong> <?= esc($delivery['delivery_date']) ?></div>
            <div class="col-md-3"><strong>Expected Remittance:</strong> PHP <?= number_format((float) ($delivery['expected_remittance'] ?? 0), 2) ?></div>
            <div class="col-md-3"><strong>Salary Earning:</strong> PHP <?= number_format((float) $delivery['total_due'], 2) ?></div>
            <div class="col-12"><strong>Selected Remittance Account:</strong> <?= esc(trim((string) ($delivery['remittance_account_name'] ?? '')) !== '' ? (($delivery['remittance_account_name'] ?? '') . (! empty($delivery['remittance_account_number']) ? ' (' . $delivery['remittance_account_number'] . ')' : '')) : '-') ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= site_url('/admin/remittance/' . (int) $delivery['id']) ?><?= ! empty($isModal) ? '?modal=1' : '' ?>" data-remittance-form>
            <?= csrf_field() ?>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label"><strong>Expected Remittance</strong></label>
                    <input type="number" step="0.01" value="<?= esc(number_format((float) ($delivery['expected_remittance'] ?? 0), 2, '.', '')) ?>" class="form-control" readonly data-supposed-remittance>
                    <div class="form-text">This comes from the rider-day record and is the amount the rider should remit.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><strong>GCash Remitted</strong></label>
                    <input type="number" step="0.01" min="0" name="gcash_remitted" placeholder="0.00" value="<?= isset($remittance['gcash_remitted']) && $remittance['gcash_remitted'] !== null ? esc($remittance['gcash_remitted']) : '' ?>" class="form-control" data-gcash-remitted>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><strong>Cash Remitted</strong></label>
                    <input type="number" step="0.01" min="0" name="cash_remitted" placeholder="Auto-filled from denominations below" value="<?= isset($remittance['cash_remitted']) && $remittance['cash_remitted'] !== null ? esc($remittance['cash_remitted']) : '' ?>" class="form-control" data-cash-remitted>
                    <div class="form-text">This updates live from the denomination counts below. If no denominations are entered, you can still type the cash amount manually.</div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label"><strong>GCash Reference</strong></label>
                    <input type="text" name="gcash_reference" maxlength="100" placeholder="Reference number, sender, or note" value="<?= ! empty($remittance['gcash_reference']) ? esc($remittance['gcash_reference']) : '' ?>" class="form-control">
                </div>
            </div>

            <hr>
            <h5 class="mb-3">Cash Denomination Count (Optional)</h5>

            <div class="row g-2">
                <?php foreach ($denominations as $field => $value): ?>
                    <div class="col-md-4">
                        <label class="form-label"><?= $value < 1 ? '25c' : 'PHP ' . number_format($value, 2) ?></label>
                        <input type="number" min="0" name="<?= esc($field) ?>" value="<?= (int) ($remittance[$field] ?? 0) ?>" class="form-control" data-denom data-value="<?= esc($value) ?>">
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4 d-flex flex-wrap gap-2 align-items-center">
                <div class="alert alert-light border mb-0">Cash from Denominations: <strong data-cash-total>PHP 0.00</strong></div>
                <div class="alert alert-light border mb-0">Grand Total: <strong data-total-remitted>PHP 0.00</strong></div>
                <div class="alert alert-light border mb-0">Status: <span data-variance-status class="badge text-bg-secondary">PENDING</span></div>
                <button class="btn btn-primary">Save Remittance</button>
                <?php if (! empty($remittance['id'])): ?>
                    <a href="<?= site_url('/admin/remittance/pdf/' . (int) $remittance['id']) ?>" class="btn btn-dark" target="_blank">Download Receipt PDF</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

