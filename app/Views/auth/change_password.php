<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="container py-4" style="max-width: 560px;">
    <div class="card">
        <div class="card-body p-4">
            <h2 class="h4 mb-1">Change Password</h2>
            <p class="text-muted mb-4">Update your account password before continuing to the system.</p>

            <form method="post" action="<?= site_url('/change-password') ?>">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" minlength="8" required>
                    <div class="form-text">Use at least 8 characters.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                </div>
                <button class="btn btn-dark w-100">Save New Password</button>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
