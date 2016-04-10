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
 * Copyright (c) 2009-2012, Stephen Colebourne & Michael Nascimento Santos
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
namespace Celest\Zone;

/**
 * Provider of time-zone rules to the system.
 * <p>
 * This class manages the configuration of time-zone rules.
 * The static methods provide the public API that can be used to manage the providers.
 * The abstract methods provide the SPI that allows rules to be provided.
 * <p>
 * ZoneRulesProvider may be installed in an instance of the Java Platform as
 * extension classes, that is, jar files placed into any of the usual extension
 * directories. Installed providers are loaded using the service-provider loading
 * facility defined by the {@link ServiceLoader} class. A ZoneRulesProvider
 * identifies itself with a provider configuration file named
 * {@code java.time.zone.ZoneRulesProvider} in the resource directory
 * {@code META-INF/services}. The file should contain a line that specifies the
 * fully qualified concrete zonerules-provider class name.
 * Providers may also be made available by adding them to the class path or by
 * registering themselves via {@link #registerProvider} method.
 * <p>
 * The Java virtual machine has a default provider that provides zone rules
 * for the time-zones defined by IANA Time Zone Database (TZDB). If the system
 * property {@code java.time.zone.DefaultZoneRulesProvider} is defined then
 * it is taken to be the fully-qualified name of a concrete ZoneRulesProvider
 * class to be loaded as the default provider, using the system class loader.
 * If this system property is not defined, a system-default provider will be
 * loaded to serve as the default provider.
 * <p>
 * Rules are looked up primarily by zone ID, as used by {@link ZoneId}.
 * Only zone region IDs may be used, zone offset IDs are not used here.
 * <p>
 * Time-zone rules are political, thus the data can change at any time.
 * Each provider will provide the latest rules for each zone ID, but they
 * may also provide the history of how the rules changed.
 *
 * @implSpec
 * This interface is a service provider that can be called by multiple threads.
 * Implementations must be immutable and thread-safe.
 * <p>
 * Providers must ensure that once a rule has been seen by the application, the
 * rule must continue to be available.
 * <p>
 *  Providers are encouraged to implement a meaningful {@code toString} method.
 * <p>
 * Many systems would like to update time-zone rules dynamically without stopping the JVM.
 * When examined in detail, this is a complex problem.
 * Providers may choose to handle dynamic updates, however the default provider does not.
 *
 * @since 1.8
 */
abstract class ZoneRulesProvider
{

    /**
     * The set of loaded providers.
     * @var ZoneRulesProvider[]
     */
    private static $PROVIDERS;
    /**
     * The lookup from zone ID to provider.
     * @var ZoneRulesProvider[] string->ZoneRulesProvider
     */
    private static $ZONES;

    public static function init()
    {
        self::registerProvider(new TZDBZoneRulesProvider());
    }

    //-------------------------------------------------------------------------
    /**
     * Gets the set of available zone IDs.
     * <p>
     * These IDs are the string form of a {@link ZoneId}.
     *
     * @return string[] a modifiable copy of the set of zone IDs, not null
     */
    public static function getAvailableZoneIds()
    {
        return array_keys(self::$ZONES);
    }

    /**
     * Gets the rules for the zone ID.
     * <p>
     * This returns the latest available rules for the zone ID.
     * <p>
     * This method relies on time-zone data provider files that are configured.
     * These are loaded using a {@code ServiceLoader}.
     * <p>
     * The caching flag is designed to allow provider implementations to
     * prevent the rules being cached in {@code ZoneId}.
     * Under normal circumstances, the caching of zone rules is highly desirable
     * as it will provide greater performance. However, there is a use case where
     * the caching would not be desirable, see {@link #provideRules}.
     *
     * @param string $zoneId the zone ID as defined by {@code ZoneId}, not null
     * @param bool $forCaching whether the rules are being queried for caching,
     * true if the returned rules will be cached by {@code ZoneId},
     * false if they will be returned to the user without being cached in {@code ZoneId}
     * @return ZoneRules the rules, null if {@code forCaching} is true and this
     * is a dynamic provider that wants to prevent caching in {@code ZoneId},
     * otherwise not null
     * @throws ZoneRulesException if rules cannot be obtained for the zone ID
     */
    public static function getRules($zoneId, $forCaching)
    {
        return self::getProvider($zoneId)->provideRules($zoneId, $forCaching);
    }

