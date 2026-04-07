<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Login') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= base_url('assets/css/app.css') ?>" rel="stylesheet">
</head>
<body>
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card">
                <div class="card-body p-4">
                    <h1 class="h3 mb-1">J&T Rider Remittance & Payroll</h1>
                    <p class="text-muted mb-4">Log in as admin or rider to continue.</p>

                    <?php if (session()->getFlashdata('success')): ?>
                        <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
                    <?php endif; ?>
                    <?php if (session()->getFlashdata('error')): ?>
                        <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
                    <?php endif; ?>

                    <form method="post" action="<?= site_url('/login') ?>">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="<?= old('username') ?>" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button class="btn btn-dark w-100">Log In</button>
                    </form>

                    <hr>
                    <div class="small text-muted">
                        <div>Initial credentials are issued by the administrator during setup or password reset.</div>
                        <div class="mt-2">Temporary passwords require an immediate password change after login.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>

