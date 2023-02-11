<?php
// 代码来自hyperf
namespace think\swow;

class Cookie
{
    /**
     * Returns the cookie as a array.
     *
     * @return array The cookie
     */
    public static function toArray(\think\Cookie $cookie)
    {
        $cookies = [];

        foreach ($cookie->getCookie() as $name => $val) {
            [$value, $expire, $option] = $val;

            $str = urlencode($name) . '=';

            if ($value === '') {
                $str .= 'deleted; expires=' . gmdate('D, d-M-Y H:i:s T', time() - 31536001) . '; max-age=-31536001';
            } else {
                $str .= rawurlencode($value);

                if ($expire !== 0) {
                    $str .= '; expires=' . gmdate(
                        'D, d-M-Y H:i:s T',
                        $expire
                    ) . '; max-age=' . ($expire !== 0 ? $expire - time() : 0);
                }
            }

            if ($option['path']) {
                $str .= '; path=' . $option['path'];
            }

            if ($option['domain']) {
                $str .= '; domain=' . $option['domain'];
            }

            if ($option['secure'] === true) {
                $str .= '; secure';
            }

            if ($option['httponly'] === true) {
                $str .= '; httponly';
            }

            if ($option['samesite'] !== null) {
                $str .= '; samesite=' . $option['samesite'];
            }

            $cookies[] = $str;
        }

        return $cookies;
    }
}
