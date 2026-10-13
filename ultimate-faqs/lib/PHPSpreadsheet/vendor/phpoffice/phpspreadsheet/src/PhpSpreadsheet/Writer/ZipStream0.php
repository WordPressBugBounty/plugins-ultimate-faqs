<?php

namespace EwdUfaqVendor\PhpOffice\PhpSpreadsheet\Writer;

use EwdUfaqVendor\ZipStream\Option\Archive;
use EwdUfaqVendor\ZipStream\ZipStream;
class ZipStream0
{
    /**
     * @param resource $fileHandle
     */
    public static function newZipStream($fileHandle) : ZipStream
    {
        return \class_exists(Archive::class) ? ZipStream2::newZipStream($fileHandle) : ZipStream3::newZipStream($fileHandle);
    }
}
