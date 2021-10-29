<?php

namespace OliviaDownload;

class Download
{
    public function now($file, $fileName = null)
    {
        $nome = null;

        if (is_file($file)) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $nome = $fileName ?? $file;
            $nome .= '.' . $ext;
        }

        header("Content-type: application/octet-stream");
        header("Content-disposition: attachment; filename={$nome}");
        readfile($file);
    }
}
