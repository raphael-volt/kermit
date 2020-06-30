<?php
use src\services\RequestService;
error_reporting(- 1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . "autoload.php";

$requestService = RequestService::instance();
try {
    $requestService->handle();
    $requestService->getResponse();
} catch (Exception $e) {
    $error = $requestService->getJsonError($e->getCode(), $e->getMessage(), true);
    $error->time = time();
    $fn = __DIR__ . DIRECTORY_SEPARATOR . "error.json";
    if (file_exists($fn))
        $json = json_decode(file_get_contents($fn));
    else
        $json = [];
    $json = [$error];
    //array_unshift($json, $error);
    $json = json_encode($json, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
    file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "error.json", $json);
}
