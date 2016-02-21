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
 * Copyright (c) 2008-2012, Stephen Colebourne & Michael Nascimento Santos
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

use Celest\DateTimeException;
use Celest\LocalDate;
use Celest\Temporal\ChronoField;
use Celest\ZoneOffset;

class TCKSignStyleTest extends \PHPUnit_Framework_TestCase
{

    //-----------------------------------------------------------------------
    // valueOf()
    //-----------------------------------------------------------------------
    public function test_valueOf()
    {
        foreach (SignStyle::values() as $style) {
            $this->assertEquals(SignStyle::valueOf($style->name()), $style);
        }
    }

    public function data_signStyle()
    {
        return
            [
                [
                    LocalDate::of(0, 10, 2), SignStyle::ALWAYS(), null, "+00"
                ],
                [
                    LocalDate::of(2001, 10, 2), SignStyle::ALWAYS(), null, "+2001"],
                [
                    LocalDate::of(-2001, 10, 2), SignStyle::ALWAYS(), null, "-2001"],

                [
                    LocalDate::of(2001, 10, 2), SignStyle::NORMAL(), null, "2001"],
                [
                    LocalDate::of(-2001, 10, 2), SignStyle::NORMAL(), null, "-2001"],

                [
                    LocalDate::of(2001, 10, 2), SignStyle::NEVER(), null, "2001"],
                [
                    LocalDate::of(-2001, 10, 2), SignStyle::NEVER(), null, "2001"],

                [
                    LocalDate::of(2001, 10, 2), SignStyle::NOT_NEGATIVE(), null, "2001"],
                [
                    LocalDate::of(-2001, 10, 2), SignStyle::NOT_NEGATIVE(), DateTimeException::class, ""],

                [
                    LocalDate::of(0, 10, 2), SignStyle::EXCEEDS_PAD(), null, "00"],
                [
                    LocalDate::of(1, 10, 2), SignStyle::EXCEEDS_PAD(), null, "01"],
                [
                    LocalDate::of(-1, 10, 2), SignStyle::EXCEEDS_PAD(), null, "-01"],

                [
                    LocalDate::of(20001, 10, 2), SignStyle::ALWAYS(), DateTimeException::class, ""],
                [
                    LocalDate::of(20001, 10, 2), SignStyle::NORMAL(), DateTimeException::class, ""],
                [
                    LocalDate::of(20001, 10, 2), SignStyle::NEVER(), DateTimeException::class, ""],
                [
                    LocalDate::of(20001, 10, 2), SignStyle::EXCEEDS_PAD(), DateTimeException::class, ""],
                [
                    LocalDate::of(20001, 10, 2), SignStyle::NOT_NEGATIVE(), DateTimeException::class, ""],
            ];
    }

    /**
     * @dataProvider data_signStyle
     */
    public
    function test_signStyle(LocalDate $localDate, SignStyle $style, $expectedEx, $expectedStr)
    {
        $builder = new DateTimeFormatterBuilder();
        $formatter = $builder->appendValue3(ChronoField::YEAR(), 2, 4, $style)
            ->toFormatter();
        $formatter = $formatter->withZone(ZoneOffset::UTC());
        if ($expectedEx === null) {
            $output = $formatter->format($localDate);
            $this->assertEquals($output, $expectedStr);
        } else {
            try {
                $formatter->format($localDate);
                $this->fail();
            } catch (\Exception $ex) {
                $this->assertInstanceOf($expectedEx, $ex);
            }
        }
    }

}
