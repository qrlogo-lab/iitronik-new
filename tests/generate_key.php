<?php
// Простой генератор ключа без HTML-интерфейса
header('Content-Type: text/plain; charset=utf-8');
$newKey = bin2hex(random_bytes(32));
echo "ENCRYPTION_KEY=\"$newKey\"\n";
echo "SESSION_NAME=\"iitronik_session\"\n";
echo "SESSION_LIFETIME=\"86400\"\n";
echo "APP_ENV=\"production\"";
?>