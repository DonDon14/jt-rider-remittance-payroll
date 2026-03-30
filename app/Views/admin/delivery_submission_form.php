<?= $this->extend($layout ?? 'layouts/main') ?>
<?= $this->section('content') ?>

<?php if (empty($isModal)): ?>
    <div class="page-hero">
        <div>
            <h2 class="mb-0">Review Rider Submission</h2>
            <p>Approve the rider-submitted delivery counts, set the final commission for that day, or reject the request with a note.</p>
        </div>
        <a href="<?= site_url('/admin/remittances') ?>" class="btn btn-outline-secondary">Back to Remittances</a>
    </div>
<?php else: ?>
    <div class="mb-3">
        <h3 class="mb-1">Review Rider Submission</h3>
        <p class="text-muted mb-0">Approve the rider-submitted delivery counts, set the final commission for that day, or reject the request with a note.</p>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-3"><strong>Rider:</strong> <?= esc($submission['rider_code']) ?> - <?= esc($submission['name']) ?></div>
            <div class="col-md-3"><strong>Date:</strong> <?= esc($submission['delivery_date']) ?></div>
            <div class="col-md-2"><strong>Allocated:</strong> <?= (int) $submission['allocated_parcels'] ?></div>
            <div class="col-md-2"><strong>Successful:</strong> <?= (int) $submission['successful_deliveries'] ?></div>
            <div class="col-md-2"><strong>Expected Remittance:</strong> PHP <?= number_format((float) ($submission['expected_remittance'] ?? 0), 2) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= site_url('/admin/delivery-submissions/' . (int) $submission['id'] . '/approve') ?><?= ! empty($isModal) ? '?modal=1' : '' ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label"><strong>Commission Used (PHP)</strong></label>
                <input type="number" step="0.01" min="0.01" name="commission_rate" value="<?= esc(number_format((float) ($submission['commission_rate'] ?? 13), 2, '.', '')) ?>" class="form-control" required>
                <div class="form-text">Set the final commission based on the area the rider actually handled that day.</div>
            </div>
            <div class="mb-3">
                <label class="form-label"><strong>Rider Notes</strong></label>
                <textarea class="form-control" rows="4" readonly><?= esc($submission['notes'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label"><strong>Rejection Note</strong></label>
                <textarea name="rejection_note" class="form-control" rows="3" placeholder="Optional note if you are rejecting this request."></textarea>
            </div>
            <?php $rejectUrl = site_url('/admin/delivery-submissions/' . (int) $submission['id'] . '/reject') . (! empty($isModal) ? '?modal=1' : ''); ?>
            <div class="d-flex gap-2">
                <button class="btn btn-dark flex-fill">Approve And Continue To Collection</button>
                <button class="btn btn-outline-danger" type="submit" formaction="<?= $rejectUrl ?>" formnovalidate onclick="return confirm('Reject this rider submission?');">Reject</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
