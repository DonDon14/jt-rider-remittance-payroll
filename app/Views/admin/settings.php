<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-hero">
    <div>
        <h2 class="mb-0">Settings</h2>
        <p>Manage rider commission rates with effective dates so historical delivery records keep the correct rate.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header fw-semibold">Update Commission Rate</div>
            <div class="card-body">
                <form method="post" action="<?= site_url('/admin/settings/commission') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Rider</label>
                        <div class="searchable-select" data-searchable-select>
                            <input type="text" class="form-control mb-2" placeholder="Search rider code or name" data-search-target>
                            <select name="rider_id" class="form-select" required data-search-source>
                                <option value="">Select rider</option>
                                <?php foreach ($riders as $rider): ?>
                                    <option value="<?= (int) $rider['id'] ?>"><?= esc($rider['rider_code']) ?> - <?= esc($rider['name']) ?> | Current: PHP <?= esc($rider['display_commission_rate']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Commission Rate (PHP)</label>
                        <input type="number" name="commission_rate" step="0.01" min="0.01" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Effective Date</label>
                        <input type="date" name="effective_date" value="<?= esc($today) ?>" class="form-control" required>
                        <div class="form-text">This rate will apply only to delivery records on this date and after.</div>
                    </div>
                    <button class="btn btn-dark w-100">Save Commission Change</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Current Rider Rates</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Rider</th>
                            <th>Current Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riders as $rider): ?>
                            <tr>
                                <td><?= esc($rider['rider_code']) ?> - <?= esc($rider['name']) ?></td>
                                <td>PHP <?= esc($rider['display_commission_rate']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header fw-semibold">Commission Change History</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Effective Date</th>
                            <th>Rider</th>
                            <th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rateHistory as $item): ?>
                            <tr>
                                <td><?= esc($item['effective_date']) ?></td>
                                <td><?= esc($item['rider_code']) ?> - <?= esc($item['name']) ?></td>
                                <td>PHP <?= number_format((float) $item['commission_rate'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rateHistory)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">No commission history yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
