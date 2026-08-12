<?php
declare(strict_types=1);

session_start();

const SECRET_PHRASE = 'йошкин кошкин';
const CREDENTIALS_FILE = __DIR__ . '/credentials.php';

/**
 * Загружаем логин и хэш пароля
 */
function loadCredentials(): array
{
    if (file_exists(CREDENTIALS_FILE)) {
        $data = require CREDENTIALS_FILE;
        if (is_array($data)) {
            return [
                'username' => $data['username'] ?? 'admin',
                'password_hash' => $data['password_hash'] ?? '',
            ];
        }
    }

    return [
        'username' => 'admin',
        'password_hash' => '',
    ];
}

/**
 * Сохраняем логин и хэш пароля
 */
function saveCredentials(string $username, string $passwordHash): void
{
    $payload = [
        'username' => $username,
        'password_hash' => $passwordHash,
    ];

    $export = "<?php\nreturn " . var_export($payload, true) . ";\n";

    if (file_put_contents(CREDENTIALS_FILE, $export, LOCK_EX) === false) {
        throw new RuntimeException('Не удалось сохранить credentials.php');
    }
}

/**
 * Проверяем, настроен ли пароль
 */
function credentialsConfigured(): bool
{
    return ADMIN_PASSWORD_HASH !== null && ADMIN_PASSWORD_HASH !== '';
}

$credentials = loadCredentials();

define('ADMIN_USERNAME', $credentials['username']);
define('ADMIN_PASSWORD_HASH', $credentials['password_hash']);

/**
 * Проверяем авторизацию
 */
function isAuthenticated(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

/**
 * Требуем авторизацию
 */
function requireAuth(): void
{
    if (!isAuthenticated()) {
        header('Location: auth.php');
        exit;
    }
}

/**
 * Попытка входа
 */
function attemptLogin(string $username, string $password): bool
{
    if (!credentialsConfigured()) {
        return false;
    }

    if (
        hash_equals(ADMIN_USERNAME, $username) &&
        password_verify($password, ADMIN_PASSWORD_HASH)
    ) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        return true;
    }

    return false;
}

/**
 * Выход
 */
function adminLogout(): void
{
    $_SESSION = [];
    if (session_id() !== '' || isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 42000, '/');
    }
    session_destroy();
}
