<?php
/**
 * КОНФИГУРАЦИЯ БАЗЫ ДАННЫХ (MySQL)
 * 
 * Версия 2.1 с поддержкой .env и расширенными возможностями
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Инициализация переменных окружения
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Обязательные переменные окружения
$dotenv->required([
    'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS',
    'DB_PORT', 'DB_CHARSET'
])->notEmpty();

// Конфигурация подключения
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// Пул подключений (статический массив для разных пользователей)
static $dbPool = [];

/**
 * Получение подключения к БД с улучшенной безопасностью
 * 
 * @param string|null $user Имя пользователя (null для дефолтного)
 * @param string|null $pass Пароль (null для дефолтного)
 * @param array $options Дополнительные PDO-опции
 * @return PDO
 * @throws PDOException
 */
function getDB($user = null, $pass = null, $options = []) {
    $user = $user ?? DB_USER;
    $pass = $pass ?? DB_PASS;
    $key = md5($user . $pass . DB_HOST . DB_NAME);
    
    if (!isset($GLOBALS['dbPool'][$key]) || !$GLOBALS['dbPool'][$key] instanceof PDO) {
        try {
            $defaultOptions = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
            ];
            
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );
            
            $GLOBALS['dbPool'][$key] = new PDO(
                $dsn,
                $user,
                $pass,
                array_replace_recursive($defaultOptions, $options)
            );
            
            // Логирование успешного подключения (без паролей)
            error_log(sprintf(
                'DB: Successful connection to %s@%s',
                $user,
                DB_HOST
            ));
            
        } catch (PDOException $e) {
            error_log(sprintf(
                'DB: Connection failed (%s@%s): %s',
                $user,
                DB_HOST,
                $e->getMessage()
            ));
            throw new PDOException("Database connection error", 0, $e);
        }
    }
    
    return $GLOBALS['dbPool'][$key];
}

/**
 * Проверка подключения с таймаутом
 * 
 * @param int $timeout Таймаут в секундах
 * @return bool
 */
function testDBConnection($timeout = 5) {
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_TIMEOUT, $timeout);
        return (bool)$db->query('SELECT 1')->fetchColumn();
    } catch (PDOException $e) {
        error_log('DB: Connection test failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Безопасное выполнение SQL-запроса с параметрами
 * 
 * @param string $sql SQL-запрос
 * @param array $params Параметры
 * @return PDOStatement|false
 */
function executeQuery($sql, $params = []) {
    try {
        $db = getDB();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log('DB: Query failed: ' . $e->getMessage());
        error_log('SQL: ' . $sql);
        return false;
    }
}

/**
 * Транзакционная обертка для выполнения нескольких запросов
 * 
 * @param callable $callback Функция с логикой запросов
 * @return bool
 */
function transaction(callable $callback) {
    $db = getDB();
    try {
        $db->beginTransaction();
        $result = call_user_func($callback, $db);
        $db->commit();
        return $result;
    } catch (Exception $e) {
        $db->rollBack();
        error_log('DB: Transaction failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Экранирование значений для безопасного SQL
 * 
 * @param mixed $value
 * @return string
 */
function dbSafeValue($value) {
    if (is_null($value)) return 'NULL';
    
    $db = getDB();
    return $db->quote($value);
}

// Автоматическая проверка при первом подключении в dev-режиме
if ($_ENV['APP_ENV'] === 'development') {
    register_shutdown_function(function() {
        if (!testDBConnection()) {
            error_log('DB: Automatic connection test failed');
        }
    });
}