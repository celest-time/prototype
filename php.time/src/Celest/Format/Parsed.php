<?php
/*
 * Copyright (c) 2012, 2013, Oracle and/or its affiliates. All rights reserved.
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
 * Copyright (c) 2008-2013, Stephen Colebourne & Michael Nascimento Santos
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
namespace Celest\Format;

use Celest\ArithmeticException;
use Celest\Chrono\ChronoLocalDate;
use Celest\Chrono\ChronoLocalDateTime;
use Celest\Chrono\Chronology;
use Celest\Chrono\ChronoZonedDateTime;
use Celest\DateTimeException;
use Celest\Helper\Math;
use Celest\Instant;
use Celest\LocalDate;
use Celest\LocalTime;
use Celest\Period;
use Celest\Temporal\ChronoField;
use Celest\Temporal\TemporalAccessorDefaults;
use Celest\Temporal\TemporalField;
use Celest\Temporal\TemporalAccessor;
use Celest\Temporal\TemporalQueries;
use Celest\Temporal\TemporalQuery;
use Celest\Temporal\UnsupportedTemporalTypeException;
use Celest\Temporal\ValueRange;
use Celest\ZoneId;
use Celest\ZoneOffset;

/**
 * A store of parsed data.
 * <p>
 * This class is used during parsing to collect the data. Part of the parsing process
 * involves handling optional blocks and multiple copies of the data get created to
 * support the necessary backtracking.
 * <p>
 * Once parsing is completed, this class can be used as the resultant {@code TemporalAccessor}.
 * In most cases, it is only exposed once the fields have been resolved.
 *
 * @implSpec
 * This class is a mutable context intended for use from a single thread.
 * Usage of the class is thread-safe within standard parsing as a new instance of this class
 * is automatically created for each parse and parsing is single-threaded
 *
 * @since 1.8
 */
final class Parsed implements TemporalAccessor
{
// some fields are accessed using package scope from DateTimeParseContext

    /**
     * The parsed fields.
     * @var array TemporalField->int
     */
    public $fieldValues = [];
    /**
     * The parsed zone.
     * @var ZoneId
     */
    public $zone;
    /**
     * The parsed chronology.
     * @var Chronology
     */
    public $chrono;
    /**
     * Whether a leap-second is parsed.
     * @var bool
     */
    public $leapSecond;
    /**
     * The resolver style to use.
     * @var ResolverStyle
     */
    private $resolverStyle;
    /**
     * The resolved date.
     * @var ChronoLocalDate
     */
    private $date;
    /**
     * The resolved time.
     * @var LocalTime
     */
    private $time;
    /**
     * The excess period from time-only parsing.
     * @var Period
     */
    public $excessDays;

    /**
     * Creates an instance.
     */
    public function __construct()
    {
        $this->excessDays = Period::ZERO();
    }

    /**
     * Creates a copy.
     * @return Parsed
     */
    public function copy()
    {
        // only copy fields used in parsing stage
        $cloned = new Parsed();
        $cloned->fieldValues = $this->fieldValues;
        $cloned->zone = $this->zone;
        $cloned->chrono = $this->chrono;
        $cloned->leapSecond = $this->leapSecond;
        return $cloned;
    }

//-----------------------------------------------------------------------
    /**
     * @param TemporalField $field
     * @return bool
     */
    public function isSupported(TemporalField $field)
    {
        if (array_key_exists($field->__toString(), $this->fieldValues) ||
            ($this->date != null && $this->date->isSupported($field)) ||
            ($this->time != null && $this->time->isSupported($field))
        ) {
            return true;
        }

        return $field != null && ($field instanceof ChronoField == false) && $field->isSupportedBy($this);
    }

    /**
     * @param TemporalField $field
     * @return int
     * @throws UnsupportedTemporalTypeException
     */
    public function getLong(TemporalField $field)
    {
        $value = @$this->fieldValues[$field->__toString()][1];
        if ($value !== null) {
            return $value;
        }

        if ($this->date !== null && $this->date->isSupported($field)) {
            return $this->date->getLong($field);
        }
        if ($this->time !== null && $this->time->isSupported($field)) {
            return $this->time->getLong($field);
        }
        if ($field instanceof ChronoField) {
            throw new UnsupportedTemporalTypeException("Unsupported field: " . $field);
        }
        return $field->getFrom($this);
    }

