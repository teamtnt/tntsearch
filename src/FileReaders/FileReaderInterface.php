<?php

namespace TeamTNT\TNTSearch\FileReaders;

interface FileReaderInterface
{
    /**
     * Read the content of a file
     *
     * @param  SplFileInfo  $fileinfo
     * @return string
     */
    public function read(SplFileInfo $fileinfo);
}