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
    <?php require __DIR__ . '/background-view.php'; ?>
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