    public function query(TemporalQuery $query)
    {
        if ($query == TemporalQueries::zoneId()) {
            return $this->zone;
        } else
            if ($query == TemporalQueries::chronology()) {
                return $this->chrono;
            } else if ($query == TemporalQueries::localDate()) {
                return ($this->date != null ? LocalDate::from($this->date) : null);
            } else if ($query == TemporalQueries::localTime()) {
                return $this->time;
            } else if ($query == TemporalQueries::zone() || $query == TemporalQueries::offset()) {
                return $query->queryFrom($this);
            } else if ($query == TemporalQueries::precision()) {
                return null;  // not a complete date/time
            }
        // inline TemporalAccessor.super.query(query) as an optimization
        // non-JDK classes are not permitted to make this optimization
        return $query->queryFrom($this);
    }

//-----------------------------------------------------------------------
    /**
     * Resolves the fields in this context.
     *
     * @param ResolverStyle $resolverStyle the resolver style, not null
     * @param array|null $resolverFields the fields to use for resolving, null for all fields
     * @return $this, for method chaining
     * @throws DateTimeException if resolving one field results in a value for
     *  another field that is in conflict
     */
    public function resolve(ResolverStyle $resolverStyle, &$resolverFields)
    {
        if ($resolverFields !== null) {
            $this->fieldValues->keySet()->retainAll($resolverFields);
        }

        $this->resolverStyle = $resolverStyle;
        $this->resolveFields();
        $this->resolveTimeLenient();
        $this->crossCheck();
        $this->resolvePeriod();
        $this->resolveFractional();
        $this->resolveInstant();
        return $this;
    }

//-----------------------------------------------------------------------
    private
    function resolveFields()
    {
        // resolve ChronoField
        $this->resolveInstantFields();
        $this->resolveDateFields();
        $this->resolveTimeFields();

        // if any other fields, handle them
        // any lenient date resolution should return epoch-day
        if (!empty($this->fieldValues)) {
            $changedCount = 0;
            outer:
            while ($changedCount < 50) {
                foreach ($this->fieldValues as $entry) {
                    /** @var ChronoField $targetField */
                    $targetField = $entry[0];
                    $resolvedObject = $targetField->resolve($this->fieldValues, $this, $this->resolverStyle);
                    if ($resolvedObject !== null) {
                        if ($resolvedObject instanceof ChronoZonedDateTime) {
                            $czdt = $resolvedObject;
                            if ($this->zone === null) {
                                $this->zone = $czdt->getZone();
                            } else
                                if ($this->zone->equals($czdt->getZone()) == false) {
                                    throw new DateTimeException("ChronoZonedDateTime must use the effective parsed zone: " . $this->zone);
                                }
                            $resolvedObject = $czdt->toLocalDateTime();
                        }
                        if ($resolvedObject instanceof ChronoLocalDateTime) {
                            $cldt = $resolvedObject;
                            $this->updateCheckConflict($cldt->toLocalTime(), Period::ZERO());
                            $this->updateCheckConflict1($cldt->toLocalDate());
                            $changedCount++;
                            continue 2;  // have to restart to avoid concurrent modification
                        }
                        if ($resolvedObject instanceof ChronoLocalDate) {
                            $this->updateCheckConflict1($resolvedObject);
                            $changedCount++;
                            continue 2;  // have to restart to avoid concurrent modification
                        }
                        if ($resolvedObject instanceof LocalTime) {
                            $this->updateCheckConflict($resolvedObject, Period::ZERO());
                            $changedCount++;
                            continue 2;  // have to restart to avoid concurrent modification
                        }
                        throw new DateTimeException("Method resolve() can only return ChronoZonedDateTime, " .
                            "ChronoLocalDateTime, ChronoLocalDate or LocalTime");
                    } else if (array_key_exists($targetField->__toString(), $this->fieldValues) === false) {
                        $changedCount++;
                        continue 2;  // have to restart to avoid concurrent modification
                    }
                }
                break;
            }
            if ($changedCount === 50) {  // catch infinite loops
                throw new DateTimeException("One of the parsed fields has an incorrectly implemented resolve method");
            }
            // if something changed then have to redo ChronoField resolve
            if ($changedCount > 0) {
                $this->resolveInstantFields();
                $this->resolveDateFields();
                $this->resolveTimeFields();
            }
        }
    }

