<?php

namespace Celest\Helper;

final class Math
{
    private function __construct()
    {
    }

    public static function binarySearch(array $a, $key)
    {
        $low = 0;
        $high = count($a) - 1;

        while ($low <= $high) {
            $mid = ($low + $high) >> 1;
            $midVal = $a[$mid];

            if ($midVal < $key)
                $low = $mid + 1;
            else if ($midVal > $key)
                $high = $mid - 1;
            else
                return $mid; // key found
        }
        return -($low + 1);  // key not found.
    }

    /**
     * @param int $val
     * @return int
     */
    public static function abs($val)
    {
        return \abs((int)$val);
    }

    /**
     * TODO overflow check
     * @param int $l
     * @param int $r
     * @return int
     */
    public static function multiplyExact($l, $r)
    {
        return (int)((int)$l * (int)$r);
    }

    /**
     * @param int $l
     * @param int $r
     * @return int
     */
    public static function addExact($l, $r)
    {
        return (int)((int)$l + (int)$r);
    }

    /**
     * @param int $dividend
     * @param int $divisor
     * @return int
     */
    public static function floorMod($dividend, $divisor)
    {
        return (($dividend % $divisor) + $divisor) % $divisor;
    }

    /**
     * @param int $dividend
     * @param int $divisor
     * @return int
     */
    public static function floorDiv($dividend, $divisor)
    {
        if (\function_exists('\intdiv')) {
            return $dividend >= 0 ? \intdiv($dividend, $divisor) : \intdiv($dividend + 1, $divisor) - 1;
        }
        return $dividend >= 0 ?  Math::div($dividend, $divisor) : Math::div($dividend + 1, $divisor) - 1;
    }

    /**
     * @param int $l
     * @param int $r
     * @return int
     */
    public static function subtractExact($l, $r)
    {
        return (int)($l - $r);
    }

    /**
     * @param int $a
     * @param int $b
     * @return int
     */
    public static function min($a, $b)
    {
        return \min($a, $b);
    }

    /**
     * @param int $val
     * @return int
     */
    public static function toIntExact($val)
    {
        return (int)$val;
    }

    public static function div($dividend, $divisor)
    {
        if (\function_exists('\intdiv')) {
            return \intdiv($dividend, $divisor);
        }
        return \gmp_intval(\gmp_div($dividend, $divisor));
    }

    public static function max($a, $b)
    {
        return \max($a, $b);
    }
}