<?php

namespace Celest\Format\Builder;

use Celest\Chrono\AbstractChronology;
use Celest\Format\DateTimePrintContext;
use Celest\Format\TextStyle;
use Celest\Locale;
use Celest\Temporal\TemporalQueries;
use Celest\Format\DateTimeParseContext;
use Celest\Chrono\Chronology;

/**
 * Prints or parses a chronology.
 */
final class ChronoPrinterParser implements DateTimePrinterParser
{
    /** The text style to output, null means the ID.
     * @var TextStyle|null
     */
    private $textStyle;

    /**
     * ChronoPrinterParser constructor.
     * @param TextStyle|null $textStyle
     */
    public function __construct($textStyle)
    {
        // validated by caller
        $this->textStyle = $textStyle;
    }

    public function format(DateTimePrintContext $context, &$buf)
    {
        $chrono = $context->getValue(TemporalQueries::chronology());
        if ($chrono == null) {
            return false;
        }

        if ($this->textStyle == null) {
            $buf .= $chrono->getId();
        } else {
            $buf .= $this->getChronologyName($chrono, $context->getLocale());
        }
        return true;
    }

    public function parse(DateTimeParseContext $context, $text, $position)
    {
        // simple looping parser to find the chronology
        if ($position < 0 || $position > strlen($text)) {
            throw new IndexOutOfBoundsException();
        }

        $chronos = AbstractChronology::getAvailableChronologies();
        $bestMatch = null;
        $matchLen = -1;
        foreach ($chronos as $chrono) {
            if ($this->textStyle === null) {
                $name = $chrono->getId();
            } else {
                $name = $this->getChronologyName($chrono, $context->getLocale());
            }
            $nameLen = strlen($name);
            if ($nameLen > $matchLen && $context->subSequenceEquals($text, $position, $name, 0, $nameLen)) {
                $bestMatch = $chrono;
                $matchLen = $nameLen;
            }
        }
        if ($bestMatch === null) {
            return ~$position;
        }
        $context->setParsed($bestMatch);
        return $position + $matchLen;
    }

    /**
     * Returns the chronology name of the given chrono in the given locale
     * if available, or the chronology Id otherwise. The regular ResourceBundle
     * search path is used for looking up the chronology name.
     *
     * @param Chronology $chrono the chronology, not null
     * @param Locale $locale the locale, not null
     * @return string the chronology name of chrono in locale, or the id if no name is available
     * @throws NullPointerException if chrono or locale is null
     */
    private function getChronologyName(Chronology $chrono, Locale $locale)
    {
        $key = "calendarname." . $chrono->getCalendarType();
        $name = DateTimeTextProvider::getLocalizedResource($key, $locale);
        return $name != null ? $name : $chrono->getId();
    }

    function __toString()
    {
        return (string)$this->textStyle;
    }
}