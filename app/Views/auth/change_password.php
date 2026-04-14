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
                    <div class="input-group">
                        <input type="password" name="current_password" class="form-control password-field" required>
                        <button type="button" class="btn btn-outline-secondary password-toggle">Show</button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <div class="input-group">
                        <input type="password" name="new_password" class="form-control password-field" minlength="8" required>
                        <button type="button" class="btn btn-outline-secondary password-toggle">Show</button>
                    </div>
                    <div class="form-text">Use at least 8 characters.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" name="confirm_password" class="form-control password-field" minlength="8" required>
                        <button type="button" class="btn btn-outline-secondary password-toggle">Show</button>
                    </div>
                </div>
                <button class="btn btn-dark w-100">Save New Password</button>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.password-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
        const field = button.closest('.input-group').querySelector('.password-field');
        const showing = field.type === 'text';
        field.type = showing ? 'password' : 'text';
        button.textContent = showing ? 'Show' : 'Hide';
    });
});
</script>

<?= $this->endSection() ?>
