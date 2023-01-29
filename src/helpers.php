<?php

namespace think\swow\helper {

    use think\swow\response\File;

    function download(string $filename, string $name = '', $disposition = File::DISPOSITION_ATTACHMENT): File
    {
        $response = new File($filename, $disposition);

        if ($name) {
            $response->setContentDisposition($disposition, $name);
        }

        return $response;
    }

    function file(string $filename)
    {
        return new File($filename);
    }
}
