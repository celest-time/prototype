<?php

/*
 * Copyright (c) 2012, 2015, Oracle and/or its affiliates. All rights reserved.
 * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS FILE HEADER.
 *
 * This code is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License version 2 only, as
 * published by the Free Software Foundation.  Oracle designates this
 * particular file as subject to the "Classpath" exception as provided
 * by Oracle in the LICENSE file that accompanied this code.
 *
 * This code is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License
 * version 2 for more details (a copy is included in the LICENSE file that
 * accompanied this code).
 *
 * You should have received a copy of the GNU General Public License version
 * 2 along with this work; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * Please contact Oracle, 500 Oracle Parkway, Redwood Shores, CA 94065 USA
 * or visit www.oracle.com if you need additional information or have any
 * questions.
 */

/*
 * This file is available under and governed by the GNU General Public
 * License version 2 only, as published by the Free Software Foundation.
 * However, the following notice accompanied the original version of this
 * file:
 *
 * Copyright (c) 2007-2012, Stephen Colebourne & Michael Nascimento Santos
 *
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  * Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 *  * Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 *  * Neither the name of JSR-310 nor the names of its contributors
 *    may be used to endorse or promote products derived from this software
 *    without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace Php\Time;

use Php\Time\Helper\Math;
use Php\Time\Temporal\ChronoField;
use Php\Time\Temporal\Temporal;
use Php\Time\Temporal\TemporalAccessor;
use Php\Time\Temporal\TemporalAccessorDefaults;
use Php\Time\Temporal\TemporalAdjuster;
use Php\Time\Temporal\TemporalField;
use Php\Time\Temporal\TemporalQueries;
use Php\Time\Temporal\TemporalQuery;
use Php\Time\Temporal\UnsupportedTemporalTypeException;
use Php\Time\Temporal\ValueRange;

/**
 * A time-zone offset from Greenwich/UTC, such as {@code +02:00}.
 * <p>
 * A time-zone offset is the amount of time that a time-zone differs from Greenwich/UTC.
 * This is usually a fixed number of hours and minutes.
 * <p>
 * Different parts of the world have different time-zone offsets.
 * The rules for how offsets vary by place and time of year are captured in the
 * {@link ZoneId} class.
 * <p>
 * For example, Paris is one hour ahead of Greenwich/UTC in winter and two hours
 * ahead in summer. The {@code ZoneId} instance for Paris will reference two
 * {@code ZoneOffset} instances - a {@code +01:00} instance for winter,
 * and a {@code +02:00} instance for summer.
 * <p>
 * In 2008, time-zone offsets around the world extended from -12:00 to +14:00.
 * To prevent any problems with that range being extended, yet still provide
 * validation, the range of offsets is restricted to -18:00 to 18:00 inclusive.
 * <p>
 * This class is designed for use with the ISO calendar system.
 * The fields of hours, minutes and seconds make assumptions that are valid for the
 * standard ISO definitions of those fields. This class may be used with other
 * calendar systems providing the definition of the time fields matches those
 * of the ISO calendar system.
 * <p>
 * Instances of {@code ZoneOffset} must be compared using {@link #equals}.
 * Implementations may choose to cache certain common offsets, however
 * applications must not rely on such caching.
 *
 * <p>
 * This is a <a href="{@docRoot}/java/lang/doc-files/ValueBased.html">value-based</a>
 * class; use of identity-sensitive operations (including reference equality
 * ({@code ==}), identity hash code, or synchronization) on instances of
 * {@code ZoneOffset} may have unpredictable results and should be avoided.
 * The {@code equals} method should be used for comparisons.
 *
 * @implSpec
 * This class is immutable and thread-safe.
 *
 * @since 1.8
 */
final class ZoneOffset extends ZoneId implements TemporalAccessor, TemporalAdjuster
{
    public function init()
    {
        self::$UTC = ZoneOffset::ofTotalSeconds(0);
        self::$MIN = ZoneOffset::ofTotalSeconds(-self::MAX_SECONDS);
        self::$MAX = ZoneOffset::ofTotalSeconds(self::MAX_SECONDS);
        self::$SECONDS_CACHE = [];
        self::$ID_CACHE = [];
    }

