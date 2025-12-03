<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= SecurityHelper::csrfToken() ?>">
    <title><?= $pageTitle ?? 'Flowaxy CMS - Адмін-панель' ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= UrlHelper::admin('assets/images/brand/favicon.png') ?>">
    <link rel="shortcut icon" type="image/png" href="<?= UrlHelper::admin('assets/images/brand/favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= UrlHelper::admin('assets/images/brand/favicon.png') ?>">
    
    <!-- Bootstrap CSS -->
    <link href="<?= UrlHelper::admin('assets/styles/bootstrap/bootstrap.min.css') ?>?v=5.1.3" rel="stylesheet">
    
    <!-- CSS Compatibility Fixes (після Bootstrap для перевизначення) -->
    <link href="<?= UrlHelper::adminAsset('styles/css-fixes.css') ?>" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="<?= UrlHelper::admin('assets/styles/font-awesome/css/all.min.css') ?>" rel="stylesheet">
    
    <!-- Основні стилі адмінки -->
    <link href="<?= UrlHelper::adminAsset('styles/flowaxy.css') ?>" rel="stylesheet">
    
    <?php if (! empty($additionalCSS)): ?>
        <!-- Додаткові CSS файли -->
        <?php foreach ($additionalCSS as $css): ?>
            <link href="<?= $css ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (! empty($additionalInlineCSS)): ?>
        <!-- Інлайн стилі для сторінки -->
        <style><?= $additionalInlineCSS ?></style>
    <?php endif; ?>
</head>
<body>
    <?php include __DIR__ . '/../components/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../components/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10">
                <div class="admin-content-container">
                    <?php include __DIR__ . '/../components/breadcrumbs.php'; ?>
                    <?php include __DIR__ . '/../components/page-header.php'; ?>
                    
                    <!-- Основний контент сторінки -->
    <?php
    // Якщо є кастомний шаблон плагіна, використовуємо його
    if (isset($customTemplateFile) && file_exists($customTemplateFile)) {
        include $customTemplateFile;
    } else {
        // Інакше використовуємо стандартний шаблон
        $templateFile = __DIR__ . '/../templates/' . ($templateName ?? 'dashboard') . '.php';
        if (file_exists($templateFile)) {
            include $templateFile;
        }
    }
    ?>
                </div>
            </main>
        </div>
    </div>
    
    <?php include __DIR__ . '/../components/footer.php'; ?>
    <?php include __DIR__ . '/../components/notifications.php'; ?>
    <?php include __DIR__ . '/../components/scripts.php'; ?>
    
    <?php if (! empty($additionalJS)): ?>
        <!-- Додаткові JS файли -->
        <?php foreach ($additionalJS as $js): ?>
            <script src="<?= $js ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

