<?php
require_once __DIR__ . '/../../config/app.php';
require_admin();

$pageTitle = $pageTitle ?? 'Dashboard Admin';
$adminActive = $adminActive ?? 'dashboard';

// Ambil info admin yang sedang login
$currentAdmin = current_user();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/jpeg" href="../assets/img/ikon.jpeg">
    <title><?= e($pageTitle) ?> | Admin NISAYA</title>

    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="../assets/style.css" rel="stylesheet">

    <style>
        /* Penyesuaian Tema Admin Selaras dengan Halaman Pengguna NISAYA */
        :root {
            --pink: #f3c9d8;
            --lav: #dcccf2;
            --ink: #594765;
            --bg: #faf7fc;
            --accent: #a36f91;
            --accent-hover: #886b98;
            --soft-border: #f0e3f0;
        }

        body {
            background-color: var(--bg);
            color: #51465b;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        /* Admin user pill di dalam sidebar */
        .sidebar-user-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            margin: 0 4px 14px 4px;
            background: #fdf8fb;
            border: 1px solid #f2e3ed;
            border-radius: 14px;
            transition: all 0.2s ease;
        }

        .avatar-sidebar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #f3c9d8;
            flex-shrink: 0;
        }

        .avatar-sidebar-placeholder {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #fce6ef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .sidebar-user-info {
            overflow: hidden;
            line-height: 1.25;
        }

        .sidebar-user-name {
            font-size: 13px;
            font-weight: 700;
            color: #594765;
        }

        .sidebar-user-role {
            font-size: 10px;
            color: #a36f91;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        body.sidebar-collapsed .sidebar-user-info {
            display: none;
        }

        body.sidebar-collapsed .sidebar-user-pill {
            justify-content: center;
            padding: 6px;
            margin: 0 0 14px 0;
        }

        /* Top strip admin header */
        .admin-topstrip {
            background: #ffffff;
            border: 1px solid #f0e3f0;
            border-radius: 18px;
            padding: 12px 20px;
            box-shadow: 0 4px 16px rgba(120, 97, 138, 0.04);
        }

        .admin-badge-pill {
            background: #f8eaf1;
            color: #a36f91;
            font-size: 11px;
            font-weight: 750;
            padding: 5px 12px;
            border-radius: 20px;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .topstrip-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 650;
            padding: 6px 14px;
            border-radius: 12px;
            color: #65566d;
            background: #ffffff;
            border: 1px solid #ede3ee;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .topstrip-btn:hover {
            background: #faf4fb;
            color: #a36f91;
            border-color: #f3c9d8;
        }

        .topstrip-btn.btn-mode-user {
            background: #f8eaf1;
            color: #a36f91;
            border-color: #f3c9d8;
        }

        .topstrip-btn.btn-mode-user:hover {
            background: #f3c9d8;
            color: #884b6f;
        }

        /* Admin UI Cards and Panels */
        .admin-card {
            background: #ffffff;
            border: 1px solid #f0e3f0;
            border-radius: 22px;
            padding: 24px;
            box-shadow: 0 5px 20px rgba(120, 97, 138, 0.05);
            margin-bottom: 24px;
        }

        .admin-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .admin-card-title {
            color: #594765;
            font-size: 1.15rem;
            font-weight: 750;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Stat Cards */
        .stat-card {
            position: relative;
            min-height: 160px;
            padding: 22px;
            border: 1px solid #f0e3f0;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 5px 18px rgba(120, 97, 138, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(120, 97, 138, 0.1);
        }

        .stat-card .stat-icon-badge {
            width: 46px;
            height: 46px;
            border-radius: 15px;
            display: grid;
            place-items: center;
            font-size: 22px;
            margin-bottom: 12px;
        }

        .stat-card .stat-title {
            color: #897a91;
            font-size: 11px;
            font-weight: 750;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .stat-card .stat-number {
            color: #594765;
            font-size: 26px;
            font-weight: 750;
            line-height: 1.2;
        }

        /* Admin Table */
        .admin-table-wrap {
            overflow-x: auto;
        }

        .admin-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .admin-table thead th {
            background-color: #faf5fc;
            color: #a18ba7;
            font-size: 11px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 13px 16px;
            border-top: 1px solid #f0e7f1;
            border-bottom: 1px solid #f0e7f1;
        }

        .admin-table tbody td {
            color: #65566d;
            font-size: 13.5px;
            padding: 14px 16px;
            vertical-align: middle;
            border-bottom: 1px solid #f5eff5;
        }

        .admin-table tbody tr:hover td {
            background-color: #fdf8fb;
        }

        /* Badges Selaras */
        .badge-status-active {
            background-color: #eaf8f0;
            color: #1b7a43;
            border: 1px solid #c2eecf;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 650;
        }

        .badge-status-inactive {
            background-color: #fdf2f4;
            color: #a34f7b;
            border: 1px solid #f8dce7;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 650;
        }

        .badge-role-admin {
            background-color: #f8eaf1;
            color: #a36f91;
            border: 1px solid #f3c9d8;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .badge-role-user {
            background-color: #eee9fb;
            color: #76618e;
            border: 1px solid #e3dbf7;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 650;
        }

        .avatar-sm {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #f3c9d8;
        }

        .avatar-placeholder-sm {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #fce6ef;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        /* Tombol Utama dan Soft Admin */
        .btn-admin-primary {
            background: #a987b7;
            border-color: #a987b7;
            color: #ffffff;
            border-radius: 12px;
            font-weight: 650;
            padding: 9px 18px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-admin-primary:hover {
            background: #886b98;
            border-color: #886b98;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(136, 107, 152, 0.25);
            transform: translateY(-1px);
        }

        .btn-admin-soft {
            background: #f8eaf1;
            color: #a36f91;
            border: 1px solid #f3c9d8;
            border-radius: 12px;
            font-weight: 650;
            padding: 8px 16px;
            font-size: 13.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-admin-soft:hover {
            background: #f3c9d8;
            color: #884b6f;
            transform: translateY(-1px);
        }

        /* ========================================================= */
        /* STYLING TOMBOL AKSI PADA PAGE MATERI EDUKASI (ADMIN)      */
        /* ========================================================= */
        .action-btn-group {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .btn-action-preview {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 11px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 650;
            color: #6d4b82;
            background: #f0e8fa;
            border: 1px solid #dfd1f1;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .btn-action-preview:hover {
            background: #e4d5f8;
            color: #543766;
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(109, 75, 130, 0.15);
        }

        .btn-action-edit {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 11px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 650;
            color: #a34f7b;
            background: #fce8ef;
            border: 1px solid #f6cfdc;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .btn-action-edit:hover {
            background: #f8cddb;
            color: #843a60;
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(163, 79, 123, 0.15);
        }

        .btn-action-push {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 11px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 650;
            color: #b45309;
            background: #fff0e7;
            border: 1px solid #fedec0;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .btn-action-push:hover {
            background: #fedbc3;
            color: #92400e;
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(180, 83, 9, 0.15);
        }

        .btn-action-delete {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 11px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 650;
            color: #c0264b;
            background: #fdf2f4;
            border: 1px solid #f8dce7;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .btn-action-delete:hover {
            background: #f9d3de;
            color: #9f1239;
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(192, 38, 75, 0.15);
        }

        /* Action Buttons Legend Card */
        .action-legend-card {
            background: #ffffff;
            border: 1px solid #f0e3f0;
            border-radius: 18px;
            padding: 16px 20px;
            box-shadow: 0 4px 16px rgba(120, 97, 138, 0.04);
        }

        .action-legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 10px;
            background: #faf7fc;
            font-size: 12px;
            color: #65566d;
        }

        /* Tombol Logout */
        .btn-logout {
            background: #f8e8ef;
            color: #a75e7c;
            border: none;
            border-radius: 12px;
            padding: 11px 15px;
            font-weight: 600;
        }

        .btn-logout:hover {
            background: #efd2df;
            color: #874762;
        }

        /* Modal Logout */
        .logout-modal {
            border: none;
            border-radius: 20px;
            overflow: hidden;
        }

        .logout-icon {
            width: 65px;
            height: 65px;
            background: #f8e8ef;
            color: #b66d8a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: auto;
        }

        /* ================================================= */
        /* IKON SIDEBAR ADMIN NISAYA                         */
        /* ================================================= */

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-link .menu-icon {
            width: 24px;
            min-width: 24px;
            font-size: 19px;
            color: #a987b7;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .sidebar-link:hover .menu-icon {
            color: #a36f91;
        }

        .sidebar-link.active .menu-icon {
            color: #a36f91;
        }

        .sidebar-link .link-text {
            line-height: 1.5;
        }



/* SIDEBAR ADMIN MOBILE - SAMAKAN DENGAN SIDEBAR USER */
@media (max-width: 768px) {

    body {
        overflow-x: hidden;
    }

    /* SIDEBAR TERTUTUP SECARA DEFAULT */
    body .sidebar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        bottom: 0 !important;

        width: min(260px, 82vw) !important;
        height: 100vh !important;
        height: 100dvh !important;

        margin: 0 !important;
        padding: 25px 18px !important;
        box-sizing: border-box !important;

        background: #fff !important;
        overflow-x: hidden !important;
        overflow-y: auto !important;

        transform: translateX(-100%) !important;
        transition: transform 0.3s ease !important;

        z-index: 1100 !important;
        box-shadow: 5px 0 24px rgba(80, 50, 90, 0.12);
    }

    /* SIDEBAR TERBUKA */
    body.sidebar-collapsed .sidebar {
        transform: translateX(0) !important;
    }

    /* TAMPILAN MENU SAAT SIDEBAR DIBUKA */
    body .sidebar-brand {
        justify-content: flex-start !important;
        padding: 5px 12px 25px !important;
    }

    body.sidebar-collapsed .brand-text,
    body.sidebar-collapsed .link-text,
    body.sidebar-collapsed .menu-label,
    body.sidebar-collapsed .sidebar-description,
    body.sidebar-collapsed .sidebar-user-info {
        display: block !important;
    }

    body.sidebar-collapsed .sidebar-user-pill {
        justify-content: flex-start !important;
        padding: 10px 12px !important;
        margin: 0 4px 14px !important;
    }

    body .sidebar-link {
        justify-content: flex-start !important;
        padding: 12px 14px !important;
    }

    /* KONTEN ADMIN TIDAK IKUT BERGESER */
    body .app-main,
    body.sidebar-collapsed .app-main {
        position: relative !important;
        left: 0 !important;

        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;

        padding: 65px 15px 25px !important;

        box-sizing: border-box !important;
        transform: none !important;
        transition: none !important;
    }

    /* TOMBOL HAMBURGER SAAT SIDEBAR TERTUTUP */
    body .sidebar-toggle {
        position: fixed !important;
        top: 15px !important;
        left: 15px !important;

        z-index: 1200 !important;
        transition: left 0.3s ease !important;
    }

    /* TOMBOL BERGESER KE SAMPING SIDEBAR SAAT DIBUKA */
    body.sidebar-collapsed .sidebar-toggle {
        left: min(
            calc(min(260px, 82vw) + 5px),
            calc(100vw - 53px)
        ) !important;
    }

    /* OVERLAY HANYA SAAT SIDEBAR TERBUKA */
    body.sidebar-collapsed::before {
        content: "";
        position: fixed;
        inset: 0;

        background: rgba(40, 30, 45, 0.35);

        z-index: 1020;
        pointer-events: none;
    }

    /* OVERLAY HILANG SAAT SIDEBAR DITUTUP */
    body:not(.sidebar-collapsed)::before {
        content: none !important;
        display: none !important;
    }

    /* MODAL */
    body .modal-backdrop {
        z-index: 1300 !important;
    }

    body .modal {
        z-index: 1305 !important;
    }
}


    </style>
</head>

<body>

    <!-- Tombol Sidebar Toggle Serasi dengan Page User -->
    <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
        ☰
    </button>

    <!-- Sidebar Admin Menggunakan Struktur & Ornamen Selaras Page User -->


    <!-- Sidebar Admin Menggunakan Struktur & Ornamen Selaras Page User -->
    <aside class="sidebar" id="sidebar">

        <a href="index.php" class="sidebar-brand">
            <span class="brand-icon">🌸</span>
            <span class="brand-text">NISAYA</span>
        </a>

        <!-- Profil Admin Singkat di Sidebar -->
        <div class="sidebar-user-pill">
            <?php if (!empty($currentAdmin['profile_picture']) && file_exists(__DIR__ . '/../../' . $currentAdmin['profile_picture'])): ?>
                <img src="../<?= e($currentAdmin['profile_picture']) ?>" alt="Avatar" class="avatar-sidebar">
            <?php else: ?>
                <div class="avatar-sidebar-placeholder">🛡️</div>
            <?php endif; ?>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name text-truncate"><?= e($currentAdmin['name'] ?? $_SESSION['user_name'] ?? 'Admin') ?></div>
                <span class="sidebar-user-role">Administrator</span>
            </div>
        </div>


        <div class="sidebar-menu">

            <small class="menu-label">MENU ADMIN</small>

            <!-- Dashboard -->
            <a href="index.php"
                class="sidebar-link <?= $adminActive === 'dashboard' ? 'active' : '' ?>"
                title="Dashboard Admin">

                <i class="bi bi-house-door menu-icon"></i>
                <span class="link-text">Dashboard</span>
            </a>

            <!-- Kelola Pengguna -->
            <a href="users.php"
                class="sidebar-link <?= $adminActive === 'users' ? 'active' : '' ?>"
                title="Kelola Pengguna">

                <i class="bi bi-people menu-icon"></i>
                <span class="link-text">Kelola Pengguna</span>
            </a>

            <!-- Materi Edukasi -->
            <a href="articles.php"
                class="sidebar-link <?= $adminActive === 'articles' ? 'active' : '' ?>"
                title="Materi Edukasi">

                <i class="bi bi-journal-bookmark menu-icon"></i>
                <span class="link-text">Materi Edukasi</span>
            </a>

            <!-- Push Notifikasi -->
            <a href="notifications.php"
                class="sidebar-link <?= $adminActive === 'notifications' ? 'active' : '' ?>"
                title="Push Notifikasi">

                <i class="bi bi-bell menu-icon"></i>
                <span class="link-text">Push Notifikasi</span>
            </a>


            <small class="menu-label">AKSES APLIKASI</small>

            <!-- Halaman User -->
            <a href="../dashboard/index.php"
                class="sidebar-link"
                title="Halaman User">

                <i class="bi bi-window menu-icon"></i>
                <span class="link-text">Halaman User ↗</span>
            </a>

            <!-- Pusat Edukasi -->
            <a href="../education/index.php"
                class="sidebar-link"
                title="Pusat Edukasi">

                <i class="bi bi-book-half menu-icon"></i>
                <span class="link-text">Pusat Edukasi ↗</span>
            </a>

        </div>


        <div class="sidebar-bottom">

            <form id="logoutForm" action="../auth/logout.php" method="POST">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <button type="button"
                    class="sidebar-link logout-link"
                    title="Keluar"
                    data-bs-toggle="modal"
                    data-bs-target="#logoutModal">

                    <span>↪</span>
                    <span class="link-text">Keluar</span>

                </button>
            </form>

            <!-- MODAL KONFIRMASI LOGOUT -->


            <small class="sidebar-description">
                Administrator Control Panel · NISAYA
            </small>

        </div>

    </aside>
    <div class="modal fade" id="logoutModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content logout-modal">

                <div class="modal-body text-center p-4">

                    <div class="logout-icon mb-3">
                        ↪
                    </div>

                    <h5 class="fw-bold">Konfirmasi Keluar</h5>

                    <p class="text-muted">
                        Apakah kamu yakin ingin keluar dari NISAYA?
                    </p>

                    <div class="d-flex justify-content-center gap-2 mt-4">

                        <button type="button"
                            class="btn btn-secondary px-4"
                            data-bs-dismiss="modal">
                            Batal
                        </button>

                        <button type="submit"
                            form="logoutForm"
                            class="btn btn-danger px-4">
                            Ya, Keluar
                        </button>

                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Main Content Container yang Selaras dengan User Page -->

    <main class="app-main py-4">

        <!-- TOP HEADER STRIP ADMIN -->
        <div class="admin-topstrip mb-4">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                <div class="d-flex align-items-center gap-2">

                    <span class="admin-badge-pill">
                        🛡️ Panel Administrator
                    </span>

                    <span class="text-muted small d-none d-sm-inline">
                        · <?= e($pageTitle) ?>
                    </span>

                </div>

                <div class="d-flex align-items-center gap-2">

                    <a href="notifications.php"
                        class="topstrip-btn"
                        title="Kirim Push Notifikasi">
                        <span>♧</span> Notifikasi
                    </a>

                    <a href="../dashboard/index.php"
                        class="topstrip-btn btn-mode-user"
                        title="Buka Mode Pengguna">
                        <span>🌷</span> Mode User ↗
                    </a>

                </div>

            </div>

        </div>


        <!-- FLASH MESSAGE SUCCESS -->
        <?php if ($m = flash('success')): ?>

            <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm"
                role="alert">

                🌸 <?= e($m) ?>

                <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close">
                </button>

            </div>

        <?php endif; ?>


        <!-- FLASH MESSAGE ERROR -->
        <?php if ($m = flash('error')): ?>

            <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm"
                role="alert">

                ⚠️ <?= e($m) ?>

                <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close">
                </button>

            </div>

        <?php endif; ?>


        <!-- KONTEN HALAMAN ADMIN DILANJUTKAN DI SINI -->