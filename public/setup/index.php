<?php

declare(strict_types=1);

use OpenPhonebook\Config\Config;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Security\Session;
use OpenPhonebook\Setup\DatabaseTester;
use OpenPhonebook\Setup\EnvironmentChecker;
use OpenPhonebook\Setup\InstallationState;
use OpenPhonebook\Setup\Installer;
use OpenPhonebook\Setup\LocalConfigWriter;
use OpenPhonebook\Setup\SetupRequest;
use OpenPhonebook\Setup\SetupSession;
use OpenPhonebook\Setup\SetupView;
use Throwable;

$projectRoot = dirname(__DIR__, 2);
$autoload = $projectRoot . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Open Phonebook dependencies are not installed.\n";
    exit;
}

require $autoload;

$https = SetupRequest::isHttps();
Session::start($https);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$installation = new InstallationState($projectRoot . '/storage/installed.lock');

if ($installation->isInstalled()) {
    http_response_code(404);
    echo SetupView::page(
        'Setup unavailable',
        '<p>This Open Phonebook installation has already been completed.</p>'
    );
    exit;
}

$step = $_GET['step'] ?? 'environment';
$step = is_string($step) ? $step : 'environment';
$errors = [];
$setupSession = new SetupSession();

if ($step === 'environment') {
    $checker = new EnvironmentChecker();
    $checks = $checker->check($projectRoot, $https);
    $body = '<p>Before installation, Open Phonebook verifies the minimum runtime and security requirements.</p>';

    foreach ($checks as $check) {
        $class = $check->passed ? 'ok' : 'fail';
        $symbol = $check->passed ? '✓' : '✗';
        $body .= '<div class="check ' . $class . '"><strong>'
            . $symbol . ' ' . SetupView::escape($check->name)
            . '</strong><br><span class="muted">'
            . SetupView::escape($check->message)
            . '</span></div>';
    }

    if ($checker->allPassed($checks)) {
        $body .= '<form method="get"><input type="hidden" name="step" value="database">'
            . '<button type="submit">Continue</button></form>';
    } else {
        $body .= '<p class="fail"><strong>Resolve the failed checks before continuing.</strong></p>';
    }

    echo SetupView::page('System check', $body);
    exit;
}

if ($step === 'database') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!CsrfToken::verify(SetupRequest::postString('_csrf'))) {
            $errors[] = 'The setup form expired. Please try again.';
        }

        $database = [
            'driver' => 'mysql',
            'host' => SetupRequest::postString('db_host'),
            'port' => (int) SetupRequest::postString('db_port'),
            'name' => SetupRequest::postString('db_name'),
            'user' => SetupRequest::postString('db_user'),
            'password' => SetupRequest::postPassword('db_password'),
            'charset' => 'utf8mb4',
        ];

        if ($database['port'] < 1 || $database['port'] > 65535) {
            $errors[] = 'The database port is invalid.';
        }

        if ($errors === []) {
            $connectionError = (new DatabaseTester())->test($database);
            if ($connectionError !== null) {
                $errors[] = $connectionError;
            } else {
                $setupSession->setDatabase($database);
                CsrfToken::rotate();
                header('Location: ?step=application', true, 303);
                exit;
            }
        }
    }

    $body = '<p>Enter the MariaDB/MySQL database credentials. The password is kept only in the temporary server-side setup session until installation completes.</p>'
        . '<form method="post" autocomplete="off">'
        . '<input type="hidden" name="_csrf" value="' . SetupView::escape(CsrfToken::issue()) . '">'
        . '<label>Database host<input name="db_host" required value="127.0.0.1" autocomplete="off"></label>'
        . '<label>Database port<input name="db_port" required inputmode="numeric" value="3306" autocomplete="off"></label>'
        . '<label>Database name<input name="db_name" required autocomplete="off"></label>'
        . '<label>Database user<input name="db_user" required autocomplete="off"></label>'
        . '<label>Database password<input type="password" name="db_password" autocomplete="new-password"></label>'
        . '<button type="submit">Test and continue</button></form>';

    echo SetupView::page('Database', $body, $errors);
    exit;
}

