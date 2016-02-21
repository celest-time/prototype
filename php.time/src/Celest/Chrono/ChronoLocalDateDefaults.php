<?php

namespace Celest\Chrono;

use Celest\DateTimeException;
use Celest\Format\DateTimeFormatter;
use Celest\Helper\Long;
use Celest\LocalTime;
use Celest\Temporal\ChronoField;
use Celest\Temporal\ChronoUnit;
use Celest\Temporal\Temporal;
use Celest\Temporal\TemporalAccessor;
use Celest\Temporal\TemporalAdjuster;
use Celest\Temporal\TemporalAmount;
use Celest\Temporal\TemporalDefaults;
use Celest\Temporal\TemporalField;
use Celest\Temporal\TemporalQueries;
use Celest\Temporal\TemporalQuery;
use Celest\Temporal\TemporalUnit;
use Celest\Temporal\UnsupportedTemporalTypeException;

final class ChronoLocalDateDefaults
{
    private function __construct()
    {
    }

    public static function timeLineOrder()
    {
        return AbstractChronology::DATE_ORDER();
    }

    public static function from(TemporalAccessor $temporal)
    {
        if ($temporal instanceof ChronoLocalDate) {
            return $temporal;
        }
        $chrono = $temporal->query(TemporalQueries::chronology());
        if ($chrono == null) {
            throw new DateTimeException("Unable to obtain ChronoLocalDate from TemporalAccessor: " . get_class($temporal));
        }
        return $chrono->date($temporal);
    }

    public static function getEra(ChronoLocalDate $_this)
    {
        return $_this->getChronology()->eraOf($_this->get(ChronoField::ERA()));
    }

    public static function isLeapYear(ChronoLocalDate $_this)
    {
        return $_this->getChronology()->isLeapYear($_this->getLong(ChronoField::YEAR()));
    }

    public static function lengthOfYear(ChronoLocalDate $_this)
    {
        return ($_this->isLeapYear() ? 366 : 365);
    }

    public static function isSupported(ChronoLocalDate $_this, TemporalField $field)
    {
        if ($field instanceof ChronoField) {
            return $field->isDateBased();
        }

        return $field != null && $field->isSupportedBy($_this);
    }

    public static function isUnitSupported(ChronoLocalDate $_this, TemporalUnit $unit)
    {
        if ($unit instanceof ChronoUnit) {
            return $unit->isDateBased();
        }

        return $unit != null && $unit->isSupportedBy($_this);
    }

    public static function adjust(ChronoLocalDate $_this, TemporalAdjuster $adjuster)
    {
        return ChronoLocalDateImpl::ensureValid($_this->getChronology(), TemporalDefaults::adjust($_this, $adjuster));
    }

    public static function with(ChronoLocalDate $_this, TemporalField $field, $newValue)
    {
        if ($field instanceof ChronoField) {
            throw new UnsupportedTemporalTypeException("Unsupported field: " . $field);
        }

        return ChronoLocalDateImpl::ensureValid($_this->getChronology(), $field->adjustInto($_this, $newValue));
    }

    public static function plusAmount(ChronoLocalDate $_this, TemporalAmount $amount)
    {
        return ChronoLocalDateImpl::ensureValid($_this->getChronology(), TemporalDefaults::plusAmount($_this, $amount));
    }

    public static function plus(ChronoLocalDate $_this, $amountToAdd, TemporalUnit $unit)
    {
        if ($unit instanceof ChronoUnit) {
            throw new UnsupportedTemporalTypeException("Unsupported unit: " . $unit);
        }
        return ChronoLocalDateImpl::ensureValid($_this->getChronology(), $unit->addTo($_this, $amountToAdd));
    }

    public static function minusAmount(ChronoLocalDate $_this, TemporalAmount $amount)
    {
        return ChronoLocalDateImpl::ensureValid($_this->getChronology(), TemporalDefaults::minusAmount($_this, $amount));
    }

    public static function minus(ChronoLocalDate $_this, $amountToSubtract, TemporalUnit $unit)
    {
        return ChronoLocalDateImpl::ensureValid($_this->getChronology(), TemporalDefaults::minus($_this, $amountToSubtract, $unit));
    }

    public static function query(ChronoLocalDate $_this, TemporalQuery $query)
    {
        if ($query == TemporalQueries::zoneId() || $query == TemporalQueries::zone() || $query == TemporalQueries::offset()) {
            return null;
        } else
            if ($query == TemporalQueries::localTime()) {
                return null;
            } else if ($query == TemporalQueries::chronology()) {
                return $_this->getChronology();
            } else if ($query == TemporalQueries::precision()) {
                return ChronoUnit::DAYS();
            }
        // inline TemporalAccessor.super.query(query) as an optimization
        // non-JDK classes are not permitted to make this optimization
        return $query->queryFrom($_this);
    }

    public static function adjustInto(ChronoLocalDate $_this, Temporal $temporal)
    {
        return $temporal->with(ChronoField::EPOCH_DAY(), $_this->toEpochDay());
    }

    public static function format(ChronoLocalDate $_this, DateTimeFormatter $formatter)
    {
        return $formatter->format($_this);
    }

    public static function atTime(ChronoLocalDate $_this, LocalTime $localTime)
    {
        return ChronoLocalDateTimeImpl::of($_this, $localTime);
    }

    public static function toEpochDay(ChronoLocalDate $_this)
    {
        return $_this->getLong(ChronoField::EPOCH_DAY());
    }

    public static function compareTo(ChronoLocalDate $_this, ChronoLocalDate $other)
    {
        $cmp = Long::compare($_this->toEpochDay(), $other->toEpochDay());
        if ($cmp == 0) {
            $cmp = $_this->getChronology()->compareTo($other->getChronology());
        }

        return $cmp;
    }

    public static function isAfter(ChronoLocalDate $_this, ChronoLocalDate $other)
    {
        return $_this->toEpochDay() > $other->toEpochDay();
    }

    public static function isBefore(ChronoLocalDate $_this, ChronoLocalDate $other)
    {
        return $_this->toEpochDay() < $other->toEpochDay();
    }

    public static function isEqual(ChronoLocalDate $_this, ChronoLocalDate $other)
    {
        return $_this->toEpochDay() == $other->toEpochDay();
    }
}