<?php
require_once __DIR__ . '/config.php';

adminLogout();

header('Location: auth.php?msg=' . urlencode('Вы вышли из системы'));
exit;
