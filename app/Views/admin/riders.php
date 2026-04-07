<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-hero">
    <div>
        <h2 class="mb-0">Rider Management</h2>
        <p>Create rider profiles, update details, reset access, and control whether a rider is active for payroll and daily operations.</p>
    </div>
    <div class="d-flex gap-2">
        <form method="get" action="<?= site_url('/admin/riders') ?>" class="d-flex gap-2">
            <input type="text" name="q" value="<?= esc($search) ?>" class="form-control" placeholder="Search rider code, name, or username">
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                <option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
                <option value="INACTIVE" <?= $status === 'INACTIVE' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <button class="btn btn-primary">Search</button>
        </form>
        <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addRiderModal">Add Rider</button>
    </div>
</div>

<div class="card">
    <div class="card-header fw-semibold">Rider Directory</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Commission</th>
                    <th>Status</th>
                    <th>Username</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($riders as $rider): ?>
                    <tr>
                        <td><?= esc($rider['rider_code']) ?></td>
                        <td><?= esc($rider['name']) ?></td>
                        <td><?= esc($rider['contact_number'] ?: '-') ?></td>
                        <td>PHP <?= number_format((float) $rider['commission_rate'], 2) ?></td>
                        <td><span class="badge <?= ! empty($rider['is_active']) ? 'badge-over' : 'badge-short' ?>"><?= ! empty($rider['is_active']) ? 'ACTIVE' : 'INACTIVE' ?></span></td>
                        <td><?= esc($rider['username'] ?? strtolower((string) $rider['rider_code'])) ?></td>
                        <td>
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#editRiderModal<?= (int) $rider['id'] ?>">Edit</button>
                                <form method="post" action="<?= site_url('/admin/riders/' . (int) $rider['id'] . '/reset-password') ?>" onsubmit="return confirm('Reset this rider password to the default format based on the rider code?');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-warning">Reset Password</button>
                                </form>
                                <a href="<?= site_url('/rider/' . (int) $rider['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Portal</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($riders)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No riders found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (! empty($pager)): ?>
    <div class="mt-3">
        <?= $pager->links($pageGroup) ?>
    </div>
<?php endif; ?>

<div class="modal fade" id="addRiderModal" tabindex="-1" aria-hidden="true">
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

<?php foreach ($riders as $rider): ?>
    <div class="modal fade" id="editRiderModal<?= (int) $rider['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Rider</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="<?= site_url('/admin/riders/' . (int) $rider['id']) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label">Rider Code</label>
                            <input type="text" name="rider_code" class="form-control" value="<?= esc($rider['rider_code']) ?>" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" value="<?= esc($rider['name']) ?>" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact_number" class="form-control" value="<?= esc($rider['contact_number']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="is_active" class="form-select" required>
                                <option value="1" <?= ! empty($rider['is_active']) ? 'selected' : '' ?>>Active</option>
                                <option value="0" <?= empty($rider['is_active']) ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <button class="btn btn-dark w-100">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?= $this->endSection() ?>