    private
    function updateCheckConflict3(TemporalField $targetField, TemporalField $changeField, $changeValue)
    {
        $old = @$this->fieldValues[$changeField->__toString()][1];
        $this->fieldValues[$changeField->__toString()] = [$changeField, $changeValue];

        if ($old !== null && $old !== $changeValue) {
            throw new DateTimeException("Conflict found: " . $changeField . " " . $old .
                " differs from " . $changeField . " " . $changeValue .
                " while resolving  " . $targetField);
        }
    }

//-----------------------------------------------------------------------
    private
    function resolveInstantFields()
    {
        // resolve parsed instant seconds to date and time if zone available
        if (array_key_exists(ChronoField::INSTANT_SECONDS()->__toString(), $this->fieldValues)) {
            if ($this->zone !== null) {
                $this->resolveInstantFields0($this->zone);
            } else {
                $offsetSecs = @$this->fieldValues[ChronoField::OFFSET_SECONDS()->__toString()][1];
                if ($offsetSecs !== null) {
                    $offset = ZoneOffset::ofTotalSeconds($offsetSecs);
                    $this->resolveInstantFields0($offset);
                }
            }
        }
    }

    private
    function resolveInstantFields0(ZoneId $selectedZone)
    {
        $instant = Instant::ofEpochSecond($this->fieldValues->remove(ChronoField::INSTANT_SECONDS()));
        $zdt = $this->chrono->zonedDateTime($instant, $selectedZone);
        $this->updateCheckConflict1($zdt->toLocalDate());
        $this->updateCheckConflict3(ChronoField::INSTANT_SECONDS(), ChronoField::SECOND_OF_DAY(), $zdt->toLocalTime()->toSecondOfDay());
    }

//-----------------------------------------------------------------------
    private
    function resolveDateFields()
    {
        $this->updateCheckConflict1($this->chrono->resolveDate($this->fieldValues, $this->resolverStyle));
    }