    /** Cache of time-zone offset by offset in seconds. */
    private static $SECONDS_CACHE;
    /** Cache of time-zone offset by ID. */
    private static $ID_CACHE;

    /**
     * The abs maximum seconds.
     */
    const MAX_SECONDS = 18 * LocalTime::SECONDS_PER_HOUR;

    /**
     * The time-zone offset for UTC, with an ID of 'Z'.
     */
    public static function UTC()
    {
        return self::$UTC;
    }

    /** @var ZoneOffset */
    private static $UTC;

    /**
     * Constant for the minimum supported offset.
     * @return ZoneOffset
     */
    public static function MIN()
    {
        return self::$MIN;
    }

    /** @var ZoneOffset */
    private static $MIN;

    /**
     * Constant for the maximum supported offset.
     * @return ZoneOffset
     */
    public static function MAX()
    {
        return self::$MAX;
    }

    /** @var ZoneOffset */
    private static $MAX;

    /**
     * The total offset in seconds.
     * @var int
     */
    private $totalSeconds;
    /**
     * The string form of the time-zone offset.
     * @var string
     */
    private $id;

    //-----------------------------------------------------------------------
    /**
     * Obtains an instance of {@code ZoneOffset} using the ID.
     * <p>
     * This method parses the string ID of a {@code ZoneOffset} to
     * return an instance. The parsing accepts all the formats generated by
     * {@link #getId()}, plus some additional formats:
     * <ul>
     * <li>{@code Z} - for UTC
     * <li>{@code +h}
     * <li>{@code +hh}
     * <li>{@code +hh:mm}
     * <li>{@code -hh:mm}
     * <li>{@code +hhmm}
     * <li>{@code -hhmm}
     * <li>{@code +hh:mm:ss}
     * <li>{@code -hh:mm:ss}
     * <li>{@code +hhmmss}
     * <li>{@code -hhmmss}
     * </ul>
     * Note that &plusmn; means either the plus or minus symbol.
     * <p>
     * The ID of the returned offset will be normalized to one of the formats
     * described by {@link #getId()}.
     * <p>
     * The maximum supported range is from +18:00 to -18:00 inclusive.
     *
     * @param $offsetId string the offset ID, not null
     * @return ZoneOffset the zone-offset, not null
     * @throws DateTimeException if the offset ID is invalid
     */
    public static function  of($offsetId)
    {
        // "Z" is always in the cache
        // TODO check not undefined
        $offset = self::$ID_CACHE[$offsetId];
        if ($offset != null) {
            return $offset;
        }

        // parse - +h, +hh, +hhmm, +hh:mm, +hhmmss, +hh:mm:ss
        switch (strlen($offsetId)) {
            /** @noinspection PhpMissingBreakStatementInspection */
            case 2:
                $offsetId = $offsetId[0] . "0" . $offsetId[1];  // fallthru
            case 3:
                $hours = self::parseNumber($offsetId, 1, false);
                $minutes = 0;
                $seconds = 0;
                break;
            case 5:
                $hours = self::parseNumber($offsetId, 1, false);
                $minutes = self::parseNumber($offsetId, 3, false);
                $seconds = 0;
                break;
            case 6:
                $hours = self::parseNumber($offsetId, 1, false);
                $minutes = self::parseNumber($offsetId, 4, true);
                $seconds = 0;
                break;
            case 7:
                $hours = self::parseNumber($offsetId, 1, false);
                $minutes = self::parseNumber($offsetId, 3, false);
                $seconds = self::parseNumber($offsetId, 5, false);
                break;
            case 9:
                $hours = self::parseNumber($offsetId, 1, false);
                $minutes = self::parseNumber($offsetId, 4, true);
                $seconds = self::parseNumber($offsetId, 7, true);
                break;
            default:
                throw new DateTimeException("Invalid ID for ZoneOffset, invalid format: " . $offsetId);
        }
        $first = $offsetId[0];
        if ($first != '+' && $first != '-') {
            throw new DateTimeException("Invalid ID for ZoneOffset, plus/minus not found when expected: " . $offsetId);
        }
        if ($first == '-') {
            return self::ofHoursMinutesSeconds(-$hours, -$minutes, -$seconds);
        } else {
            return self::ofHoursMinutesSeconds($hours, $minutes, $seconds);
        }
    }

