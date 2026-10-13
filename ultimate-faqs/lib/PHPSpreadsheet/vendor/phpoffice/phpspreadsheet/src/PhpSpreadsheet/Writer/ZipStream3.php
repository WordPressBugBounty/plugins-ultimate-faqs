<?php

namespace EwdUfaqVendor\PhpOffice\PhpSpreadsheet\Writer;

use EwdUfaqVendor\ZipStream\Option\Archive;
use EwdUfaqVendor\ZipStream\ZipStream;
class ZipStream3
{
    /**
     * @param resource $fileHandle
     */
    public static function newZipStream($fileHandle) : ZipStream
    {
        return new ZipStream(enableZip64: \false, outputStream: $fileHandle, sendHttpHeaders: \false, defaultEnableZeroHeader: \false);
    }
}
