<?php

namespace Php\Time\Temporal;


use Php\Time\Helper\Long;

final class TemporalDefaults
{
    private function __construct()
    {
    }

    public static function adjust(Temporal $_this, TemporalAdjuster $adjuster)
    {
        return $adjuster->adjustInto($_this);
    }

    public static function plusAmount(Temporal $_this, TemporalAmount $amount)
    {
        return $amount->addTo($_this);
    }

    public static function minusAmount(Temporal $_this, TemporalAmount $amount)
    {
        return $amount->subtractFrom($_this);
    }

    public static function minus(Temporal $_this, $amountToSubtract, TemporalUnit $unit)
    {
        return ($amountToSubtract == Long::MIN_VALUE ? $_this->plus(Long::MAX_VALUE, $unit)->plus(1, $unit) : $_this->plus(-$amountToSubtract, $unit));
    }


}