    /**
     * Gets the history of rules for the zone ID.
     * <p>
     * Time-zones are defined by governments and change frequently.
     * This method allows applications to find the history of changes to the
     * rules for a single zone ID. The map is keyed by a string, which is the
     * version string associated with the rules.
     * <p>
     * The exact meaning and format of the version is provider specific.
     * The version must follow lexicographical order, thus the returned map will
     * be order from the oldest known rules to the newest available rules.
     * The default 'TZDB' group uses version numbering consisting of the year
     * followed by a letter, such as '2009e' or '2012f'.
     * <p>
     * Implementations must provide a result for each valid zone ID, however
     * they do not have to provide a history of rules.
     * Thus the map will always contain one element, and will only contain more
     * than one element if historical rule information is available.
     *
     * @param string $zoneId the zone ID as defined by {@code ZoneId}, not null
     * @return ZoneRules[] a modifiable copy of the history of the rules for the ID, sorted
     *  from oldest to newest, not null
     * @throws ZoneRulesException if history cannot be obtained for the zone ID
     */
    public static function getVersions($zoneId)
    {
        return self::getProvider($zoneId)->provideVersions($zoneId);
    }

    /**
     * Gets the provider for the zone ID.
     *
     * @param string $zoneId the zone ID as defined by {@code ZoneId}, not null
     * @return ZoneRulesProvider he provider, not null
     * @throws ZoneRulesException if the zone ID is unknown
     */
    private static function getProvider($zoneId)
    {
        $provider = @self::$ZONES[$zoneId];
        if ($provider == null) {
            if (empty(self::$ZONES)) {
                throw new ZoneRulesException("No time-zone data files registered");
            }

            throw new ZoneRulesException("Unknown time-zone ID: " . $zoneId);
        }
        return $provider;
    }

//-------------------------------------------------------------------------
    /**
     * Registers a zone rules provider.
     * <p>
     * This adds a new provider to those currently available.
     * A provider supplies rules for one or more zone IDs.
     * A provider cannot be registered if it supplies a zone ID that has already been
     * registered. See the notes on time-zone IDs in {@link ZoneId}, especially
     * the section on using the concept of a "group" to make IDs unique.
     * <p>
     * To ensure the integrity of time-zones already created, there is no way
     * to deregister providers.
     *
     * @param ZoneRulesProvider $provider the provider to register, not null
     * @throws ZoneRulesException if a zone ID is already registered
     */
    public static function registerProvider(ZoneRulesProvider $provider)
    {
        self::registerProvider0($provider);
        self::$PROVIDERS[] = $provider;
    }

    /**
     * Registers the provider.
     *
     * @param ZoneRulesProvider $provider the provider to register, not null
     * @throws ZoneRulesException if unable to complete the registration
     */
    private static function registerProvider0(ZoneRulesProvider $provider)
    {
        foreach ($provider->provideZoneIds() as $zoneId) {
            $old = @self::$ZONES[$zoneId];
            self::$ZONES[$zoneId] = $provider;
            if ($old !== null) {
                throw new ZoneRulesException(
                    "Unable to register zone as one already registered with that ID: " . $zoneId .
                    ", currently loading from provider: " . $provider);
            }
        }
    }

    /**
     * Refreshes the rules from the underlying data provider.
     * <p>
     * This method allows an application to request that the providers check
     * for any updates to the provided rules.
     * After calling this method, the offset stored in any {@link ZonedDateTime}
     * may be invalid for the zone ID.
     * <p>
     * Dynamic update of rules is a complex problem and most applications
     * should not use this method or dynamic rules.
     * To achieve dynamic rules, a provider implementation will have to be written
     * as per the specification of this class.
     * In addition, instances of {@code ZoneRules} must not be cached in the
     * application as they will become stale. However, the boolean flag on
     * {@link #provideRules(String, boolean)} allows provider implementations
     * to control the caching of {@code ZoneId}, potentially ensuring that
     * all objects in the system see the new rules.
     * Note that there is likely to be a cost in performance of a dynamic rules
     * provider. Note also that no dynamic rules provider is in this specification.
     *
     * @return true if the rules were updated
     * @throws ZoneRulesException if an error occurs during the refresh
     */
    public static function refresh()
    {
        $changed = false;
        foreach (self::$PROVIDERS as $provider) {
            $changed |= $provider->provideRefresh();
        }

        return $changed;
    }

