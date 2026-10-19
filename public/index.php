<?php

declare(strict_types=1);

use OpenPhonebook\Application\AppVersion;
use OpenPhonebook\Config\Config;
use OpenPhonebook\Setup\InstallationState;

$projectRoot = dirname(__DIR__);
$autoload = $projectRoot . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Open Phonebook dependencies are not installed.\n";
    exit;
}

require $autoload;

$config = Config::load($projectRoot);
$installation = new InstallationState($projectRoot . '/storage/installed.lock');

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

$appName = htmlspecialchars((string) $config->get('app.name', 'Open Phonebook'), ENT_QUOTES, 'UTF-8');
$status = $installation->isInstalled() ? 'installed' : 'not installed';

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $appName ?></title>
</head>
<body>
    <main>
        <h1><?= $appName ?></h1>
        <p>Foundation build <?= htmlspecialchars(AppVersion::VERSION, ENT_QUOTES, 'UTF-8') ?></p>
        <p>Installation status: <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></p>
    </main>
</body>
</html>
