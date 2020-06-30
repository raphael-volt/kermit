<?php
namespace src\services;

use src\vo\JPFile;

class FileService
{

    const DIR_PRIVATE = "private";

    const DIR_ASSETS = "assets";

    const DIR_IMAGE = "images";

    const DIR_DOWNLOADS = "downloads";

    const DIR_LOGS = "logs";

    private function path(...$pieces)
    {
        return join(DIRECTORY_SEPARATOR, $pieces);
    }

    /**
     *
     * @var \stdClass
     */
    private $_config;

    /**
     *
     * @return \stdClass
     */
    function getConfig()
    {
        if ($this->_config)
            return $this->_config;
        $path = $this->path(dirname($this->_getRoot()), self::DIR_PRIVATE, "kermit.config.json");
        $this->_config = json_decode(file_get_contents($path));
        return $this->_config;
    }

    private $_root;

    private function _getRoot()
    {
        if (! $this->_root)
            $this->_root = dirname(__DIR__, 2);
        return $this->_root;
    }

    function root(...$pieces)
    {
        return $this->path($this->_getRoot(), ...$pieces);
    }

    function assets(...$pieces)
    {
        return $this->root(self::DIR_ASSETS, ...$pieces);
    }

    function assetsImages(...$pieces)
    {
        return $this->assets(self::DIR_IMAGE, ...$pieces);
    }

    function assetsDownloads(...$pieces)
    {
        return $this->assets(self::DIR_DOWNLOADS, ...$pieces);
    }

    function logs(...$pieces)
    {
        return $this->root(self::DIR_LOGS, ...$pieces);
    }

    private const DATA_IMG_PATTERN = '/^data:image\/(\w+);base64,(.*)/';

    /**
     *
     * @var FileService
     */
    private static $_instance;

    /**
     *
     * @return \src\services\FileService
     */
    static function instance()
    {
        if (! self::$_instance)
            self::$_instance = new FileService();
        return self::$_instance;
    }

    private function __construct()
    {}

    /**
     *
     * @param string|int $type
     * @param string $filename
     * @return string|NULL
     */
    function getFilename($type, $filename)
    {
        switch ((int) $type) {
            case JPFile::TYPE_IMAGE:
            case JPFile::TYPE_PICTO:
                return $this->assetsImages($filename);
                break;

            case JPFile::TYPE_DOWNLOAD:
                return $this->assetsDownloads($filename);
                break;
            case JPFile::TYPE_:
                return $this->assetsDownloads($filename);
                break;

            default:
                ;
                break;
        }
        ;
        return null;
    }

    /**
     *
     * @param int $width
     * @param int $height
     * @param int $destWidth
     * @param int $destHeight
     * @return int[]|int[]
     */
    function resolveDestSizes(int $width, int $height, int $destWidth, int $destHeight)
    {
        $ratio = $width / $height;
        /*
         * W H R
         * LANDSCAPE 200x100 2 w = h * r h = w / r
         * PORTRAIT 100x200 0.5 w = h * r h = w / r
         */
        if ($destWidth == 0 && $destHeight == 0) {
            $destWidth = $width;
            $destHeight = $height;
        }

        if ($destHeight == 0) {
            $destHeight = floatval($destWidth / $ratio);
        }

        if ($destWidth == 0) {
            $destWidth = floatval($destHeight * $ratio);
        }

        return [
            $destWidth,
            $destHeight
        ];
    }

    function unlinkImage($id)
    {
        $success = false;
        $db = DatabaseService::instance();
        $file = $db->getFileById($id);
        if ($file) {
            $success = $db->deleteFile($id);
            $filename = $this->getFilename(JPFile::TYPE_IMAGE, $file->filename);
            if (file_exists($filename)) {
                $success = unlink($filename);
            } else
                $success = false;
        }
        return $success;
    }

    function isImageOp($op)
    {
        if (! $op || ! property_exists($op, "insert"))
            return false;
        return property_exists($op->insert, "image");
    }

    function createImageFromString($data, $basename, $destWidth, $destHeight)
    {
        $img = $this->decodeImage($data);
        if ($img == false)
            return false;
        $src = imagecreatefromstring($img[1]);
        $type = $img[0];
        $img = null;
        $width = (int) imagesx($src);
        $height = (int) imagesy($src);

        $destSizes = $this->resolveDestSizes($width, $height, $destWidth, $destHeight);
        if ($destSizes[0] != $width) {
            $dest = imagecreatetruecolor($destSizes[0], $destSizes[1]);
            if ($type == "png") {
                imagealphablending($dest, true);
                $alpha_image = imagecolorallocatealpha($dest, 0, 0, 0, 127);
                imagefill($dest, 0, 0, $alpha_image);
            }
            imagecopyresampled($dest, $src, 0, 0, 0, 0, $destSizes[0], $destSizes[1], $width, $height);
            imagedestroy($src);
        } else {
            $dest = $src;
        }
        $basename = "$basename.$type";
        $filename = $this->getFilename(JPFile::TYPE_IMAGE, $basename);
        switch ($type) {
            case "jpeg":
            case "jpg":
                imagejpeg($dest, $filename, 85);
                break;
            case "png":
                imagealphablending($dest, false);
                imagesavealpha($dest, true);
                imagepng($dest, $filename);
                break;

            case "gif":
                imagegif($dest, $filename);
                break;

            default:
                $basename = false;
                break;
        }
        imagedestroy($dest);
        return $basename;
    }

    function saveInsert($op, $basename)
    {
        $attr = null;
        $img = null;
        $destWidth = 0;
        $destHeight = 0;
        if (! $op || ! property_exists($op, "insert"))
            return false;
        $insert = $op->insert;
        if (property_exists($op, "attributes")) {
            $attr = $op->attributes;
            if ($attr && is_object($attr)) {
                if (property_exists($attr, "width")) {
                    $destWidth = (int) $attr->width;
                }
                if (property_exists($attr, "height")) {
                    $destHeight = (int) $attr->height;
                }
            }
        }
        if (property_exists($insert, "image")) {
            $img = $insert->image;
        }
        if (! $img)
            return false;
        return $this->createImageFromString($img, $basename, $destWidth, $destHeight);
    }

    /**
     *
     * @param string $data
     * @return string[]|string[]|boolean
     */
    function decodeImage(string $data)
    {
        if (1 == preg_match(self::DATA_IMG_PATTERN, $data, $m)) {
            $type = strtolower($m[1]);
            if ($type == "jpeg")
                $type = "jpg";
            return [
                $type,
                base64_decode($m[2])
            ];
        }
        return false;
    }

    /**
     *
     * @param string|object $data
     * @return boolean
     */
    function isImageInsert($data)
    {
        if (is_object($data)) {
            if (property_exists($data, "insert")) {
                $insert = $data->insert;
                if (property_exists($insert, "image"))
                    return true;
            }
        }
        return false;
    }
}