    /**
     * Parse a two digit zero-prefixed number.
     *
     * @param $offsetId string the offset ID, not null
     * @param $pos int the position to parse, valid
     * @param $precededByColon bool should this number be prefixed by a precededByColon
     * @return int the parsed number, from 0 to 99
     * @throws DateTimeException
     */
    private static function parseNumber($offsetId, $pos, $precededByColon)
    {
        if ($precededByColon && $offsetId[$pos - 1] !== ':') {
            throw new DateTimeException("Invalid ID for ZoneOffset, colon not found when expected: " . $offsetId);
        }

        $ch1 = $offsetId[$pos];
        $ch2 = $offsetId[$pos];
        if ($ch1 < '0' || $ch1 > '9' || $ch2 < '0' || $ch2 > '9') {
            throw new DateTimeException("Invalid ID for ZoneOffset, non numeric characters found: " . $offsetId);
        }
        // TODO check conversion
        return ($ch1 - 48) * 10 + ($ch2 - 48);
    }

    //-----------------------------------------------------------------------
    /**
     * Obtains an instance of {@code ZoneOffset} using an offset in hours.
     *
     * @param $hours int the time-zone offset in hours, from -18 to +18
     * @return ZoneOffset the zone-offset, not null
     * @throws DateTimeException if the offset is not in the required range
     */
    public static function ofHours($hours)
    {
        return self::ofHoursMinutesSeconds($hours, 0, 0);
    }

    /**
     * Obtains an instance of {@code ZoneOffset} using an offset in
     * hours and minutes.
     * <p>
     * The sign of the hours and minutes components must match.
     * Thus, if the hours is negative, the minutes must be negative or zero.
     * If the hours is zero, the minutes may be positive, negative or zero.
     *
     * @param $hours int the time-zone offset in hours, from -18 to +18
     * @param $minutes int the time-zone offset in minutes, from 0 to &plusmn;59, sign matches hours
     * @return ZoneOffset the zone-offset, not null
     * @throws DateTimeException if the offset is not in the required range
     */
    public
    static function ofHoursMinutes($hours, $minutes)
    {
        return self::ofHoursMinutesSeconds($hours, $minutes, 0);
    }

