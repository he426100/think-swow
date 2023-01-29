<?php

namespace {
    if (!function_exists('microdate')) {
        /**
         * 精确到微秒的date方法
         * @param string $format
         * @return string
         */
        function microdate(string $format = 'c'): string
        {
            return \DateTime::createFromFormat('0.u00 U', microtime())->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format($format);
        }
    }    
}

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
