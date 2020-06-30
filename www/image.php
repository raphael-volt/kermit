<?php
include 'autoload.php';

use src\core\http\HTTPMethods;
use src\core\pdo\ApiPDO;
use src\services\FileService;
use src\vo\JPFile;

function responseHeader($code, $die = false)
{
    HTTPMethods::setResponseCode($code);
    if ($die)
        die();
}

function get_image_type($filename)
{
    $img = getimagesize($filename);
    if (! empty($img[2]))
        return image_type_to_mime_type($img[2]);
    return false;
}
$method = $_SERVER['REQUEST_METHOD'];
if ($method == HTTPMethods::OPTIONS) {
    responseHeader(200, true);
}

if (! HTTPMethods::is($method) || ! isset($_GET['id'])) {
    responseHeader(400, true);
}
$id = (int) $_GET['id'];
$pdo = ApiPDO::instance();

$filename = $pdo->getFilename($id);
$filename = FileService::instance()->getFilename(JPFile::TYPE_IMAGE, $filename);
if(! is_file($filename)) {
    responseHeader(400, true);
}
$imagesize = getimagesize($filename);

header("Content-Type: " . $imagesize['mime']);
header('Content-Length: ' . filesize($filename));

echo file_get_contents($filename);