<?php
// Автозагрузчик для Dotenv и всех зависимостей
spl_autoload_register(function ($class) {
    $map = [
        'Dotenv\\' => __DIR__ . '/vlucas/phpdotenv/src/',
        'PhpOption\\' => __DIR__ . '/phpoption/phpoption/src/PhpOption/',
        'GrahamCampbell\\ResultType\\' => __DIR__ . '/graham-campbell/result-type/src/'
    ];

    foreach ($map as $prefix => $base_dir) {
        if (strpos($class, $prefix) === 0) {
            $relative_class = substr($class, strlen($prefix));
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        }
    }
});