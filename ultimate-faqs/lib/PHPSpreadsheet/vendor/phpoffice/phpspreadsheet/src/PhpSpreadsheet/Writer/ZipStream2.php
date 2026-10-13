<?php

namespace EwdUfaqVendor\PhpOffice\PhpSpreadsheet\Writer;

use EwdUfaqVendor\ZipStream\Option\Archive;
use EwdUfaqVendor\ZipStream\ZipStream;
class ZipStream2
{
    /**
     * @param resource $fileHandle
     */
    public static function newZipStream($fileHandle) : ZipStream
    {
        $options = new Archive();
        $options->setEnableZip64(\false);
        $options->setOutputStream($fileHandle);
        return new ZipStream(null, $options);
    }
}
