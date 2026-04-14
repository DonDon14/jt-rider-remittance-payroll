<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Forgot Password') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= base_url('assets/css/app.css') ?>" rel="stylesheet">
</head>
<body>
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body p-4">
                    <h1 class="h3 mb-1">Forgot Password</h1>
                    <p class="text-muted mb-4">Verify your account details to issue a temporary password.</p>

                    <?php if (session()->getFlashdata('success')): ?>
                        <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
                    <?php endif; ?>
                    <?php if (session()->getFlashdata('error')): ?>
                        <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
                    <?php endif; ?>

                    <form method="post" action="<?= site_url('/forgot-password') ?>">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="<?= old('username') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rider Code (for rider accounts)</label>
                            <input type="text" name="rider_code" class="form-control" value="<?= old('rider_code') ?>" placeholder="e.g. R-1003">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Number (for rider accounts)</label>
                            <input type="text" name="contact_number" class="form-control" value="<?= old('contact_number') ?>" placeholder="e.g. 09170000000">
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Admin Recovery Key (for admin accounts)</label>
                            <input type="password" name="recovery_key" class="form-control password-field" autocomplete="off">
                        </div>
                        <button class="btn btn-dark w-100">Issue Temporary Password</button>
                    </form>

                    <div class="mt-3">
                        <a href="<?= site_url('/login') ?>" class="small">Back to login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
document.querySelectorAll('.password-field').forEach(function (field) {
    const wrapper = document.createElement('div');
    wrapper.className = 'input-group';
    field.parentNode.insertBefore(wrapper, field);
    wrapper.appendChild(field);

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'btn btn-outline-secondary';
    toggle.textContent = 'Show';
    toggle.addEventListener('click', function () {
        const showing = field.type === 'text';
        field.type = showing ? 'password' : 'text';
        toggle.textContent = showing ? 'Show' : 'Hide';
    });
    wrapper.appendChild(toggle);
});
</script>
</body>
</html>
