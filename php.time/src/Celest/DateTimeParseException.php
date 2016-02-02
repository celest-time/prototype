<?php

namespace Celest;


class DateTimeParseException extends DateTimeException
{
    /**
     * The text that was being parsed.
     */
    private $parsedString;
    /**
     * The error index in the text.
     */
    private $errorIndex;

    /**
     * Constructs a new exception with the specified message and cause.
     *
     * @param string $message the message to use for this exception, may be null
     * @param string $parsedData the parsed text, should not be null
     * @param int $errorIndex the index in the parsed string that was invalid, should be a valid index
     * @param $cause \Exception the cause exception, may be null
     */
    public function __construct($message, $parsedData, $errorIndex, $cause = null)
    {
        parent::__construct($message, $cause);
        $this->parsedString = $parsedData;
        $this->errorIndex = $errorIndex;
    }

    /**
     * Returns the string that was being parsed.
     *
     * @return string the string that was being parsed, should not be null.
     */
    public function getParsedString()
    {
        return $this->parsedString;
    }

    /**
     * Returns the index where the error was found.
     *
     * @return int the index in the parsed string that was invalid, should be a valid index
     */
    public function getErrorIndex()
    {
        return $this->errorIndex;
    }
}