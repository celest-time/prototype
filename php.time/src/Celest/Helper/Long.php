<?php

namespace Celest\Helper;


class Long
{
    // See https://bugs.php.net/bug.php?id=53934 for -1
    const MIN_VALUE = -9223372036854775807 - 1;
    const MAX_VALUE = 9223372036854775807;

    public static function compare($x, $y)
    {
        return ($x < $y) ? -1 : (($x === $y) ? 0 : 1);
    }

    /**
     * @param string $str
     * @return int
     */
    public static function parseLong($str)
    {
        return (int)$str;
    }
}