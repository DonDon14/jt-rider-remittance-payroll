<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-hero">
    <div>
        <h2 class="mb-0">Announcements</h2>
        <p>Publish branch-wide notices that riders can immediately see in their portal.</p>
    </div>
    <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">New Announcement</button>
</div>

<div class="card">
    <div class="card-header fw-semibold">Announcement Board</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Published</th>
                    <th>Expires</th>
                    <th>Title</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($announcements as $announcement): ?>
                    <tr>
                        <td><?= esc(date('Y-m-d', strtotime((string) $announcement['published_at']))) ?></td>
                        <td><?= ! empty($announcement['expires_at']) ? esc(date('Y-m-d', strtotime((string) $announcement['expires_at']))) : 'No expiry' ?></td>
                        <td><?= esc($announcement['title']) ?></td>
                        <td><?= esc($announcement['message']) ?></td>
                        <td><span class="badge <?= ! empty($announcement['is_active']) ? 'badge-over' : 'badge-short' ?>"><?= ! empty($announcement['is_active']) ? 'ACTIVE' : 'INACTIVE' ?></span></td>
                        <td><button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#editAnnouncementModal<?= (int) $announcement['id'] ?>">Edit</button></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($announcements)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No announcements yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addAnnouncementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?= site_url('/admin/announcements') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required maxlength="150">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Message</label>
                        <textarea name="message" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Publish Date</label>
                        <input type="date" name="published_at" class="form-control" value="<?= esc($today) ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expires_at" class="form-control">
                        <div class="form-text">Leave blank if the announcement should stay visible until manually disabled.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="is_active" class="form-select" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <button class="btn btn-dark w-100">Publish Announcement</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php foreach ($announcements as $announcement): ?>
    <div class="modal fade" id="editAnnouncementModal<?= (int) $announcement['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="<?= site_url('/admin/announcements/' . (int) $announcement['id']) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" value="<?= esc($announcement['title']) ?>" required maxlength="150">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="4" required><?= esc($announcement['message']) ?></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Publish Date</label>
                            <input type="date" name="published_at" class="form-control" value="<?= esc(date('Y-m-d', strtotime((string) $announcement['published_at']))) ?>" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expires_at" class="form-control" value="<?= ! empty($announcement['expires_at']) ? esc(date('Y-m-d', strtotime((string) $announcement['expires_at']))) : '' ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="is_active" class="form-select" required>
                                <option value="1" <?= ! empty($announcement['is_active']) ? 'selected' : '' ?>>Active</option>
                                <option value="0" <?= empty($announcement['is_active']) ? 'selected' : '' ?>>Inactive</option>
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