    /**
     * Obtains an instance of {@code ZoneOffset} using an offset in
     * hours, minutes and seconds.
     * <p>
     * The sign of the hours, minutes and seconds components must match.
     * Thus, if the hours is negative, the minutes and seconds must be negative or zero.
     *
     * @param $hours int the time-zone offset in hours, from -18 to +18
     * @param $minutes int the time-zone offset in minutes, from 0 to &plusmn;59, sign matches hours and seconds
     * @param $seconds int the time-zone offset in seconds, from 0 to &plusmn;59, sign matches hours and minutes
     * @return ZoneOffset the zone-offset, not null
     * @throws DateTimeException if the offset is not in the required range
     */
    public
    static function ofHoursMinutesSeconds($hours, $minutes, $seconds)
    {
        self::validate($hours, $minutes, $seconds);
        $totalSeconds = self::totalSeconds($hours, $minutes, $seconds);
        return self::ofTotalSeconds($totalSeconds);
    }

//-----------------------------------------------------------------------
    /**
     * Obtains an instance of {@code ZoneOffset} from a temporal object.
     * <p>
     * This obtains an offset based on the specified temporal.
     * A {@code TemporalAccessor} represents an arbitrary set of date and time information,
     * which this factory converts to an instance of {@code ZoneOffset}.
     * <p>
     * A {@code TemporalAccessor} represents some form of date and time information.
     * This factory converts the arbitrary temporal object to an instance of {@code ZoneOffset}.
     * <p>
     * The conversion uses the {@link TemporalQueries#offset()} query, which relies
     * on extracting the {@link ChronoField#OFFSET_SECONDS OFFSET_SECONDS} field.
     * <p>
     * This method matches the signature of the functional interface {@link TemporalQuery}
     * allowing it to be used as a query via method reference, {@code ZoneOffset::from}.
     *
     * @param $temporal TemporalAccessor the temporal object to convert, not null
     * @return ZoneOffset the zone-offset, not null
     * @throws DateTimeException if unable to convert to an {@code ZoneOffset}
     */
    public
    static function from(TemporalAccessor $temporal)
    {
        $offset = $temporal->query(TemporalQueries::offset());
        if ($offset == null) {
            throw new DateTimeException("Unable to obtain ZoneOffset from TemporalAccessor: " .
                $temporal . " of type " . get_class($temporal));
        }

        return $offset;
    }

//-----------------------------------------------------------------------
    /**
     * Validates the offset fields.
     *
     * @param $hours int the time-zone offset in hours, from -18 to +18
     * @param $minutes int the time-zone offset in minutes, from 0 to &plusmn;59
     * @param $seconds int the time-zone offset in seconds, from 0 to &plusmn;59
     * @throws DateTimeException if the offset is not in the required range
     */
    private
    static function validate($hours, $minutes, $seconds)
    {
        if ($hours < -18 || $hours > 18) {
            throw new DateTimeException("Zone offset hours not in valid range: value " . $hours .
                " is not in the range -18 to 18");
        }

        if ($hours > 0) {
            if ($minutes < 0 || $seconds < 0) {
                throw new DateTimeException("Zone offset minutes and seconds must be positive because hours is positive");
            }
        } else if ($hours < 0) {
            if ($minutes > 0 || $seconds > 0) {
                throw new DateTimeException("Zone offset minutes and seconds must be negative because hours is negative");
            }
        } else if (($minutes > 0 && $seconds < 0) || ($minutes < 0 && $seconds > 0)) {
            throw new DateTimeException("Zone offset minutes and seconds must have the same sign");
        }
        if (Math::abs($minutes) > 59) {
            throw new DateTimeException("Zone offset minutes not in valid range: abs(value) " .
                Math::abs($minutes) . " is not in the range 0 to 59");
        }
        if (Math::abs($seconds) > 59) {
            throw new DateTimeException("Zone offset seconds not in valid range: abs(value) " .
                Math::abs($seconds) . " is not in the range 0 to 59");
        }
        if (Math::abs($hours) == 18 && (Math::abs($minutes) > 0 || Math::abs($seconds) > 0)) {
            throw new DateTimeException("Zone offset not in valid range: -18:00 to +18:00");
        }
    }

    /**
     * Calculates the total offset in seconds.
     *
     * @param $hours int the time-zone offset in hours, from -18 to +18
     * @param $minutes int the time-zone offset in minutes, from 0 to &plusmn;59, sign matches hours and seconds
     * @param $seconds int the time-zone offset in seconds, from 0 to &plusmn;59, sign matches hours and minutes
     * @return int the total in seconds
     */
    private
    static function totalSeconds($hours, $minutes, $seconds)
    {
        return $hours * LocalTime::SECONDS_PER_HOUR + $minutes * LocalTime::SECONDS_PER_MINUTE + $seconds;
    }

//-----------------------------------------------------------------------
    /**
     * Obtains an instance of {@code ZoneOffset} specifying the total offset in seconds
     * <p>
     * The offset must be in the range {@code -18:00} to {@code +18:00}, which corresponds to -64800 to +64800.
     *
     * @param $totalSeconds int the total time-zone offset in seconds, from -64800 to +64800
     * @return ZoneOffset the ZoneOffset, not null
     * @throws DateTimeException if the offset is not in the required range
     */
    public
    static function ofTotalSeconds($totalSeconds)
    {
        if (Math::abs($totalSeconds) > self::MAX_SECONDS) {
            throw new DateTimeException("Zone offset not in valid range: -18:00 to +18:00");
        }

        if ($totalSeconds % (15 * LocalTime::SECONDS_PER_MINUTE) == 0) {
            $totalSecs = $totalSeconds;
            // TODO undefined
            $result = self::$SECONDS_CACHE->get($totalSecs);
            if ($result == null) {
                $result = new ZoneOffset($totalSeconds);
                self::$SECONDS_CACHE->putIfAbsent($totalSecs, $result);
                $result = self::$SECONDS_CACHE->get($totalSecs);
                self::$ID_CACHE->putIfAbsent($result->getId(), $result);
            }
            return $result;
        } else {
            return new ZoneOffset($totalSeconds);
        }
    }

//-----------------------------------------------------------------------
    /**
     * Constructor.
     * TODO visiblity
     * @param $totalSeconds int the total time-zone offset in seconds, from -64800 to +64800
     */
    public function __construct($totalSeconds)
    {
        parent::__construct();
        $this->totalSeconds = $totalSeconds;
        $this->id = $this->buildId($totalSeconds);
    }

