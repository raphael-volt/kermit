<?php
use src\core\pdo\ApiPDO;
use src\services\WatchService;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';
$pdo = ApiPDO::instance();
$usersOn = WatchService::instance()->getLoggedUsers();
$stmt = $pdo->prepare("SELECT name FROM jp_user WHERE id = ?");
$users = [];
foreach ($usersOn as $id) {
    $stmt->execute([$id]);
    $users[] = $stmt->fetchColumn(0);
}
sort($users, SORT_STRING|SORT_FLAG_CASE);
echo json_encode($users);