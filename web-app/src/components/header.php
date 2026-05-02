<?php
$base_url = $base_url ?? '';
$page_title = $page_title ?? 'Unimon Dashboard';
$body_class = $body_class ?? 'font-sans min-h-screen flex items-center justify-center p-6';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="icon" type="image/png" href="<?= $base_url ?>assets/images/unimon-logo.png">
    
    <!-- PWA -->
    <link rel="manifest" href="<?= $base_url ?>manifest.json">
    <meta name="theme-color" content="#FFF8EC">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Unimon">
    <link rel="apple-touch-icon" href="<?= $base_url ?>assets/images/unimon-logo.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="<?= $base_url ?>assets/js/tailwind.config.js"></script>
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/style.css">

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('<?= $base_url ?>service-worker.js')
                    .then(reg => console.log('Service Worker registered'))
                    .catch(err => console.log('Service Worker registration failed', err));
            });
        }
    </script>

    <?php if (isset($extra_head)) echo $extra_head; ?>
</head>
<body class="<?= htmlspecialchars($body_class) ?>">
