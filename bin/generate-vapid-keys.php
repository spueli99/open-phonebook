<?php

declare(strict_types=1);

use Minishlink\WebPush\VAPID;
use Throwable;

$projectRoot = dirname(__DIR__);
$autoload = $projectRoot . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "Composer autoload file is missing.\n");
    exit(2);
}

require $autoload;

try {
    $keys = VAPID::createVapidKeys();
} catch (Throwable) {
    fwrite(STDERR, "VAPID keys could not be generated.\n");
    exit(2);
}

printf(
    "OPENPHONEBOOK_WEB_PUSH_PUBLIC_KEY=%s\n"
    . "OPENPHONEBOOK_WEB_PUSH_PRIVATE_KEY=%s\n",
    $keys['publicKey'],
    $keys['privateKey']
);