    /**
     * Constructor.
     */
    protected function __construct()
    {
    }

//-----------------------------------------------------------------------
    /**
     * SPI method to get the available zone IDs.
     * <p>
     * This obtains the IDs that this {@code ZoneRulesProvider} provides.
     * A provider should provide data for at least one zone ID.
     * <p>
     * The returned zone IDs remain available and valid for the lifetime of the application.
     * A dynamic provider may increase the set of IDs as more data becomes available.
     *
     * @return string[] the set of zone IDs being provided, not null
     * @throws ZoneRulesException if a problem occurs while providing the IDs
     */
    protected abstract function provideZoneIds();

    /**
     * SPI method to get the rules for the zone ID.
     * <p>
     * This loads the rules for the specified zone ID.
     * The provider implementation must validate that the zone ID is valid and
     * available, throwing a {@code ZoneRulesException} if it is not.
     * The result of the method in the valid case depends on the caching flag.
     * <p>
     * If the provider implementation is not dynamic, then the result of the
     * method must be the non-null set of rules selected by the ID.
     * <p>
     * If the provider implementation is dynamic, then the flag gives the option
     * of preventing the returned rules from being cached in {@link ZoneId}.
     * When the flag is true, the provider is permitted to return null, where
     * null will prevent the rules from being cached in {@code ZoneId}.
     * When the flag is false, the provider must return non-null rules.
     *
     * @param string $zoneId the zone ID as defined by {@code ZoneId}, not null
     * @param bool $forCaching whether the rules are being queried for caching,
     * true if the returned rules will be cached by {@code ZoneId},
     * false if they will be returned to the user without being cached in {@code ZoneId}
     * @return ZoneRules the rules, null if {@code forCaching} is true and this
     * is a dynamic provider that wants to prevent caching in {@code ZoneId},
     * otherwise not null
     * @throws ZoneRulesException if rules cannot be obtained for the zone ID
     */
    protected abstract function provideRules($zoneId, $forCaching);

    /**
     * SPI method to get the history of rules for the zone ID.
     * <p>
     * This returns a map of historical rules keyed by a version string.
     * The exact meaning and format of the version is provider specific.
     * The version must follow lexicographical order, thus the returned map will
     * be order from the oldest known rules to the newest available rules.
     * The default 'TZDB' group uses version numbering consisting of the year
     * followed by a letter, such as '2009e' or '2012f'.
     * <p>
     * Implementations must provide a result for each valid zone ID, however
     * they do not have to provide a history of rules.
     * Thus the map will contain at least one element, and will only contain
     * more than one element if historical rule information is available.
     * <p>
     * The returned versions remain available and valid for the lifetime of the application.
     * A dynamic provider may increase the set of versions as more data becomes available.
     *
     * @param string $zoneId the zone ID as defined by {@code ZoneId}, not null
     * @return ZoneRules[] a modifiable copy of the history of the rules for the ID, sorted
     *  from oldest to newest, not null
     * @throws ZoneRulesException if history cannot be obtained for the zone ID
     */
    protected abstract function provideVersions($zoneId);

    /**
     * SPI method to refresh the rules from the underlying data provider.
     * <p>
     * This method provides the opportunity for a provider to dynamically
     * recheck the underlying data provider to find the latest rules.
     * This could be used to load new rules without stopping the JVM.
     * Dynamic behavior is entirely optional and most providers do not support it.
     * <p>
     * This implementation returns false.
     *
     * @return bool true if the rules were updated
     * @throws ZoneRulesException if an error occurs during the refresh
     */
    protected function provideRefresh()
    {
        return false;
    }

}

ZoneRulesProvider::init();