    private
    static function buildId($totalSeconds)
    {
        if ($totalSeconds == 0) {
            return "Z";
        } else {
            $absTotalSeconds = Math::abs($totalSeconds);
            $buf = '';
            $absHours = $absTotalSeconds / LocalTime::SECONDS_PER_HOUR;
            $absMinutes = ($absTotalSeconds / LocalTime::SECONDS_PER_MINUTE) % LocalTime::MINUTES_PER_HOUR;
            $buf .= $totalSeconds < 0 ? "-" : "+"
            . $absHours < 10 ? "0" : "" . $absHours
            . $absMinutes < 10 ? ":0" : ":" . $absMinutes;
            $absSeconds = $absTotalSeconds % LocalTime::SECONDS_PER_MINUTE;
            if ($absSeconds != 0) {
                $buf .= $absSeconds < 10 ? ":0" : ":" . $absSeconds;
            }
            return $buf;
        }
    }

//-----------------------------------------------------------------------
    /**
     * Gets the total zone offset in seconds.
     * <p>
     * This is the primary way to access the offset amount.
     * It returns the total of the hours, minutes and seconds fields as a
     * single offset that can be added to a time.
     *
     * @return int the total zone offset amount in seconds
     */
    public function
    getTotalSeconds()
    {
        return $this->totalSeconds;
    }

    /**
     * Gets the normalized zone offset ID.
     * <p>
     * The ID is minor variation to the standard ISO-8601 formatted string
     * for the offset. There are three formats:
     * <ul>
     * <li>{@code Z} - for UTC (ISO-8601)
     * <li>{@code +hh:mm} or {@code -hh:mm} - if the seconds are zero (ISO-8601)
     * <li>{@code +hh:mm:ss} or {@code -hh:mm:ss} - if the seconds are non-zero (not ISO-8601)
     * </ul>
     *
     * @return string the zone offset ID, not null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Gets the associated time-zone rules.
     * <p>
     * The rules will always return this offset when queried.
     * The implementation class is immutable, thread-safe and serializable.
     *
     * @return ZoneRules the rules, not null
     */
    public function  getRules()
    {
        return ZoneRules::of($this);
    }

//-----------------------------------------------------------------------
    /**
     * Checks if the specified field is supported.
     * <p>
     * This checks if this offset can be queried for the specified field.
     * If false, then calling the {@link #range(TemporalField) range} and
     * {@link #get(TemporalField) get} methods will throw an exception.
     * <p>
     * If the field is a {@link ChronoField} then the query is implemented here.
     * The {@code OFFSET_SECONDS} field returns true.
     * All other {@code ChronoField} instances will return false.
     * <p>
     * If the field is not a {@code ChronoField}, then the result of this method
     * is obtained by invoking {@code TemporalField.isSupportedBy(TemporalAccessor)}
     * passing {@code this} as the argument.
     * Whether the field is supported is determined by the field.
     *
     * @param $field TemporalField the field to check, null returns false
     * @return bool true if the field is supported on this offset, false if not
     */
    public function isSupported(TemporalField $field)
    {
        if ($field instanceof ChronoField) {
            return $field == ChronoField::OFFSET_SECONDS();
        }

        return $field != null && $field->isSupportedBy($this);
    }