    /**
     * @param ChronoLocalDate|null $cld
     * @throws DateTimeException
     */
    private
    function updateCheckConflict1($cld)
    {
        if ($this->date != null) {
            if ($cld != null && $this->date->equals($cld) == false) {
                throw new DateTimeException("Conflict found: Fields resolved to two different dates: " . $this->date . " " . $cld);
            }
        } else if ($cld != null) {
            if ($this->chrono->equals($cld->getChronology()) == false) {
                throw new DateTimeException("ChronoLocalDate must use the effective parsed chronology: " . $this->chrono);
            }
            $this->date = $cld;
        }
    }

//-----------------------------------------------------------------------
    private
    function resolveTimeFields()
    {
// simplify fields
        if (array_key_exists(ChronoField::CLOCK_HOUR_OF_DAY()->__toString(), $this->fieldValues)) {
// lenient allows anything, smart allows 0-24, strict allows 1-24
            $ch = $this->fieldValues->remove(ChronoField::CLOCK_HOUR_OF_DAY());
            if ($this->resolverStyle == ResolverStyle::STRICT() || ($this->resolverStyle == ResolverStyle::SMART() && $ch != 0)) {
                ChronoField::CLOCK_HOUR_OF_DAY()->checkValidValue($ch);
            }

            $this->updateCheckConflict3(ChronoField::CLOCK_HOUR_OF_DAY(), ChronoField::HOUR_OF_DAY(), $ch == 24 ? 0 : $ch);
        }
        if (isset($this->fieldValues[ChronoField::CLOCK_HOUR_OF_AMPM()->__toString()])) {
// lenient allows anything, smart allows 0-12, strict allows 1-12
            $ch = $this->fieldValues[ChronoField::CLOCK_HOUR_OF_AMPM()->__toString()][1];
            unset($this->fieldValues[ChronoField::CLOCK_HOUR_OF_AMPM()->__toString()]);
            if ($this->resolverStyle == ResolverStyle::STRICT() || ($this->resolverStyle == ResolverStyle::SMART() && $ch != 0)) {
                ChronoField::CLOCK_HOUR_OF_AMPM()->checkValidValue($ch);
            }
            $this->updateCheckConflict3(ChronoField::CLOCK_HOUR_OF_AMPM(), ChronoField::HOUR_OF_AMPM(), $ch == 12 ? 0 : $ch);
        }
        if (isset($this->fieldValues[ChronoField::AMPM_OF_DAY()->__toString()])) {
            $ap = $this->fieldValues[ChronoField::AMPM_OF_DAY()->__toString()][1];
            $hap = $this->fieldValues[ChronoField::HOUR_OF_AMPM()->__toString()][1];
            unset($this->fieldValues[ChronoField::AMPM_OF_DAY()->__toString()]);
            unset($this->fieldValues[ChronoField::HOUR_OF_AMPM()->__toString()]);
            if ($this->resolverStyle == ResolverStyle::LENIENT()) {
                $this->updateCheckConflict3(ChronoField::AMPM_OF_DAY(), ChronoField::HOUR_OF_DAY(), Math::addExact(Math::multiplyExact($ap, 12), $hap));
            } else {  // STRICT or SMART
                ChronoField::AMPM_OF_DAY()->checkValidValue($ap);
                ChronoField::HOUR_OF_AMPM()->checkValidValue($ap);
                $this->updateCheckConflict3(ChronoField::AMPM_OF_DAY(), ChronoField::HOUR_OF_DAY(), $ap * 12 + $hap);
            }
        }
        if (array_key_exists(ChronoField::NANO_OF_DAY()->__toString(), $this->fieldValues)) {
            $nod = $this->fieldValues->remove(ChronoField::NANO_OF_DAY());
            if ($this->resolverStyle != ResolverStyle::LENIENT()) {
                ChronoField::NANO_OF_DAY()->checkValidValue($nod);
            }
            $this->updateCheckConflict3(ChronoField::NANO_OF_DAY(), ChronoField::HOUR_OF_DAY(), $nod / 3600000000000);
            $this->updateCheckConflict3(ChronoField::NANO_OF_DAY(), ChronoField::MINUTE_OF_HOUR(), ($nod / 60000000000) % 60);
            $this->updateCheckConflict3(ChronoField::NANO_OF_DAY(), ChronoField::SECOND_OF_MINUTE(), ($nod / 1000000000) % 60);
            $this->updateCheckConflict3(ChronoField::NANO_OF_DAY(), ChronoField::NANO_OF_SECOND(), $nod % 1000000000);
        }
        if (array_key_exists(ChronoField::MICRO_OF_DAY()->__toString(), $this->fieldValues)) {
            $cod = $this->fieldValues->remove(ChronoField::MICRO_OF_DAY());
            if ($this->resolverStyle != ResolverStyle::LENIENT()) {
                ChronoField::MICRO_OF_DAY()->checkValidValue($cod);
            }
            $this->updateCheckConflict3(ChronoField::MICRO_OF_DAY(), ChronoField::SECOND_OF_DAY(), $cod / 1000000);
            $this->updateCheckConflict3(ChronoField::MICRO_OF_DAY(), ChronoField::MICRO_OF_SECOND(), $cod % 1000000);
        }
        if (array_key_exists(ChronoField::MILLI_OF_DAY()->__toString(), $this->fieldValues)) {
            $lod = $this->fieldValues->remove(ChronoField::MILLI_OF_DAY());
            if ($this->resolverStyle != ResolverStyle::LENIENT()) {
                ChronoField::MILLI_OF_DAY()->checkValidValue($lod);
            }
            $this->updateCheckConflict3(ChronoField::MILLI_OF_DAY(), ChronoField::SECOND_OF_DAY(), $lod / 1000);
            $this->updateCheckConflict3(ChronoField::MILLI_OF_DAY(), ChronoField::MILLI_OF_SECOND(), $lod % 1000);
        }
        if (array_key_exists(ChronoField::SECOND_OF_DAY()->__toString(), $this->fieldValues)) {
            $sod = $this->fieldValues->remove(ChronoField::SECOND_OF_DAY());
            if ($this->resolverStyle != ResolverStyle::LENIENT()) {
                ChronoField::SECOND_OF_DAY()->checkValidValue($sod);
            }
            $this->updateCheckConflict3(ChronoField::SECOND_OF_DAY(), ChronoField::HOUR_OF_DAY(), $sod / 3600);
            $this->updateCheckConflict3(ChronoField::SECOND_OF_DAY(), ChronoField::MINUTE_OF_HOUR(), ($sod / 60) % 60);
            $this->updateCheckConflict3(ChronoField::SECOND_OF_DAY(), ChronoField::SECOND_OF_MINUTE(), $sod % 60);
        }
        if (array_key_exists(ChronoField::MINUTE_OF_DAY()->__toString(), $this->fieldValues)) {
            $mod = $this->fieldValues->remove(ChronoField::MINUTE_OF_DAY());
            if ($this->resolverStyle != ResolverStyle::LENIENT()) {
                ChronoField::MINUTE_OF_DAY()->checkValidValue($mod);
            }
            $this->updateCheckConflict3(ChronoField::MINUTE_OF_DAY(), ChronoField::HOUR_OF_DAY(), $mod / 60);
            $this->updateCheckConflict3(ChronoField::MINUTE_OF_DAY(), ChronoField::MINUTE_OF_HOUR(), $mod % 60);
        }

// combine partial second fields strictly, leaving lenient expansion to later
        if (array_key_exists(ChronoField::NANO_OF_SECOND()->__toString(), $this->fieldValues)) {
            $nos = $this->fieldValues[ChronoField::NANO_OF_SECOND()->__toString()][1];
            if ($this->resolverStyle != ResolverStyle::LENIENT()) {
                ChronoField::NANO_OF_SECOND()->checkValidValue($nos);
            }
            if (array_key_exists(ChronoField::MICRO_OF_SECOND()->__toString(), $this->fieldValues)) {
                $cos = $this->fieldValues[ChronoField::MICRO_OF_SECOND()->__toString()][1];
                unset($this->fieldValues[ChronoField::MICRO_OF_SECOND()->__toString()]);
                if ($this->resolverStyle != ResolverStyle::LENIENT()) {
                    ChronoField::MICRO_OF_SECOND()->checkValidValue($cos);
                }
                $nos = $cos * 1000 + ($nos % 1000);
                $this->updateCheckConflict3(ChronoField::MICRO_OF_SECOND(), ChronoField::NANO_OF_SECOND(), $nos);
            }
            if (array_key_exists(ChronoField::MILLI_OF_SECOND()->__toString(), $this->fieldValues)) {
                $los = $this->fieldValues[ChronoField::MILLI_OF_SECOND()->__toString()][1];
                unset($this->fieldValues[ChronoField::MILLI_OF_SECOND()->__toString()]);
                if ($this->resolverStyle != ResolverStyle::LENIENT()) {
                    ChronoField::MILLI_OF_SECOND()->checkValidValue($los);
                }
                $this->updateCheckConflict3(ChronoField::MILLI_OF_SECOND(), ChronoField::NANO_OF_SECOND(), $los * 1000000 + ($nos % 1000000));
            }
        }

// convert to time if all four fields available (optimization)
        if (array_key_exists(ChronoField::HOUR_OF_DAY()->__toString(), $this->fieldValues) && array_key_exists(ChronoField::MINUTE_OF_HOUR()->__toString(), $this->fieldValues) &&
            array_key_exists(ChronoField::SECOND_OF_MINUTE()->__toString(), $this->fieldValues) && array_key_exists(ChronoField::NANO_OF_SECOND()->__toString(), $this->fieldValues)
        ) {
            $hod = $this->fieldValues->remove(ChronoField::HOUR_OF_DAY());
            $moh = $this->fieldValues->remove(ChronoField::MINUTE_OF_HOUR());
            $som = $this->fieldValues->remove(ChronoField::SECOND_OF_MINUTE());
            $nos = $this->fieldValues->remove(ChronoField::NANO_OF_SECOND());
            $this->resolveTime($hod, $moh, $som, $nos);
        }
    }

