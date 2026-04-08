<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-hero">
    <div>
        <h2 class="mb-0">Rider Management</h2>
        <p>Create rider profiles, update details, reset access, and maintain the rider information needed for payroll, contact tracing, and branch operations.</p>
    </div>
    <div class="d-flex gap-2">
        <form method="get" action="<?= site_url('/admin/riders') ?>" class="d-flex gap-2">
            <input type="text" name="q" value="<?= esc($search) ?>" class="form-control" placeholder="Search rider code, name, branch, or username">
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
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Rider</th>
                    <th>Branch</th>
                    <th>Contact</th>
                    <th>Commission</th>
                    <th>Status</th>
                    <th>Username</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($riders as $rider): ?>
                    <?php $photoUrl = ! empty($rider['profile_photo_path']) ? site_url((string) $rider['profile_photo_path']) : null; ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <?php if ($photoUrl): ?>
                                    <img src="<?= esc($photoUrl) ?>" alt="<?= esc($rider['name']) ?>" style="width:48px;height:48px;border-radius:50%;object-fit:cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:48px;height:48px;"><?= esc(strtoupper(substr((string) $rider['name'], 0, 1))) ?></div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-semibold"><?= esc($rider['rider_code']) ?> - <?= esc($rider['name']) ?></div>
                                    <div class="small text-muted"><?= esc($rider['address'] ?: '-') ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div><?= esc($rider['branch_name'] ?: '-') ?></div>
                            <div class="small text-muted">Hired <?= esc($rider['hire_date'] ?: '-') ?></div>
                        </td>
                        <td>
                            <div><?= esc($rider['contact_number'] ?: '-') ?></div>
                            <div class="small text-muted">Emergency: <?= esc($rider['emergency_contact_number'] ?: '-') ?></div>
                        </td>
                        <td>PHP <?= number_format((float) $rider['commission_rate'], 2) ?></td>
                        <td><span class="badge <?= ! empty($rider['is_active']) ? 'badge-over' : 'badge-short' ?>"><?= ! empty($rider['is_active']) ? 'ACTIVE' : 'INACTIVE' ?></span></td>
                        <td><?= esc($rider['username'] ?? strtolower((string) $rider['rider_code'])) ?></td>
                        <td>
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#editRiderModal<?= (int) $rider['id'] ?>">Edit</button>
                                <form method="post" action="<?= site_url('/admin/riders/' . (int) $rider['id'] . '/reset-password') ?>" onsubmit="return confirm('Generate a new temporary password for this rider?');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-warning">Reset Password</button>
                                </form>
                                <a href="<?= site_url('/rider/' . (int) $rider['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Open Rider Portal</a>
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
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Rider</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?= site_url('/admin/riders') ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Rider Code</label>
                            <input type="text" name="rider_code" class="form-control" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact_number" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" name="profile_photo" class="form-control" accept="image/png,image/jpeg,image/webp">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Birth Date</label>
                            <input type="date" name="birth_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Hire Date</label>
                            <input type="date" name="hire_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Branch</label>
                            <input type="text" name="branch_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Emergency Contact Number</label>
                            <input type="text" name="emergency_contact_number" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Government ID / Reference</label>
                            <input type="text" name="government_id_number" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Commission Per Parcel (PHP)</label>
                            <input type="number" step="0.01" min="0" name="commission_rate" class="form-control" value="13.00" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <button class="btn btn-success w-100 mt-3">Save Rider</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php foreach ($riders as $rider): ?>
    <div class="modal fade" id="editRiderModal<?= (int) $rider['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Rider</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="<?= site_url('/admin/riders/' . (int) $rider['id']) ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label">Rider Code</label>
                                <input type="text" name="rider_code" class="form-control" value="<?= esc($rider['rider_code']) ?>" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control" value="<?= esc($rider['name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Number</label>
                                <input type="text" name="contact_number" class="form-control" value="<?= esc($rider['contact_number']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Profile Photo</label>
                                <input type="file" name="profile_photo" class="form-control" accept="image/png,image/jpeg,image/webp">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="2"><?= esc($rider['address'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Birth Date</label>
                                <input type="date" name="birth_date" class="form-control" value="<?= esc($rider['birth_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Hire Date</label>
                                <input type="date" name="hire_date" class="form-control" value="<?= esc($rider['hire_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Branch</label>
                                <input type="text" name="branch_name" class="form-control" value="<?= esc($rider['branch_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Emergency Contact Name</label>
                                <input type="text" name="emergency_contact_name" class="form-control" value="<?= esc($rider['emergency_contact_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Emergency Contact Number</label>
                                <input type="text" name="emergency_contact_number" class="form-control" value="<?= esc($rider['emergency_contact_number'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Government ID / Reference</label>
                                <input type="text" name="government_id_number" class="form-control" value="<?= esc($rider['government_id_number'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="is_active" class="form-select" required>
                                    <option value="1" <?= ! empty($rider['is_active']) ? 'selected' : '' ?>>Active</option>
                                    <option value="0" <?= empty($rider['is_active']) ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="3"><?= esc($rider['notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <button class="btn btn-dark w-100 mt-3">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?= $this->endSection() ?>

