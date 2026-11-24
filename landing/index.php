<?php
// Landing Page NISAYA
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon NISAYA -->
    <link rel="icon" type="image/jpeg" href="../assets/img/ikon.jpeg?v=3">

    <title>NISAYA | Understand Your Cycle</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --pink: #C86F91;
            --dark-pink: #A94F72;
            --soft-pink: #FCEAF1;
            --cream: #FFF9F7;
            --dark: #45333B;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--cream);
            color: var(--dark);
        }

        /* NAVBAR */
        .navbar {
            background: rgba(255, 249, 247, 0.95);
            padding: 18px 0;
        }

        .navbar-brand {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--pink);
            letter-spacing: 2px;
        }

        .nav-link {
            color: var(--dark);
            font-weight: 500;
            margin: 0 8px;
        }

        .nav-link:hover {
            color: var(--pink);
        }

        .btn-login {
            display: inline-block;
            background: var(--pink);
            color: #fff;
            border-radius: 30px;
            padding: 10px 25px;
            text-decoration: none;
            transition: 0.3s ease;
        }

        .btn-login:hover {
            background: var(--dark-pink);
            color: #fff;
        }

        /* HERO */
        .hero {
            min-height: 620px;
            display: flex;
            align-items: center;
            background: linear-gradient(120deg, #FFF9F7, #FCEAF1);
            padding: 80px 0;
        }

        .hero .row {
            width: 100%;
            justify-content: center;
        }

        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(38px, 5vw, 60px);
            line-height: 1.2;
            margin-bottom: 20px;
        }

        .hero h1 span {
            color: var(--pink);
        }

        .hero p {
            color: #806B75;
            line-height: 1.8;
            max-width: 500px;
        }

        .btn-start {
            display: inline-block;
            background: var(--pink);
            color: #fff;
            padding: 14px 30px;
            border-radius: 30px;
            text-decoration: none;
            margin-top: 20px;
            transition: 0.3s ease;
        }

        .btn-start:hover {
            background: var(--dark-pink);
            color: #fff;
            transform: translateY(-3px);
        }

        .hero-card {
            width: 100%;
            max-width: 560px;
            margin: 0 auto;
            padding: 40px 35px;
            background: #fff;
            border-radius: 25px;
            box-shadow: 0 15px 40px rgba(200, 111, 145, 0.12);
            text-align: center;
        }

        .hero-icon {
            font-size: 90px;
            line-height: 1.2;
            margin-bottom: 15px;
        }

        .hero-card h3 {
            font-family: 'Playfair Display', serif;
            color: var(--dark);
        }

        .hero-card p {
            max-width: none;
            margin-bottom: 0;
        }

        /* GENERAL SECTION */
        section:not(.hero) {
            padding: 90px 0;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 38px;
            text-align: center;
            margin-bottom: 15px;
        }

        .section-description {
            max-width: 650px;
            margin: 0 auto 45px;
            color: #806B75;
            text-align: center;
            line-height: 1.8;
        }

        /* FEATURES */
        .feature-card {
            height: 100%;
            padding: 30px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.04);
            transition: 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-7px);
        }

        .feature-icon {
            font-size: 35px;
            margin-bottom: 18px;
        }

        .feature-card h5 {
            font-weight: 700;
            margin-bottom: 12px;
        }

        .feature-card p {
            color: #806B75;
            line-height: 1.7;
            margin-bottom: 0;
        }

        /* ABOUT */
        .about {
            background: var(--soft-pink);
        }

        /* FOOTER */
        footer {
            padding: 35px 15px;
            background: #45333B;
            color: #fff;
            text-align: center;
        }

        footer p {
            margin: 5px 0;
            color: #E8D8DF;
        }

        @media (max-width: 991.98px) {
            .hero {
                min-height: auto;
                padding: 56px 0;
            }

            .hero-card {
                max-width: 620px;
            }
        }

        @media (max-width: 767.98px) {
            .navbar {
                position: sticky;
                top: 0;
                z-index: 1030;
                padding: 12px 0;
                background: rgba(255, 249, 247, 0.98);
            }

            .navbar .container {
                padding-left: 16px;
                padding-right: 16px;
            }

            .navbar-brand {
                font-size: 25px;
                letter-spacing: 1px;
                line-height: 1.2;
                margin-right: 8px;
            }

            .navbar-toggler {
                padding: 8px 10px;
                border: 1px solid rgba(200, 111, 145, 0.35);
                border-radius: 10px;
            }

            .navbar-toggler:focus {
                box-shadow: 0 0 0 3px rgba(200, 111, 145, 0.15);
            }

            .navbar-collapse {
                padding-top: 12px;
            }

            .navbar-nav {
                align-items: stretch !important;
                gap: 6px;
            }

            .nav-link {
                margin: 0;
                padding: 10px 4px;
            }

            .btn-login {
                margin-top: 6px;
                text-align: center;
            }

            .hero {
                min-height: auto;
                padding: 36px 0 48px;
                text-align: center;
            }

            .hero .row {
                --bs-gutter-x: 1.25rem;
                --bs-gutter-y: 1.5rem;
                /* Bootstrap row biasanya melebar dengan margin negatif.
                   Matikan di mobile agar konten hero benar-benar berada di tengah. */
                width: 100%;
                margin-left: 0;
                margin-right: 0;
            }

            .hero .col-lg-5,
            .hero .col-lg-6 {
                width: 100%;
            }

            .hero-eyebrow {
                max-width: 100%;
                margin: 0 auto 14px;
                font-size: 10px;
                line-height: 1.5;
                letter-spacing: 1.5px !important;
                overflow-wrap: anywhere;
            }

            .hero h1 {
                max-width: 440px;
                margin: 0 auto 16px;
                font-size: clamp(32px, 8.2vw, 42px);
                line-height: 1.14;
                overflow-wrap: normal;
            }

            .hero p:not(.hero-eyebrow) {
                max-width: 420px;
                margin: 0 auto;
                font-size: 15px;
                line-height: 1.75;
            }

            .btn-start {
                margin-top: 20px;
                padding: 12px 25px;
                font-size: 15px;
            }

            .hero-card {
                width: 100%;
                max-width: 420px;
                margin: 12px auto 0;
                padding: 28px 22px;
                border-radius: 22px;
            }

            .hero-icon {
                font-size: 72px;
                margin-bottom: 12px;
            }

            .hero-card h3 {
                font-size: 23px;
                margin-bottom: 0;
            }

            .hero-card p {
                font-size: 14px;
                line-height: 1.7;
            }

            section:not(.hero) {
                padding: 56px 0;
            }

            .section-title {
                font-size: 30px;
            }

            .section-description {
                margin-bottom: 30px;
                padding: 0 8px;
                font-size: 15px;
            }

            .feature-card {
                padding: 24px;
            }

            footer {
                padding: 30px 16px;
            }
        }

        @media (max-width: 380px) {
            .hero {
                padding-top: 28px;
            }

            .hero h1 {
                font-size: 31px;
            }

            .hero-card {
                padding: 25px 18px;
            }
        }

    </style>