if ($step === 'application') {
    if ($setupSession->database() === null) {
        header('Location: ?step=database', true, 303);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!CsrfToken::verify(SetupRequest::postString('_csrf'))) {
            $errors[] = 'The setup form expired. Please try again.';
        }

        $baseUrl = rtrim(SetupRequest::postString('base_url'), '/');
        $timezone = SetupRequest::postString('timezone');
        $locale = SetupRequest::postString('locale');
        $adminEmail = SetupRequest::postString('admin_email');
        $adminName = SetupRequest::postString('admin_name');
        $adminPassword = SetupRequest::postPassword('admin_password');
        $adminPasswordConfirm = SetupRequest::postPassword('admin_password_confirm');

        if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !str_starts_with($baseUrl, 'https://')) {
            $errors[] = 'Base URL must be a valid HTTPS URL.';
        }

        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            $errors[] = 'Select a valid timezone.';
        }

        if (!in_array($locale, ['en', 'de'], true)) {
            $errors[] = 'Select a supported language.';
        }

        if ($adminPassword !== $adminPasswordConfirm) {
            $errors[] = 'Administrator passwords do not match.';
        }

        if ($errors === []) {
            try {
                $database = $setupSession->database();

                if ($database === null) {
                    throw new RuntimeException('Database setup data is missing.');
                }

                $installer = new Installer(
                    new LocalConfigWriter($projectRoot . '/config/local.php'),
                    $installation
                );

                $installer->install(
                    $database,
                    $baseUrl,
                    $timezone,
                    $locale,
                    $adminEmail,
                    $adminName,
                    $adminPassword
                );

                $setupSession->clear();
                Session::regenerate();
                CsrfToken::rotate();

                header('Location: ?step=complete', true, 303);
                exit;
            } catch (Throwable $exception) {
                error_log('Open Phonebook setup failed: ' . $exception::class);
                $errors[] = 'Installation failed. Check the server log for technical details.';
            }
        }
    }

    $config = Config::load($projectRoot);
    $suggestedBaseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? '');
    $defaultTimezone = (string) $config->get('app.timezone', 'UTC');

    $timezoneOptions = '';
    foreach (['Europe/Berlin', 'UTC', 'Europe/London', 'America/New_York'] as $timezoneOption) {
        $selected = $timezoneOption === $defaultTimezone ? ' selected' : '';
        $timezoneOptions .= '<option value="' . SetupView::escape($timezoneOption) . '"' . $selected . '>'
            . SetupView::escape($timezoneOption)
            . '</option>';
    }

    $body = '<p>Configure the application and create the first system administrator.</p>'
        . '<form method="post" autocomplete="off">'
        . '<input type="hidden" name="_csrf" value="' . SetupView::escape(CsrfToken::issue()) . '">'
        . '<label>Base URL<input name="base_url" required value="' . SetupView::escape($suggestedBaseUrl) . '"></label>'
        . '<label>Timezone<select name="timezone">' . $timezoneOptions . '</select></label>'
        . '<label>Language<select name="locale"><option value="en">English</option><option value="de">Deutsch</option></select></label>'
        . '<label>Administrator name<input name="admin_name" required autocomplete="name"></label>'
        . '<label>Administrator email<input type="email" name="admin_email" required autocomplete="email"></label>'
        . '<label>Administrator password<input type="password" name="admin_password" required minlength="12" autocomplete="new-password"></label>'
        . '<label>Repeat password<input type="password" name="admin_password_confirm" required minlength="12" autocomplete="new-password"></label>'
        . '<p class="muted">Use at least 12 characters. A password manager is recommended.</p>'
        . '<button type="submit">Install Open Phonebook</button></form>';

    echo SetupView::page('Application and administrator', $body, $errors);
    exit;
}

if ($step === 'complete') {
    if (!$installation->isInstalled()) {
        header('Location: ?step=environment', true, 303);
        exit;
    }

    echo SetupView::page(
        'Installation complete',
        '<p class="ok"><strong>Open Phonebook has been installed successfully.</strong></p>'
        . '<p>The setup endpoint is now locked. Continue to the application.</p>'
        . '<p><a href="../">Open Open Phonebook</a></p>'
    );
    exit;
}

http_response_code(404);
echo SetupView::page('Not found', '<p>The requested setup step does not exist.</p>');
