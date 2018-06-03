<?php

namespace TeamTNT\TNTSearch\FileReaders;

use SplFileInfo;

class TextFileReader implements FileReaderInterface
{
	public $fileMapCallback = null;
	public $fileFilterCallback = null;

    public function read(SplFileInfo $fileinfo)
    {
        return file_get_contents($fileinfo);
    }
}
