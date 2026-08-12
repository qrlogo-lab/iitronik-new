<?php
require_once __DIR__ . '/config.php';

logoutUser();

header('Location: /auth/login.php?msg=Вы успешно вышли из системы');
exit;
