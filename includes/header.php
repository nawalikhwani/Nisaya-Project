<?php
require_once __DIR__ . '/../config/app.php';

$pageTitle = $pageTitle ?? 'NISAYA';
$active = $active ?? basename(dirname($_SERVER['SCRIPT_NAME']));
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/jpeg"
        href="../assets/img/ikon.jpeg">
    <title><?= e($pageTitle) ?> | NISAYA</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>

<body>

    <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
        ☰
    </button>

    <aside class="sidebar" id="sidebar">

        <a href="../dashboard/index.php" class="sidebar-brand">
            <span class="brand-icon">🌸</span>
            <span class="brand-text">NISAYA</span>
        </a>

        <div class="sidebar-menu">

            <small class="menu-label">MENU UTAMA</small>

            <a href="../dashboard/index.php" class="sidebar-link <?= $active === 'dashboard' ? 'active' : '' ?>" title="Dashboard">
                <span>⌂</span><span class="link-text">Dashboard</span>
            </a>

            <a href="../calendar/index.php" class="sidebar-link <?= $active === 'calendar' ? 'active' : '' ?>" title="Kalender">
                <span>▦</span><span class="link-text">Kalender</span>
            </a>

            <a href="../history/index.php" class="sidebar-link <?= $active === 'history' ? 'active' : '' ?>" title="Riwayat">
                <span>◷</span><span class="link-text">Riwayat</span>
            </a>

            <a href="../profile/index.php" class="sidebar-link <?= $active === 'profile' ? 'active' : '' ?>" title="Profil">
                <span>♙</span><span class="link-text">Profil</span>
            </a>

            <small class="menu-label">INFORMASI</small>

            <a href="../education/index.php" class="sidebar-link <?= $active === 'education' ? 'active' : '' ?>" title="Edukasi">
                <span>♡</span><span class="link-text">Edukasi</span>
            </a>

            <a href="../notifications/index.php" class="sidebar-link <?= $active === 'notifications' ? 'active' : '' ?>" title="Notifikasi">
                <span>♧</span><span class="link-text">Notifikasi</span>
            </a>

            <?php if (is_admin()): ?>
                <small class="menu-label" style="color: #a36f91; font-weight: 700;">ADMINISTRATOR</small>

                <a href="../admin/index.php" class="sidebar-link <?= strpos($active, 'admin') === 0 ? 'active' : '' ?>" title="Panel Admin">
                    <span>🛡️</span><span class="link-text fw-semibold">Dashboard Admin</span>
                </a>
            <?php endif; ?>

        </div>


        <div class="sidebar-bottom">

            <form action="../auth/logout.php" method="POST">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <button
                    type="button"
                    class="sidebar-link logout-link"
                    title="Keluar"
                    data-bs-toggle="modal"
                    data-bs-target="#logoutModal">
                    <span>↪</span>
                    <span class="link-text">Keluar</span>
                </button>
            </form>

            <small class="sidebar-description">
                Catat, pahami, dan kenali siklusmu.
            </small>

        </div>


    </aside>

    <main class="app-main py-4">

        <?php if ($m = flash('success')): ?>
            <div class="alert alert-success"><?= e($m) ?></div>
        <?php endif; ?>

        <?php if ($m = flash('error')): ?>
            <div class="alert alert-danger"><?= e($m) ?></div>
        <?php endif; ?>