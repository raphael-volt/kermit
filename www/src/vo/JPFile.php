<?php
namespace src\vo;

class JPFile extends Vo
{
    const TYPE_IMAGE = 1;
    const TYPE_PICTO = 2;
    const TYPE_DOWNLOAD = 3;
    var $filetype;
    var $filename;
}

