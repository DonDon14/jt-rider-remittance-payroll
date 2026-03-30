<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'J&T Rider Remittance & Payroll') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= base_url('assets/css/app.css') ?>" rel="stylesheet">
</head>
<body class="<?= ! empty($activeTab) ? 'admin-shell' : '' ?>">
<?php
$role = session()->get('role');
$homeUrl = $role === 'admin' ? site_url('/admin') : ($role === 'rider' ? site_url('/rider-dashboard') : site_url('/login'));
$isAdminShell = ! empty($activeTab);
$adminNav = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill', 'url' => site_url('/admin')],
    ['key' => 'riders', 'label' => 'Riders', 'icon' => 'bi-people-fill', 'url' => site_url('/admin/riders')],
    ['key' => 'deliveries', 'label' => 'Deliveries', 'icon' => 'bi-box-seam-fill', 'url' => site_url('/admin/deliveries')],
    ['key' => 'history', 'label' => 'History', 'icon' => 'bi-clock-history', 'url' => site_url('/admin/history')],
    ['key' => 'remittances', 'label' => 'Remittances', 'icon' => 'bi-cash-stack', 'url' => site_url('/admin/remittances')],
    ['key' => 'shortages', 'label' => 'Shortages', 'icon' => 'bi-exclamation-diamond-fill', 'url' => site_url('/admin/shortages')],
    ['key' => 'announcements', 'label' => 'Announcements', 'icon' => 'bi-megaphone-fill', 'url' => site_url('/admin/announcements')],
    ['key' => 'adjustments', 'label' => 'Adjustments', 'icon' => 'bi-sliders2-vertical', 'url' => site_url('/admin/adjustments')],
    ['key' => 'analytics', 'label' => 'Analytics', 'icon' => 'bi-bar-chart-fill', 'url' => site_url('/admin/analytics')],
    ['key' => 'payroll', 'label' => 'Payroll', 'icon' => 'bi-receipt-cutoff', 'url' => site_url('/admin/payroll')],
];
?>

<?php if ($isAdminShell): ?>
    <div class="admin-layout">
        <aside class="admin-sidebar" data-admin-sidebar>
            <div>
                <div class="admin-sidebar-brand-wrap">
                    <a class="admin-sidebar-brand" href="<?= site_url('/admin') ?>">
                        <span class="admin-sidebar-brand-mark">J&amp;T</span>
                        <span class="admin-sidebar-label">Home Claveria Branch</span>
                    </a>
                    <button type="button" class="btn admin-shell-toggle d-none d-lg-inline-flex" data-sidebar-toggle aria-label="Toggle sidebar">
                        <i class="bi bi-layout-sidebar-inset"></i>
                    </button>
                </div>
                <nav class="admin-sidebar-nav">
                    <?php foreach ($adminNav as $item): ?>
                        <a href="<?= $item['url'] ?>" class="admin-sidebar-link <?= $activeTab === $item['key'] ? 'is-active' : '' ?>">
                            <i class="bi <?= esc($item['icon']) ?>"></i>
                            <span class="admin-sidebar-label"><?= esc($item['label']) ?></span>
                            <?php if ($item['key'] === 'remittances' && ! empty($pendingSubmissionCount)): ?>
                                <span class="badge text-bg-warning text-dark admin-sidebar-badge"><?= (int) $pendingSubmissionCount ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <div class="admin-sidebar-footer">
                <a href="<?= site_url('/admin/settings') ?>" class="admin-sidebar-link <?= $activeTab === 'settings' ? 'is-active' : '' ?>">
                    <i class="bi bi-gear-fill"></i>
                    <span class="admin-sidebar-label">Settings</span>
                </a>
                <a href="<?= site_url('/logout') ?>" class="admin-sidebar-link admin-sidebar-link-secondary">
                    <i class="bi bi-box-arrow-right"></i>
                    <span class="admin-sidebar-label">Logout</span>
                </a>
            </div>
        </aside>
        <div class="admin-main">
            <header class="admin-topbar">
                <div>
                    <div class="admin-topbar-title"><?= esc($title ?? 'Admin') ?></div>
                    <div class="admin-topbar-subtitle">Signed in as <?= esc((string) session()->get('username')) ?></div>
                </div>
                <button type="button" class="btn admin-shell-toggle d-inline-flex d-lg-none" data-sidebar-toggle aria-label="Open sidebar">
                    <i class="bi bi-layout-sidebar-inset"></i>
                </button>
            </header>
            <main class="admin-content">
                <?php if (session()->getFlashdata('success')): ?>
                    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
                <?php endif; ?>
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
                <?php endif; ?>
                <?= $this->renderSection('content') ?>
            </main>
        </div>
    </div>
<?php else: ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= $homeUrl ?>">J&T Rider Remittance & Payroll</a>
            <?php if (session()->get('isLoggedIn')): ?>
                <div class="d-flex align-items-center gap-3 text-white">
                    <span class="small text-white-50"><?= esc((string) session()->get('username')) ?> | <?= strtoupper(esc((string) $role)) ?></span>
                    <a href="<?= site_url('/logout') ?>" class="btn btn-sm btn-outline-light">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </nav>

    <main class="container py-4">
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>
        <?= $this->renderSection('content') ?>
    </main>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
