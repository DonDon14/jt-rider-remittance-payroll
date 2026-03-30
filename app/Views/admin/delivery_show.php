<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-hero">
    <div>
        <h2 class="mb-0">Delivery Record Detail</h2>
        <p>Review the rider-day record, cash remittance result, and payroll assignment.</p>
    </div>
    <a href="<?= site_url('/admin/history') ?>" class="btn btn-outline-secondary">Back to History</a>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header fw-semibold">Delivery Details</div>
            <div class="card-body">
                <div><strong>Rider:</strong> <?= esc($record['rider_code']) ?> - <?= esc($record['name']) ?></div>
                <div><strong>Contact:</strong> <?= esc($record['contact_number'] ?: '-') ?></div>
                <div><strong>Date:</strong> <?= esc($record['delivery_date']) ?></div>
                <div><strong>Allocated:</strong> <?= (int) $record['allocated_parcels'] ?></div>
                <div><strong>Successful:</strong> <?= (int) $record['successful_deliveries'] ?></div>
                <div><strong>Failed:</strong> <?= (int) $record['failed_deliveries'] ?></div>
                <div><strong>Commission Rate Applied:</strong> PHP <?= number_format((float) ($record['applied_commission_rate'] ?? 0), 2) ?></div>
                <div><strong>Salary Earning:</strong> PHP <?= number_format((float) $record['total_due'], 2) ?></div>
                <div><strong>Expected Remittance:</strong> PHP <?= number_format((float) ($record['expected_remittance'] ?? 0), 2) ?></div>
                <div><strong>Notes:</strong> <?= esc($record['notes'] ?: '-') ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Remittance Status</div>
            <div class="card-body">
                <?php if ($remittance): ?>
                    <div><strong>Status:</strong> <?= esc($remittance['variance_type']) ?></div>
                    <div><strong>Expected Remittance:</strong> PHP <?= number_format((float) ($remittance['supposed_remittance'] ?? ($record['expected_remittance'] ?? 0)), 2) ?></div>
                    <div><strong>Actual Remitted:</strong> <?= $remittance['actual_remitted'] !== null ? 'PHP ' . number_format((float) $remittance['actual_remitted'], 2) : 'PHP ' . number_format((float) $remittance['total_remitted'], 2) ?></div>
                    <div><strong>Variance:</strong> PHP <?= number_format((float) $remittance['variance_amount'], 2) ?></div>
                    <div class="mt-3"><a href="<?= site_url('/admin/remittance/' . (int) $record['id']) ?>" class="btn btn-sm btn-outline-primary">Open Remittance</a></div>
                <?php else: ?>
                    <div class="text-muted">No remittance record yet.</div>
                    <div class="mt-3"><a href="<?= site_url('/admin/remittance/' . (int) $record['id']) ?>" class="btn btn-sm btn-primary">Collect Remittance</a></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header fw-semibold">Payroll Assignment</div>
            <div class="card-body">
                <?php if ($payroll): ?>
                    <div><strong>Coverage:</strong> <?= esc($payroll['start_date'] ?? $payroll['month_year']) ?> to <?= esc($payroll['end_date'] ?? $payroll['month_year']) ?></div>
                    <div><strong>Net Pay Snapshot:</strong> PHP <?= number_format((float) $payroll['net_pay'], 2) ?></div>
                    <div class="mt-3"><a href="<?= site_url('/admin/payroll/' . (int) $payroll['id'] . '/pdf') ?>" target="_blank" class="btn btn-sm btn-outline-dark">Open Payslip</a></div>
                <?php else: ?>
                    <div class="text-muted">This delivery day has not yet been assigned to a payroll batch.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