</head>

<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="#beranda">NISAYA.</a>

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarMenu"
                aria-controls="navbarMenu"
                aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarMenu">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link" href="#beranda">Beranda</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#tentang">Tentang</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#fitur">Fitur</a>
                    </li>

                    <li class="nav-item ms-lg-3">
                        <a class="btn-login" href="../auth/login.php">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- HERO -->
    <section class="hero" id="beranda">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-5">
                    <p
                        class="hero-eyebrow text-uppercase fw-bold"
                        style="color: var(--pink); letter-spacing: 3px;">
                        Your Personal Cycle Companion
                    </p>

                    <h1>
                        Understand Your Cycle,
                        <span>Embrace Yourself.</span>
                    </h1>

                    <p>
                        Kenali pola siklus menstruasimu, catat perubahan
                        tubuh, dan pahami dirimu dengan lebih baik bersama NISAYA.
                    </p>

                    <a href="../auth/register.php" class="btn-start">
                        Mulai Sekarang →
                    </a>
                </div>

                <div class="col-lg-6">
                    <div class="hero-card">
                        <div class="hero-icon">🌸</div>

                        <h3>Hello, Beautiful!</h3>

                        <p class="mt-3">
                            Every cycle tells a story.
                            Take a moment to understand yours.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ABOUT -->
    <section class="about" id="tentang">
        <div class="container">
            <h2 class="section-title">About NISAYA</h2>

            <p class="section-description">
                NISAYA merupakan aplikasi berbasis website yang dirancang
                untuk membantu perempuan mencatat siklus menstruasi,
                memantau perkiraan periode berikutnya, dan mengenali
                perubahan kondisi tubuh.
            </p>

            <div class="text-center">
                <h4 style="font-family: 'Playfair Display', serif;">
                    Know Your Cycle, Care for Yourself.
                </h4>
            </div>
        </div>
    </section>

    <!-- FEATURES -->
    <section id="fitur">
        <div class="container">
            <h2 class="section-title">Explore Our Features</h2>

            <p class="section-description">
                Fitur sederhana untuk membantu perjalananmu
                memahami siklus menstruasi.
            </p>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">📅</div>
                        <h5>Menstrual Calendar</h5>
                        <p>
                            Catat dan pantau siklus menstruasi melalui
                            kalender digital.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">🌷</div>
                        <h5>Cycle Prediction</h5>
                        <p>
                            Lihat perkiraan periode menstruasi berikutnya
                            berdasarkan riwayat pencatatan.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">📝</div>
                        <h5>Daily Journal</h5>
                        <p>
                            Catat suasana hati dan kondisi tubuh
                            sebagai bagian dari pemantauan diri.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <h3 style="font-family: 'Playfair Display', serif;">NISAYA.</h3>

        <p>Understand Your Cycle, Embrace Yourself.</p>

        <p class="mt-3">© 2026 NISAYA. All Rights Reserved.</p>

        <p>
            Developed by TKJ Angkatan 35
            Rumah Gemilang Indonesia.
        </p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>