    private
    function resolveTimeLenient()
    {
// leniently create a time from incomplete information
// done after everything else as it creates information from nothing
// which would break updateCheckConflict(field)

        if ($this->time == null) {
// ChronoField::NANO_OF_SECOND() merged with MILLI/MICRO above
            if (array_key_exists(ChronoField::MILLI_OF_SECOND()->__toString(), $this->fieldValues)) {
                $los = $this->fieldValues->remove(ChronoField::MILLI_OF_SECOND());
                if (array_key_exists(ChronoField::MICRO_OF_SECOND()->__toString(), $this->fieldValues)) {
// merge milli-of-second and micro-of-second for better error message
                    $cos = $los * 1000 + ($this->fieldValues->get(ChronoField::MICRO_OF_SECOND()) % 1000);
                    $this->updateCheckConflict(ChronoField::MILLI_OF_SECOND(), ChronoField::MICRO_OF_SECOND(), $cos);
                    $this->fieldValues->remove(ChronoField::MICRO_OF_SECOND());
                    $this->fieldValues->put(ChronoField::NANO_OF_SECOND(), $cos * 1000);
                } else {
// convert milli-of-second to nano-of-second
                    $this->fieldValues->put(ChronoField::NANO_OF_SECOND(), $los * 1000000);
                }
            } else
                if (array_key_exists(ChronoField::MICRO_OF_SECOND()->__toString(), $this->fieldValues)) {
// convert micro-of-second to nano-of-second
                    $cos = $this->fieldValues->remove(ChronoField::MICRO_OF_SECOND());
                    $this->fieldValues->put(ChronoField::NANO_OF_SECOND(), $cos * 1000);
                }

// merge hour/minute/second/nano leniently
            $hod = @$this->fieldValues[ChronoField::HOUR_OF_DAY()->__toString()][1];
            if ($hod !== null) {
                $moh = @$this->fieldValues[ChronoField::MINUTE_OF_HOUR()->__toString()][1];
                $som = @$this->fieldValues[ChronoField::SECOND_OF_MINUTE()->__toString()][1];
                $nos = @$this->fieldValues[ChronoField::NANO_OF_SECOND()->__toString()][1];

// check for invalid combinations that cannot be defaulted
                if (($moh === null && ($som !== null || $nos !== null)) ||
                    ($moh !== null && $som === null && $nos !== null)
                ) {
                    return;
                }

// default as necessary and build time
                $mohVal = ($moh !== null ? $moh : 0);
                $somVal = ($som !== null ? $som : 0);
                $nosVal = ($nos !== null ? $nos : 0);
                $this->resolveTime($hod, $mohVal, $somVal, $nosVal);
                unset($this->fieldValues[ChronoField::HOUR_OF_DAY()->__toString()]);
                unset($this->fieldValues[ChronoField::MINUTE_OF_HOUR()->__toString()]);
                unset($this->fieldValues[ChronoField::SECOND_OF_MINUTE()->__toString()]);
                unset($this->fieldValues[ChronoField::NANO_OF_SECOND()->__toString()]);
            }
        }

// validate remaining
        if ($this->resolverStyle != ResolverStyle::LENIENT() && !empty($this->fieldValues)) {
            foreach ($this->fieldValues as $entry) {
                $field = $entry[0];
                if ($field instanceof ChronoField && $field->isTimeBased()) {
                    $field->checkValidValue($entry[1]);
                }
            }
        }
    }