    /**
     * Gets the range of valid values for the specified field.
     * <p>
     * The range object expresses the minimum and maximum valid values for a field.
     * This offset is used to enhance the accuracy of the returned range.
     * If it is not possible to return the range, because the field is not supported
     * or for some other reason, an exception is thrown.
     * <p>
     * If the field is a {@link ChronoField} then the query is implemented here.
     * The {@link #isSupported(TemporalField) supported fields} will return
     * appropriate range instances.
     * All other {@code ChronoField} instances will throw an {@code UnsupportedTemporalTypeException}.
     * <p>
     * If the field is not a {@code ChronoField}, then the result of this method
     * is obtained by invoking {@code TemporalField.rangeRefinedBy(TemporalAccessor)}
     * passing {@code this} as the argument.
     * Whether the range can be obtained is determined by the field.
     *
     * @param $field TemporalField the field to query the range for, not null
     * @return ValueRange the range of valid values for the field, not null
     * @throws DateTimeException if the range for the field cannot be obtained
     * @throws UnsupportedTemporalTypeException if the field is not supported
     */
    public function range(TemporalField $field)
    {
        return TemporalAccessorDefaults::range($this, $field);
    }

    /**
     * Gets the value of the specified field from this offset as an {@code int}.
     * <p>
     * This queries this offset for the value of the specified field.
     * The returned value will always be within the valid range of values for the field.
     * If it is not possible to return the value, because the field is not supported
     * or for some other reason, an exception is thrown.
     * <p>
     * If the field is a {@link ChronoField} then the query is implemented here.
     * The {@code OFFSET_SECONDS} field returns the value of the offset.
     * All other {@code ChronoField} instances will throw an {@code UnsupportedTemporalTypeException}.
     * <p>
     * If the field is not a {@code ChronoField}, then the result of this method
     * is obtained by invoking {@code TemporalField.getFrom(TemporalAccessor)}
     * passing {@code this} as the argument. Whether the value can be obtained,
     * and what the value represents, is determined by the field.
     *
     * @param $field TemporalField the field to get, not null
     * @return int the value for the field
     * @throws DateTimeException if a value for the field cannot be obtained or
     *         the value is outside the range of valid values for the field
     * @throws UnsupportedTemporalTypeException if the field is not supported or
     *         the range of values exceeds an {@code int}
     * @throws ArithmeticException if numeric overflow occurs
     */
    public function get(TemporalField $field)
    {
        if ($field == ChronoField::OFFSET_SECONDS()) {
            return $this->totalSeconds;
        } else
            if ($field instanceof ChronoField) {
                throw new UnsupportedTemporalTypeException("Unsupported field: " . $field);
            }
        return $this->range($field)->checkValidIntValue($this->getLong($field), $field);
    }

    /**
     * Gets the value of the specified field from this offset as a {@code long}.
     * <p>
     * This queries this offset for the value of the specified field.
     * If it is not possible to return the value, because the field is not supported
     * or for some other reason, an exception is thrown.
     * <p>
     * If the field is a {@link ChronoField} then the query is implemented here.
     * The {@code OFFSET_SECONDS} field returns the value of the offset.
     * All other {@code ChronoField} instances will throw an {@code UnsupportedTemporalTypeException}.
     * <p>
     * If the field is not a {@code ChronoField}, then the result of this method
     * is obtained by invoking {@code TemporalField.getFrom(TemporalAccessor)}
     * passing {@code this} as the argument. Whether the value can be obtained,
     * and what the value represents, is determined by the field.
     *
     * @param field TemporalField the field to get, not null
     * @return int the value for the field
     * @throws DateTimeException if a value for the field cannot be obtained
     * @throws UnsupportedTemporalTypeException if the field is not supported
     * @throws ArithmeticException if numeric overflow occurs
     */
    public function getLong(TemporalField $field)
    {
        if ($field == ChronoField::OFFSET_SECONDS()) {
            return $this->totalSeconds;
        } else
            if ($field instanceof ChronoField) {
                throw new UnsupportedTemporalTypeException("Unsupported field: " . $field);
            }
        return $field->getFrom($this);
    }

