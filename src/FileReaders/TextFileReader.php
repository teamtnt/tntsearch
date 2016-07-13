<?php

namespace TeamTNT\TNTSearch\FileReaders;

class TextFileReader
{
    public function read($fileinfo)
    {
        return file_get_contents($fileinfo);
    }
}
