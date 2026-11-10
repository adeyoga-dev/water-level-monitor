<?php require_once __DIR__ . '/ui-text.php'; ?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> · SIPATA</title>
    <link rel="icon" href="/assets/images/flow-mark-blue.svg">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-4.6.2-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendor/fontawesome-free-6.7.2/css/fontawesome.min.css">
    <link rel="stylesheet" href="/assets/vendor/fontawesome-free-6.7.2/css/solid.min.css">
    <script src="/assets/button-icons.js" defer></script>
    <link rel="stylesheet" href="/assets/css/design-tokens.css">
    <link rel="stylesheet" href="/assets/css/theme.css">
</head>

<body class="flow-auth-body">
    <div class="flow-auth-illustration" aria-hidden="true">
        <svg viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" focusable="false">
            <path class="flow-auth-river-soft" d="M0 560C240 380 350 760 660 650S1130 370 1440 520V900H0Z" />
            <path class="flow-auth-river" d="M0 710C260 530 420 850 740 720S1160 550 1440 650V900H0Z" />
            <g class="flow-auth-contours">
                <path d="M-40 240C180 50 350 330 520 160S900 10 1040 160S1300 330 1480 130" />
                <path d="M-40 270C180 80 350 360 520 190S900 40 1040 190S1300 360 1480 160" />
                <path d="M-40 300C180 110 350 390 520 220S900 70 1040 220S1300 390 1480 190" />
                <path d="M-40 330C180 140 350 420 520 250S900 100 1040 250S1300 420 1480 220" />
            </g>
            <g class="flow-auth-gauge" transform="translate(1160 400)">
                <rect x="0" y="0" width="52" height="220" rx="12" />
                <path d="M0 35H30M0 70H18M0 105H30M0 140H18M0 175H30M-100 185Q-70 165 -40 185T20 185T80 185T140 185M-100 210Q-70 190 -40 210T20 210T80 210T140 210" />
            </g>
        </svg>
    </div>
    <main class="flow-auth-page">
        <div class="flow-auth-panel">
            <section aria-label="Masuk ke SIPATA">
                <div class="card mb-0">
                    <div class="card-body">
                        <a class="flow-logo d-flex align-items-center justify-content-center text-decoration-none mb-2" href="/login.php"><img class="flow-brand-mark" src="/assets/images/flow-mark-blue.svg" alt="" width="36" height="36"><span>SIPATA</span></a>
                        <p class="text-muted text-center mb-4">Sistem Pemantauan Tinggi Air</p>
                        <h1 class="h3 text-center mb-4"><?= h($title) ?></h1>
                        <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h(uiText($error)) ?></div><?php endif ?>
                        <?php if ($message ?? null): ?><div class="alert alert-success" role="status"><?= h($message) ?></div><?php endif ?>
                        <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                            <div class="form-group"><label for="email">Email</label><input class="form-control" id="email" name="email" type="email" required maxlength="249" autocomplete="username" value="<?= h(is_string($_POST['email'] ?? '') ? ($_POST['email'] ?? '') : '') ?>"></div>
                            <div class="form-group"><label for="password">Kata sandi</label><input class="form-control" id="password" name="password" type="password" required autocomplete="current-password"></div>
                            <div class="flow-auth-actions d-flex align-items-center">
                                <button class="btn btn-primary" type="submit">Masuk</button>
                                <a class="btn btn-outline-secondary" href="/viewer.php">Pengunjung</a>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </main>
    <script src="/assets/auth-forms.js" defer></script>
</body>

</html>
