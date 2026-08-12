<?php
/**
 * Диагностика производительности базы
 */

$db = new PDO('sqlite:itronik.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "🔍 Диагностика производительности\n\n";

// 1. Размер базы
$size = filesize('itronik.db');
$sizeMb = round($size / 1024 / 1024, 2);
echo "💾 Размер базы: {$sizeMb} MB\n";

if ($sizeMb > 100) {
    echo "   ⚠️  БОЛЬШАЯ БАЗА! Рекомендуется MySQL\n";
} elseif ($sizeMb > 50) {
    echo "   ⚠️  База растет, следите за размером\n";
} else {
    echo "   ✅ Размер нормальный\n";
}

// 2. Количество записей
$stats = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as users,
        (SELECT COUNT(*) FROM conversations) as conversations,
        (SELECT COUNT(*) FROM messages) as messages,
        (SELECT COUNT(*) FROM events) as events
")->fetch(PDO::FETCH_ASSOC);

echo "\n📊 Записей в таблицах:\n";
foreach ($stats as $table => $count) {
    echo "   $table: $count\n";
}

if ($stats['messages'] > 10000) {
    echo "   ⚠️  Много сообщений! Может тормозить\n";
}

if ($stats['events'] > 1000) {
    echo "   ⚠️  Много событий! Запустите cleanup\n";
}

// 3. Проверка индексов
echo "\n🔍 Индексы:\n";
$indexes = $db->query("SELECT name, tbl_name FROM sqlite_master WHERE type = 'index'")->fetchAll(PDO::FETCH_ASSOC);

$requiredIndexes = [
    'idx_users_session',
    'idx_conv_user',
    'idx_msg_conversation',
    'idx_events_read'
];

$existingIndexes = array_column($indexes, 'name');

foreach ($requiredIndexes as $idx) {
    if (in_array($idx, $existingIndexes)) {
        echo "   ✅ $idx\n";
    } else {
        echo "   ❌ $idx ОТСУТСТВУЕТ!\n";
    }
}

// 4. Тест скорости запроса
echo "\n⏱️  Тест скорости:\n";

$start = microtime(true);
$db->query("
    SELECT 
        c.*,
        COUNT(m.id) as message_count
    FROM conversations c
    LEFT JOIN messages m ON c.id = m.conversation_id
    WHERE c.ended_at IS NULL
    GROUP BY c.id
    LIMIT 50
")->fetchAll();
$time = round((microtime(true) - $start) * 1000, 2);

echo "   Список диалогов: {$time} ms\n";

if ($time > 100) {
    echo "   ⚠️  МЕДЛЕННО! Добавьте индексы\n";
} elseif ($time > 50) {
    echo "   ⚠️  Можно быстрее\n";
} else {
    echo "   ✅ Быстро\n";
}

// 5. PRAGMA настройки
echo "\n⚙️  PRAGMA настройки:\n";
$pragmas = [
    'journal_mode',
    'synchronous',
    'cache_size',
    'temp_store'
];

foreach ($pragmas as $pragma) {
    $value = $db->query("PRAGMA $pragma")->fetchColumn();
    echo "   $pragma: $value\n";
}

echo "\n💡 Рекомендации:\n";

if ($sizeMb > 100) {
    echo "   🔴 Мигрируйте на MySQL!\n";
} else {
    echo "   🟢 Запустите: php add_indexes.php\n";
    echo "   🟢 Добавьте PRAGMA настройки в initDatabase()\n";
    echo "   🟢 Настройте автоочистку событий\n";
}