    private
    function resolveTime($hod, $moh, $som, $nos)
    {
        if ($this->resolverStyle == ResolverStyle::LENIENT()) {
            $totalNanos = Math::multiplyExact($hod, 3600000000000);
            $totalNanos = Math::addExact($totalNanos, Math::multiplyExact($moh, 60000000000));
            $totalNanos = Math::addExact($totalNanos, Math::multiplyExact($som, 1000000000));
            $totalNanos = Math::addExact($totalNanos, $nos);
            $excessDays = (int)Math::floorDiv($totalNanos, 86400000000000);  // safe int cast
            $nod = Math::floorMod($totalNanos, 86400000000000);
            $this->updateCheckConflict(LocalTime::ofNanoOfDay($nod), Period::ofDays($excessDays));
        } else {  // STRICT or SMART
            $mohVal = ChronoField::MINUTE_OF_HOUR()->checkValidIntValue($moh);
            $nosVal = ChronoField::NANO_OF_SECOND()->checkValidIntValue($nos);
// handle 24:00 end of day
            if ($this->resolverStyle == ResolverStyle::SMART() && $hod == 24 && $mohVal == 0 && $som == 0 && $nosVal == 0) {
                $this->updateCheckConflict(LocalTime::MIDNIGHT(), Period::ofDays(1));
            } else {
                $hodVal = ChronoField::HOUR_OF_DAY()->checkValidIntValue($hod);
                $somVal = ChronoField::SECOND_OF_MINUTE()->checkValidIntValue($som);
                $this->updateCheckConflict(LocalTime::of($hodVal, $mohVal, $somVal, $nosVal), Period::ZERO());
            }
        }
    }

