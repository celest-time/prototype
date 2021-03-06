<?php
/**
 * Enumeration of the style of a localized date, time or date-time formatter.
 * <p>
 * These styles are used when obtaining a date-time style from configuration.
 * See {@link DateTimeFormatter} and {@link DateTimeFormatterBuilder} for usage.
 *
 * @implSpec
 * This is an immutable and thread-safe enum.
 *
 * @since 1.8
 */

namespace Celest\Format;

use Celest\IllegalArgumentException;

class FormatStyle
{
    public static function init()
    {
        self::$FULL = new FormatStyle(0);
        self::$LONG = new FormatStyle(1);
        self::$MEDIUM = new FormatStyle(2);
        self::$SHORT = new FormatStyle(3);
    }
    // ordered from large to small

    /**
     * Full text style, with the most detail.
     * For example, the format might be 'Tuesday, April 12, 1952 AD' or '3:30:42pm PST'.
     * @return FormatStyle
     */
    public static function FULL()
    {
        return self::$FULL;
    }

    /** @var FormatStyle */
    public static $FULL;

    /**
     * Long text style, with lots of detail.
     * For example, the format might be 'January 12, 1952'.
     * @return FormatStyle
     */
    public static function LONG()
    {
        return self::$LONG;
    }

    /** @var FormatStyle */
    public static $LONG;

    /**
     * Medium text style, with some detail.
     * For example, the format might be 'Jan 12, 1952'.
     * @return FormatStyle
     */
    public static function MEDIUM()
    {
        return self::$MEDIUM;
    }

    /** @var FormatStyle */
    public static $MEDIUM;

    /**
     * Short text style, typically numeric.
     * For example, the format might be '12.13.52' or '3:30pm'.
     * @return FormatStyle
     */
    public static function SHORT()
    {
        return self::$SHORT;
    }

    /** @var FormatStyle */
    public static $SHORT;

    /** @var int */
    private $val;

    /**
     * @param int $val
     */
    private function __construct($val)
    {
        $this->val = $val;
    }

    /**
     * @return FormatStyle[]
     */
    public static function values()
    {
        return [self::FULL(), self::LONG(), self::MEDIUM(), self::SHORT()];
    }

    /**
     * @param string $name
     * @return FormatStyle
     * @throws IllegalArgumentException
     */
    public static function valueOf($name)
    {
        switch ($name) {
            case 'FULL':
                return self::FULL();
            case 'LONG':
                return self::LONG();
            case 'MEDIUM':
                return self::MEDIUM();
            case 'SHORT':
                return self::SHORT();
        }
        throw new IllegalArgumentException();
    }

    function ordinal()
    {
        return $this->val;
    }

    /**
     * @return string
     */
    function name()
    {
        switch ($this->val) {
            case 0:
                return 'FULL';
            case 1:
                return 'LONG';
            case 2:
                return 'MEDIUM';
            case 3:
                return 'SHORT';
        }
        return '';
    }

    function __toString()
    {
        return $this->name();
    }


}

FormatStyle::init();