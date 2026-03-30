<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-hero">
    <div>
        <h2 class="mb-0">Delivery Recording</h2>
        <p>Record one rider-day entry with parcel counts, the commission used for that area, salary earning basis, and that day's expected cash remittance.</p>
    </div>
    <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#deliveryModal">New Delivery Record</button>
</div>

<div class="card">
    <div class="card-header fw-semibold">Recent Delivery Records</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Rider</th>
                    <th>Successful</th>
                    <th>Commission</th>
                    <th>Salary Earning</th>
                    <th>Expected Remittance</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dailyRecords as $record): ?>
                    <tr>
                        <td><?= esc($record['delivery_date']) ?></td>
                        <td><?= esc($record['rider_code']) ?> - <?= esc($record['name']) ?></td>
                        <td><?= (int) $record['successful_deliveries'] ?> / <?= (int) $record['allocated_parcels'] ?></td>
                        <td>PHP <?= number_format((float) ($record['commission_rate'] ?? 0), 2) ?></td>
                        <td>PHP <?= number_format((float) $record['total_due'], 2) ?></td>
                        <td>PHP <?= number_format((float) ($record['expected_remittance'] ?? 0), 2) ?></td>
                        <td><a href="<?= site_url('/admin/remittance/' . (int) $record['id']) ?>" class="btn btn-sm btn-outline-primary">Collect</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($dailyRecords)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No records yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="deliveryModal" tabindex="-1" aria-hidden="true">
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
                            <div class="form-text">Enter the rider's total cash collection for the day based on the parcels delivered in that area.</div>
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

<?= $this->endSection() ?>