    private
    function resolvePeriod()
    {
// add whole days if we have both date and time
        if ($this->date != null && $this->time != null && $this->excessDays->isZero() == false) {
            $this->date = $this->date->plusAmount($this->excessDays);
            $this->excessDays = Period::ZERO();
        }
    }

    private
    function resolveFractional()
    {
// ensure fractional seconds available as ChronoField requires
// resolveTimeLenient() will have merged ChronoField::MICRO_OF_SECOND()/MILLI_OF_SECOND to NANO_OF_SECOND
        if ($this->time == null &&
            (array_key_exists(ChronoField::INSTANT_SECONDS()->__toString(), $this->fieldValues) ||
                array_key_exists(ChronoField::SECOND_OF_DAY()->__toString(), $this->fieldValues) ||
                array_key_exists(ChronoField::SECOND_OF_MINUTE()->__toString(), $this->fieldValues))
        ) {
            if (array_key_exists(ChronoField::NANO_OF_SECOND()->__toString(), $this->fieldValues)) {
                $nos = $this->fieldValues[ChronoField::NANO_OF_SECOND()->__toString()][1];
                $this->fieldValues[ChronoField::MICRO_OF_SECOND()->__toString()] = Math::div($nos, 1000);
                $this->fieldValues[ChronoField::MILLI_OF_SECOND()->__toString()] = Math::div($nos, 1000000);
            } else {
                $this->fieldValues[ChronoField::NANO_OF_SECOND()->__toString()] = 0;
                $this->fieldValues[ChronoField::MICRO_OF_SECOND()->__toString()] = 0;
                $this->fieldValues[ChronoField::MILLI_OF_SECOND()->__toString()] = 0;
            }
        }
    }

    private
    function resolveInstant()
    {
// add instant seconds if we have date, time and zone
        if ($this->date !== null && $this->time !== null) {
            if ($this->zone !== null) {
                $instant = $this->date->atTime($this->time)->atZone($this->zone)->getLong(ChronoField::INSTANT_SECONDS());
                $this->fieldValues->put(ChronoField::INSTANT_SECONDS(), $instant);
            } else {
                $offsetSecs = @$this->fieldValues[ChronoField::OFFSET_SECONDS()->__toString()];
                if ($offsetSecs !== null) {
                    $offset = ZoneOffset::ofTotalSeconds($offsetSecs);
                    $instant = $this->date->atTime($this->time)->atZone($offset)->getLong(ChronoField::INSTANT_SECONDS());
                    $this->fieldValues->put(ChronoField::INSTANT_SECONDS(), $instant);
                }
            }
        }
    }

    private
    function updateCheckConflict(LocalTime $timeToSet, Period $periodToSet)
    {
        if ($this->time != null) {
            if ($this->time->equals($timeToSet) == false) {
                throw new DateTimeException("Conflict found: Fields resolved to different times: " . $this->time . " " . $timeToSet);
            }
            if ($this->excessDays->isZero() == false && $periodToSet->isZero() == false && $this->excessDays->equals($periodToSet) == false) {
                throw new DateTimeException("Conflict found: Fields resolved to different excess periods: " . $this->excessDays . " " . $periodToSet);
            } else {
                $excessDays = $periodToSet;
            }
        } else {
            $this->time = $timeToSet;
            $this->excessDays = $periodToSet;
        }
    }

//-----------------------------------------------------------------------
    private
    function crossCheck()
    {
// only cross-check date, time and date-time
// avoid object creation if possible
        if ($this->date !== null) {
            $this->crossCheck1($this->date);
        }
        if ($this->time !== null) {
            $this->crossCheck1($this->time);
            if ($this->date !== null && count($this->fieldValues) > 0) {
                $this->crossCheck1($this->date->atTime($this->time));
            }
        }
    }

