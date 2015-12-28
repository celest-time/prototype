<?php

namespace Php\Time\Format\Builder;

use Php\Time\DateTimeException;
use Php\Time\Format\DateTimeParseContext;
use Php\Time\Helper\Math;
use Php\Time\IllegalArgumentException;
use Php\Time\Format\DateTimePrintContext;

/**
 * Prints and parses a numeric date-time field with optional padding.
 */
final class FractionPrinterParser implements DateTimePrinterParser
{
    /** @var TemporalField */
    private $field;
    /** @var int */
    private $minWidth;
    /** @var int */
    private $maxWidth;
    /** @var bool */
    private $decimalPoint;

    /**
     * Constructor.
     *
     * @param $field TemporalField the field to output, not null
     * @param $minWidth int the minimum width to output, from 0 to 9
     * @param $maxWidth int the maximum width to output, from 0 to 9
     * @param $decimalPoint bool whether to output the localized decimal point symbol
     */
    public function __construct(TemporalField $field, $minWidth, $maxWidth, $decimalPoint)
    {
        if ($field->range()->isFixed() == false) {
            throw new IllegalArgumentException("Field must have a fixed set of values: " . $field);
        }

        if ($minWidth < 0 || $minWidth > 9) {
            throw new IllegalArgumentException("Minimum width must be from 0 to 9 inclusive but was " . $minWidth);
        }
        if ($maxWidth < 1 || $maxWidth > 9) {
            throw new IllegalArgumentException("Maximum width must be from 1 to 9 inclusive but was " . $maxWidth);
        }
        if ($maxWidth < $minWidth) {
            throw new IllegalArgumentException("Maximum width must exceed or equal the minimum width but " .
                $maxWidth . " < " . $minWidth);
        }
        $this->field = $field;
        $this->minWidth = $minWidth;
        $this->maxWidth = $maxWidth;
        $this->decimalPoint = $decimalPoint;
    }

    public function format(DateTimePrintContext $context, &$buf)
    {
        $value = $context->getValue($this->field);
        if ($value == null) {
            return false;
        }

        $decimalStyle = $context->getDecimalStyle();
        $fraction = $this->convertToFraction($value);
        if ($fraction->scale() === 0) {  // scale is zero if value is zero
            if ($this->minWidth > 0) {
                if ($this->decimalPoint) {
                    $buf .= $decimalStyle->getDecimalSeparator();
                }
                for ($i = 0; $i < $this->minWidth;
                     $i++) {
                    $buf .= $decimalStyle->getZeroDigit();
                    }
            }
        } else {
            $outputScale = Math::min(Math::max($fraction->scale(), $this->minWidth), $this->maxWidth);
            $fraction = $fraction->setScale($outputScale, RoundingMode::FLOOR);
            $str = $fraction->toPlainString()->substring(2);
            $str = $decimalStyle->convertNumberToI18N($str);
            if ($this->decimalPoint) {
                $buf->append($decimalStyle->getDecimalSeparator());
            }
            $buf .= $str;
        }
        return true;
    }

    public function parse(DateTimeParseContext $context, $text, $position)
    {
        $effectiveMin = ($context->isStrict() ? $this->minWidth : 0);
        $effectiveMax = ($context->isStrict() ? $this->maxWidth : 9);
        $length = strlen($text);
        if ($position === $length) {
            // valid if whole field is optional, invalid if minimum width
            return ($effectiveMin > 0 ? ~$position : $position);
        }

        if ($this->decimalPoint) {
            if ($text[$position] != $context->getDecimalStyle()->getDecimalSeparator()) {
                // valid if whole field is optional, invalid if minimum width
                return ($effectiveMin > 0 ? ~$position : $position);
            }
            $position++;
        }
        $minEndPos = $position + $effectiveMin;
        if ($minEndPos > $length) {
            return ~$position;  // need at least min width digits
        }
        $maxEndPos = Math::min($position + $effectiveMax, $length);
        $total = 0;  // can use int because we are only parsing up to 9 digits
        $pos = $position;
        while ($pos < $maxEndPos) {
            $ch = $text[$pos++];
            $digit = $context->getDecimalStyle()->convertToDigit($ch);
            if ($digit < 0) {
                if ($pos < $minEndPos) {
                    return ~$position;  // need at least min width digits
                }
                $pos--;
                break;
            }
            $total = $total * 10 + $digit;
        }
        $fraction = (new BigDecimal($total))->movePointLeft($pos - $position);
        $value = $this->convertFromFraction($fraction);
        return $context->setParsedField($this->field, $value, $position, $pos);
    }

    /**
     * Converts a value for this field to a fraction between 0 and 1.
     * <p>
     * The fractional value is between 0 (inclusive) and 1 (exclusive).
     * It can only be returned if the {@link java.time.temporal.TemporalField#range() value range} is fixed.
     * The fraction is obtained by calculation from the field range using 9 decimal
     * places and a rounding mode of {@link RoundingMode#FLOOR FLOOR}.
     * The calculation is inaccurate if the values do not run continuously from smallest to largest.
     * <p>
     * For example, the second-of-minute value of 15 would be returned as 0.25,
     * assuming the standard definition of 60 seconds in a minute.
     *
     * @param $value int the value to convert, must be valid for this rule
     * @return BigDecimal TODO the value as a fraction within the range, from 0 to 1, not null
     * @throws DateTimeException if the value cannot be converted to a fraction
     */
    private function convertToFraction($value)
    {
        $range = $this->field->range();
        $range->checkValidValue($value, $this->field);
        $minBD = BigDecimal::valueOf($range->getMinimum());
        $rangeBD = BigDecimal::valueOf($range->getMaximum())->subtract($minBD)->add(BigDecimal::ONE);
        $valueBD = BigDecimal::valueOf($value)->subtract($minBD);
        $fraction = $valueBD->divide($rangeBD, 9, RoundingMode::FLOOR);
        // stripTrailingZeros bug
        return $fraction->compareTo(BigDecimal::ZERO) == 0 ? BigDecimal::ZERO : $fraction->stripTrailingZeros();
    }

    /**
     * Converts a fraction from 0 to 1 for this field to a value.
     * <p>
     * The fractional value must be between 0 (inclusive) and 1 (exclusive).
     * It can only be returned if the {@link java.time.temporal.TemporalField#range() value range} is fixed.
     * The value is obtained by calculation from the field range and a rounding
     * mode of {@link RoundingMode#FLOOR FLOOR}.
     * The calculation is inaccurate if the values do not run continuously from smallest to largest.
     * <p>
     * For example, the fractional second-of-minute of 0.25 would be converted to 15,
     * assuming the standard definition of 60 seconds in a minute.
     *
     * @param $fraction BigDecimal TODO the fraction to convert, not null
     * @return int the value of the field, valid for this rule
     * @throws DateTimeException if the value cannot be converted
     */
    private function convertFromFraction(BigDecimal $fraction)
    {
        $range = $this->field->range();
        $minBD = BigDecimal::valueOf($range->getMinimum());
        $rangeBD = BigDecimal::valueOf($range->getMaximum())->subtract($minBD)->add(BigDecimal::ONE);
        $valueBD = $fraction->multiply($rangeBD)->setScale(0, RoundingMode::FLOOR)->add($minBD);
        return $valueBD->longValueExact();
    }

    public function __toString()
    {
        $decimal = ($this->decimalPoint ? ",DecimalPoint" : "");
        return "Fraction(" . $this->field . "," . $this->minWidth . "," . $this->maxWidth . $decimal . ")";
    }
}