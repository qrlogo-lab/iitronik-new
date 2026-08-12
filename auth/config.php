<?php
/**
 * КОНФИГУРАЦИЯ АВТОРИЗАЦИИ (оптимизированная)
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Загрузка .env
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Минимальная проверка ключа
if (empty($_ENV['ENCRYPTION_KEY'])) {
    die('ENCRYPTION_KEY must be set in .env');
}

// Настройки сессии
$isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
session_name($_ENV['SESSION_NAME'] ?? ($isProduction ? 'iitronik_prod' : 'iitronik_dev'));
session_set_cookie_params([
    'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? ($isProduction ? 14400 : 86400)),
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'] ?? null,
    'secure' => $isProduction,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Подключение БД
require_once __DIR__ . '/../config/database.php';

// Ключ шифрования
define('ENCRYPTION_KEY', $_ENV['ENCRYPTION_KEY']);
/**
 * Проверка авторизации пользователя
 */
function isUserLoggedIn(): bool {
    return !empty($_SESSION['user_id']) && filter_var($_SESSION['user_email'] ?? '', FILTER_VALIDATE_EMAIL);
}

/**
 * Получение текущего пользователя (с кешированием)
 */
function getCurrentUser(): ?array {
    if (!isUserLoggedIn()) {
        return null;
    }
    
    // Возвращаем кешированные данные, если они есть
    if (!empty($_SESSION['user_data']) && is_array($_SESSION['user_data'])) {
        return $_SESSION['user_data'];
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['user_data'] = $user; // Кешируем
            return $user;
        }
    } catch (PDOException $e) {
        error_log('DB User fetch error: ' . $e->getMessage());
    }
    
    return null;
}

/**
 * Требование авторизации
 */
function requireLogin(): void {
    if (!isUserLoggedIn()) {
        header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
        exit;
    }
}

/**
 * Выход пользователя
 */
function logoutUser(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Шифрование текста (AES-256-CBC)
 */
function encrypt(string $text): string {
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt(
        $text,
        'AES-256-CBC',
        ENCRYPTION_KEY,
        OPENSSL_RAW_DATA,
        $iv
    );
    return base64_encode($iv . $encrypted);
}

/**
 * Расшифровка текста
 */
function decrypt(string $encrypted): string {
    if (empty($encrypted)) {
        return '';
    }
    
    $data = base64_decode($encrypted);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    
    return openssl_decrypt(
        $encrypted,
        'AES-256-CBC',
        ENCRYPTION_KEY,
        OPENSSL_RAW_DATA,
        $iv
    ) ?: '';
}

/**
 * Валидация email
 */
function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Валидация пароля
 */
function isValidPassword(string $password): bool {
    return strlen($password) >= 8 && preg_match('/[A-Z]/', $password) && preg_match('/\d/', $password);
}