    private
    function crossCheck1(TemporalAccessor $target)
    {
        foreach ($this->fieldValues as $key => $it) {
            /** @var ChronoField $field */
            $field = $it[0];
            $entry = $it[1];
            if ($target->isSupported($field)) {
                try {
                    $val1 = $target->getLong($field);
                } catch (\RuntimeException $ex) {
                    continue;
                }
                $val2 = $entry;
                if ($val1 !== $val2) {
                    throw new DateTimeException("Conflict found: Field " . $field . " " . $val1 .
                        " differs from " . $field . " " . $val2 . " derived from " . $target);
                }
                unset($this->fieldValues[$key]);
            }
        }
    }

//-----------------------------------------------------------------------
    public function __toString()
    {
        $buf = '[';
        $sep = '';
        foreach ($this->fieldValues as $entry) {
            $buf .= $entry[0] . '=' . $entry[1] . $sep;
            $sep = ',';
        }
        $buf .= '],' . $this->chrono;
        if ($this->zone != null) {
            $buf .= ',' . $this->zone;
        }
        if ($this->date != null || $this->time != null) {
            $buf .= " resolved to ";
            if ($this->date != null) {
                $buf .= $this->date;
                if ($this->time != null) {
                    $buf .= 'T' . $this->time;
                }
            } else {
                $buf .= $this->time;
            }
        }
        return $buf;
    }

    /**
     * Gets the range of valid values for the specified field.
     * <p>
     * All fields can be expressed as a {@code long} integer.
     * This method returns an object that describes the valid range for that value.
     * The value of this temporal object is used to enhance the accuracy of the returned range.
     * If the date-time cannot return the range, because the field is unsupported or for
     * some other reason, an exception will be thrown.
     * <p>
     * Note that the result only describes the minimum and maximum valid values
     * and it is important not to read too much into them. For example, there
     * could be values within the range that are invalid for the field.
     *
     * @implSpec
     * Implementations must check and handle all fields defined in {@link ChronoField}.
     * If the field is supported, then the range of the field must be returned.
     * If unsupported, then an {@code UnsupportedTemporalTypeException} must be thrown.
     * <p>
     * If the field is not a {@code ChronoField}, then the result of this method
     * is obtained by invoking {@code TemporalField.rangeRefinedBy(TemporalAccessorl)}
     * passing {@code this} as the argument.
     * <p>
     * Implementations must ensure that no observable state is altered when this
     * read-only method is invoked.
     * <p>
     * The default implementation must behave equivalent to this code:
     * <pre>
     *  if (field instanceof ChronoField) {
     *    if (isSupported(field)) {
     *      return field.range();
     *    }
     *    throw new UnsupportedTemporalTypeException("Unsupported field: " + field);
     *  }
     *  return field.rangeRefinedBy(this);
     * </pre>
     *
     * @param TemporalField $field the field to query the range for, not null
     * @return ValueRange the range of valid values for the field, not null
     * @throws DateTimeException if the range for the field cannot be obtained
     * @throws UnsupportedTemporalTypeException if the field is not supported
     */
    public function range(TemporalField $field)
    {
        return TemporalAccessorDefaults::range($this, $field);
    }

    /**
     * Gets the value of the specified field as an {@code int}.
     * <p>
     * This queries the date-time for the value of the specified field.
     * The returned value will always be within the valid range of values for the field.
     * If the date-time cannot return the value, because the field is unsupported or for
     * some other reason, an exception will be thrown.
     *
     * @implSpec
     * Implementations must check and handle all fields defined in {@link ChronoField}.
     * If the field is supported and has an {@code int} range, then the value of
     * the field must be returned.
     * If unsupported, then an {@code UnsupportedTemporalTypeException} must be thrown.
     * <p>
     * If the field is not a {@code ChronoField}, then the result of this method
     * is obtained by invoking {@code TemporalField.getFrom(TemporalAccessor)}
     * passing {@code this} as the argument.
     * <p>
     * Implementations must ensure that no observable state is altered when this
     * read-only method is invoked.
     * <p>
     * The default implementation must behave equivalent to this code:
     * <pre>
     *  if (range(field).isIntValue()) {
     *    return range(field).checkValidIntValue(getLong(field), field);
     *  }
     *  throw new UnsupportedTemporalTypeException("Invalid field " + field + " + for get() method, use getLong() instead");
     * </pre>
     *
     * @param TemporalField $field the field to get, not null
     * @return int the value for the field, within the valid range of values
     * @throws DateTimeException if a value for the field cannot be obtained or
     *         the value is outside the range of valid values for the field
     * @throws UnsupportedTemporalTypeException if the field is not supported or
     *         the range of values exceeds an {@code int}
     * @throws ArithmeticException if numeric overflow occurs
     */
    public function get(TemporalField $field)
    {
        return TemporalAccessorDefaults::get($this, $field);
    }
}