    //-----------------------------------------------------------------------
    /**
     * Queries this offset using the specified query.
     * <p>
     * This queries this offset using the specified query strategy object.
     * The {@code TemporalQuery} object defines the logic to be used to
     * obtain the result. Read the documentation of the query to understand
     * what the result of this method will be.
     * <p>
     * The result of this method is obtained by invoking the
     * {@link TemporalQuery#queryFrom(TemporalAccessor)} method on the
     * specified query passing {@code this} as the argument.
     *
     * @param <R> the type of the result
     * @param  $query TemporalQuery the query to invoke, not null
     * @return mixed the query result, null may be returned (defined by the query)
     * @throws DateTimeException if unable to query (defined by the query)
     * @throws ArithmeticException if numeric overflow occurs (defined by the query)
     */
    public function query(TemporalQuery $query)
    {
        if ($query == TemporalQueries::offset() || $query == TemporalQueries::zone()) {
            return $this;
        }

        return TemporalAccessorDefaults::query($this, $query);
    }

    /**
     * Adjusts the specified temporal object to have the same offset as this object.
     * <p>
     * This returns a temporal object of the same observable type as the input
     * with the offset changed to be the same as this.
     * <p>
     * The adjustment is equivalent to using {@link Temporal#with(TemporalField, long)}
     * passing {@link ChronoField#OFFSET_SECONDS} as the field.
     * <p>
     * In most cases, it is clearer to reverse the calling pattern by using
     * {@link Temporal#with(TemporalAdjuster)}:
     * <pre>
     *   // these two lines are equivalent, but the second approach is recommended
     *   temporal = thisOffset.adjustInto(temporal);
     *   temporal = temporal.with(thisOffset);
     * </pre>
     * <p>
     * This instance is immutable and unaffected by this method call.
     *
     * @param $temporal Temporal the target object to be adjusted, not null
     * @return Temporal the adjusted object, not null
     * @throws DateTimeException if unable to make the adjustment
     * @throws ArithmeticException if numeric overflow occurs
     */
    public function adjustInto(Temporal $temporal)
    {
        return $temporal->with(ChronoField::OFFSET_SECONDS(), $this->totalSeconds);
    }

//-----------------------------------------------------------------------
    /**
     * Compares this offset to another offset in descending order.
     * <p>
     * The offsets are compared in the order that they occur for the same time
     * of day around the world. Thus, an offset of {@code +10:00} comes before an
     * offset of {@code +09:00} and so on down to {@code -18:00}.
     * <p>
     * The comparison is "consistent with equals", as defined by {@link Comparable}.
     *
     * @param $other ZoneOffset the other date to compare to, not null
     * @return int the comparator value, negative if less, postive if greater
     * @throws NullPointerException if {@code other} is null
     */
    public function compareTo(ZoneOffset $other)
    {
        return $other->totalSeconds - $this->totalSeconds;
    }

//-----------------------------------------------------------------------
    /**
     * Checks if this offset is equal to another offset.
     * <p>
     * The comparison is based on the amount of the offset in seconds.
     * This is equivalent to a comparison by ID.
     *
     * @param $obj mixed the object to check, null returns false
     * @return bool true if this is equal to the other offset
     */
    public function equals($obj)
    {
        if ($this === $obj) {
            return true;
        }

        if ($obj instanceof ZoneOffset) {
            return $this->totalSeconds == $obj->totalSeconds;
        }
        return false;
    }

//-----------------------------------------------------------------------
    /**
     * Outputs this offset as a {@code String}, using the normalized ID.
     *
     * @return string a string representation of this offset, not null
     */
    public function __toString()
    {
        return $this->id;
    }
}
