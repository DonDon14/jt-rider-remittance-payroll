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
$operationsBadge = (int) ($pendingSubmissionCount ?? 0) + (int) ($pendingCorrectionCount ?? 0);
$navGroups = [
    [
        'key' => 'operations',
        'label' => 'Operations',
        'icon' => 'bi-gear-wide-connected',
        'badge' => $operationsBadge,
        'tabs' => ['deliveries', 'remittances', 'shortages', 'corrections', 'announcements'],
        'items' => [
            ['key' => 'deliveries', 'label' => 'Deliveries', 'icon' => 'bi-box-seam-fill', 'url' => site_url('/admin/deliveries')],
            ['key' => 'remittances', 'label' => 'Remittances', 'icon' => 'bi-cash-stack', 'url' => site_url('/admin/remittances'), 'badge' => (int) ($pendingSubmissionCount ?? 0), 'badgeClass' => 'admin-sidebar-badge-warning'],
            ['key' => 'shortages', 'label' => 'Shortages', 'icon' => 'bi-exclamation-diamond-fill', 'url' => site_url('/admin/shortages')],
            ['key' => 'corrections', 'label' => 'Corrections', 'icon' => 'bi-pencil-square', 'url' => site_url('/admin/corrections'), 'badge' => (int) ($pendingCorrectionCount ?? 0), 'badgeClass' => 'admin-sidebar-badge-alert'],
            ['key' => 'announcements', 'label' => 'Announcements', 'icon' => 'bi-megaphone-fill', 'url' => site_url('/admin/announcements')],
        ],
    ],
    [
        'key' => 'payroll_group',
        'label' => 'Payroll',
        'icon' => 'bi-receipt-cutoff',
        'tabs' => ['payroll', 'adjustments'],
        'items' => [
            ['key' => 'payroll', 'label' => 'Payroll Runs', 'icon' => 'bi-receipt-cutoff', 'url' => site_url('/admin/payroll')],
            ['key' => 'adjustments', 'label' => 'Adjustments', 'icon' => 'bi-sliders2-vertical', 'url' => site_url('/admin/adjustments')],
        ],
    ],
    [
        'key' => 'reports',
        'label' => 'Reports',
        'icon' => 'bi-file-earmark-bar-graph-fill',
        'tabs' => ['history', 'analytics', 'activity'],
        'items' => [
            ['key' => 'history', 'label' => 'History', 'icon' => 'bi-clock-history', 'url' => site_url('/admin/history')],
            ['key' => 'analytics', 'label' => 'Analytics', 'icon' => 'bi-bar-chart-fill', 'url' => site_url('/admin/analytics')],
            ['key' => 'activity', 'label' => 'Activity Log', 'icon' => 'bi-journal-text', 'url' => site_url('/admin/activity')],
        ],
    ],
];
?>

<?php if ($isAdminShell): ?>
    <div class="admin-layout">
        <aside class="admin-sidebar" data-admin-sidebar>
            <div>
                <div class="admin-sidebar-brand-wrap">
                    <a class="admin-sidebar-brand" href="<?= site_url('/admin') ?>">
                        <span class="admin-sidebar-brand-mark">J&amp;T</span>
                        <span class="admin-sidebar-label">Claveria Branch</span>
                    </a>
                    <button type="button" class="btn admin-shell-toggle d-none d-lg-inline-flex" data-sidebar-toggle aria-label="Toggle sidebar">
                        <i class="bi bi-layout-sidebar-inset"></i>
                    </button>
                </div>
                <nav class="admin-sidebar-nav">
                    <a href="<?= site_url('/admin') ?>" class="admin-sidebar-link <?= $activeTab === 'dashboard' ? 'is-active' : '' ?>">
                        <i class="bi bi-grid-1x2-fill"></i>
                        <span class="admin-sidebar-label">Dashboard</span>
                    </a>
                    <a href="<?= site_url('/admin/riders') ?>" class="admin-sidebar-link <?= $activeTab === 'riders' ? 'is-active' : '' ?>">
                        <i class="bi bi-people-fill"></i>
                        <span class="admin-sidebar-label">Riders</span>
                    </a>

                    <?php foreach ($navGroups as $group): ?>
                        <?php $groupOpen = in_array($activeTab, $group['tabs'], true); ?>
                        <div class="admin-sidebar-group <?= $groupOpen ? 'is-open' : '' ?>" data-nav-group data-nav-group-key="<?= esc($group['key']) ?>" data-default-open="<?= $groupOpen ? '1' : '0' ?>">
                            <button type="button" class="admin-sidebar-link admin-sidebar-group-toggle <?= $groupOpen ? 'is-active' : '' ?>" data-nav-group-toggle aria-expanded="<?= $groupOpen ? 'true' : 'false' ?>">
                                <i class="bi <?= esc($group['icon']) ?>"></i>
                                <span class="admin-sidebar-label"><?= esc($group['label']) ?></span>
                                <?php if (! empty($group['badge'])): ?>
                                    <span class="badge admin-sidebar-badge <?= esc($group['badgeClass'] ?? 'admin-sidebar-badge-warning') ?>"><?= (int) $group['badge'] ?></span>
                                <?php endif; ?>
                                <i class="bi bi-chevron-down admin-sidebar-group-caret"></i>
                            </button>
                            <div class="admin-sidebar-subnav" data-nav-group-panel>
                                <?php foreach ($group['items'] as $item): ?>
                                    <a href="<?= $item['url'] ?>" class="admin-sidebar-link admin-sidebar-sublink <?= $activeTab === $item['key'] ? 'is-active' : '' ?>">
                                        <i class="bi <?= esc($item['icon']) ?>"></i>
                                        <span class="admin-sidebar-label"><?= esc($item['label']) ?></span>
                                        <?php if (! empty($item['badge'])): ?>
                                            <span class="badge admin-sidebar-badge <?= esc($item['badgeClass'] ?? 'admin-sidebar-badge-warning') ?>"><?= (int) $item['badge'] ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </nav>
            </div>
            <div class="admin-sidebar-footer">
                <div class="admin-sidebar-footer-label">Account</div>
                <a href="<?= site_url('/admin/settings') ?>" class="admin-sidebar-link <?= $activeTab === 'settings' ? 'is-active' : '' ?>">
                    <i class="bi bi-gear-fill"></i>
                    <span class="admin-sidebar-label">Settings</span>
                </a>
                <a href="<?= site_url('/change-password') ?>" class="admin-sidebar-link">
                    <i class="bi bi-key-fill"></i>
                    <span class="admin-sidebar-label">Change Password</span>
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
                <div class="admin-topbar-actions">
                    <?php if (! empty($queueSummary)): ?>
                        <div class="admin-queue-summary d-none d-lg-flex">
                            <span class="admin-queue-chip admin-queue-chip-warning">Requests: <?= (int) ($queueSummary['pending_submissions'] ?? 0) ?></span>
                            <span class="admin-queue-chip admin-queue-chip-alert">Corrections: <?= (int) ($queueSummary['pending_corrections'] ?? 0) ?></span>
                            <span class="admin-queue-chip admin-queue-chip-neutral">Overdue: <?= (int) ($queueSummary['overdue_remittances'] ?? 0) ?></span>
                        </div>
                    <?php endif; ?>
                    <button type="button" class="btn admin-shell-toggle d-inline-flex d-lg-none" data-sidebar-toggle aria-label="Open sidebar">
                        <i class="bi bi-layout-sidebar-inset"></i>
                    </button>
                </div>
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
                    <a href="<?= site_url('/change-password') ?>" class="btn btn-sm btn-outline-light">Change Password</a>
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






