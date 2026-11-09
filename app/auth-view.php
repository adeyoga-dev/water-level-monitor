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

<body>
    <main class="container py-5">
        <div class="row justify-content-center">
            <section class="col-md-8 col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <a class="flow-logo d-flex align-items-center text-decoration-none mb-4" href="/login.php"><img class="flow-brand-mark" src="/assets/images/flow-mark-blue.svg" alt="" width="36" height="36">SIPATA</a>
                        <h1 class="h3 mb-4"><?= h($title) ?></h1>
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