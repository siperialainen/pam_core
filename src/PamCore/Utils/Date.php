<?php

namespace PamCore\Utils;

use PamCore\Client;

class Date
{
    const FORMAT_ID = 'id';
    //display date formats (1 October 2099)
    const FORMAT_DISPLAY_DATE = 'DISPLAY_DATE';
    const FORMAT_DISPLAY_TIME = 'DISPLAY_TIME';
    const FORMAT_DISPLAY_DATE_TIME = 'DISPLAY_DATE_TIME';

    //display date formats (1th October 2099)
    const FORMAT_LONG_DATE = 'LONG_DATE';
    const FORMAT_LONG_TIME = 'LONG_TIME';
    const FORMAT_LONG_DATE_TIME = 'LONG_DATE_TIME';

    //short date formats (14/11/2099)
    const FORMAT_SHORT_DATE = 'SHORT_DATE';
    const FORMAT_SHORT_DATE_MONTH = 'SHORT_MONTH';
    const FORMAT_SHORT_DATE_TIME = 'SHORT_DATE_TIME';
    const FORMAT_SHORT_TIME = 'SHORT_TIME';

    const FORMAT_REPORT_DATE = 'REPORT_DATE';
    const FORMAT_REPORT_TIME = 'REPORT_TIME';

    const FORMAT_TYPE_PHP = 'php';
    const FORMAT_TYPE_DATEPICKER = 'datepicker';
    const FORMAT_TYPE_MOMENTS = 'moments';

    const MYSQL_DATE_TIME_FORMAT = 'Y-m-d H:i:s';
    const MYSQL_DATE_FORMAT = 'Y-m-d';

    const UTC_TIME_ZONE = 'UTC';
    const SYDNEY_TIME_ZONE = 'Australia/Sydney';
    const TORONTO_TIME_ZONE = 'America/Toronto';

    public static function getDateFormats()
    {
        return [
            'YYYY-MM-DD' => [
                'id' => 'YYYY-MM-DD',
                'description' => 'International (YYYY-MM-DD)',
                'dateFormat' => [
                    self::FORMAT_LONG_DATE => 'Y F jS',
                    self::FORMAT_LONG_TIME => 'g:i A',
                    self::FORMAT_LONG_DATE_TIME => 'Y F jS g:i A',
                    self::FORMAT_DISPLAY_DATE => 'Y F j',
                    self::FORMAT_DISPLAY_TIME => 'g:i A',
                    self::FORMAT_DISPLAY_DATE_TIME => 'Y F j g:i A',
                    self::FORMAT_SHORT_DATE => "Y/m/d",
                    self::FORMAT_SHORT_DATE_MONTH => "Y/m",
                    self::FORMAT_SHORT_DATE_TIME => "Y/m/d g:i A",
                    self::FORMAT_SHORT_TIME => "g:i A",
                    self::FORMAT_REPORT_DATE => 'y.m.d',
                    self::FORMAT_REPORT_TIME => 'H:i:s (P \G\M\T)',
                ],
            ],
            'MM-DD-YYYY' => [
                'id' => 'MM-DD-YYYY',
                'description' => 'US (MM-DD-YYYY)',
                'dateFormat' => [
                    self::FORMAT_LONG_DATE => 'F jS Y',
                    self::FORMAT_LONG_TIME => 'g:i A',
                    self::FORMAT_LONG_DATE_TIME => 'F jS Y g:i A',
                    self::FORMAT_DISPLAY_DATE => 'F j Y',
                    self::FORMAT_DISPLAY_TIME => 'g:i A',
                    self::FORMAT_DISPLAY_DATE_TIME => 'F j Y g:i A',
                    self::FORMAT_SHORT_DATE => "m/d/Y",
                    self::FORMAT_SHORT_DATE_MONTH => "m/Y",
                    self::FORMAT_SHORT_DATE_TIME => "m/d/Y g:i A",
                    self::FORMAT_SHORT_TIME => "g:i A",
                    self::FORMAT_REPORT_DATE => 'm.d.y',
                    self::FORMAT_REPORT_TIME => 'H:i:s (P \G\M\T)',
                ],
            ],
            'DD-MM-YYYY' => [
                'id' => 'DD-MM-YYYY',
                'description' => 'EU/AU (DD-MM-YYYY)',
                'dateFormat' => [
                    self::FORMAT_LONG_DATE => 'jS F Y',
                    self::FORMAT_LONG_TIME => 'g:i A',
                    self::FORMAT_LONG_DATE_TIME => 'jS F Y g:i A',
                    self::FORMAT_DISPLAY_DATE => 'j F Y',
                    self::FORMAT_DISPLAY_TIME => 'g:i A',
                    self::FORMAT_DISPLAY_DATE_TIME => 'j F Y g:i A',
                    self::FORMAT_SHORT_DATE => "d/m/Y",
                    self::FORMAT_SHORT_DATE_MONTH => "m/Y",
                    self::FORMAT_SHORT_DATE_TIME => "d/m/Y g:i A",
                    self::FORMAT_SHORT_TIME => "g:i A",
                    self::FORMAT_REPORT_DATE => 'd.m.y',
                    self::FORMAT_REPORT_TIME => 'H:i:s (P \G\M\T)',
                ],
            ],
        ];
    }

    public static function getDefaultDateFormat()
    {
        return static::getDateFormats()['DD-MM-YYYY'];
    }

    /**
     * Returns datetime converted from UTC timezone to client timezone
     * @param string $utcDateTime
     * @return string
     */
    public static function getClientDateTime($utcDateTime)
    {
        return static::convertDateTime($utcDateTime, static::UTC_TIME_ZONE, Client::get()->getTimeZone());
    }

    /**
     * Returns DateTime object converted from UTC timezone to client timezone
     * @param \DateTime $utcDateTimeObject
     * @return bool|\DateTime
     */
    public static function getClientDateTimeObject($utcDateTimeObject)
    {
        return static::convertDateTimeObject($utcDateTimeObject, Client::get()->getTimeZone());
    }

    /**
     * Returns date converted from UTC timezone to client timezone
     * @param string $utcDate
     * @return string
     */
    public static function getClientDate($utcDate)
    {
        return static::convertDate($utcDate, static::UTC_TIME_ZONE, Client::get()->getTimeZone());
    }

    /**
     * Converts datetime string from one timezone to another
     * @param string $dateTimeString
     * @param string|\DateTimeZone $srcTimeZone
     * @param string|\DateTimeZone $dstTimeZone
     * @return string
     */
    public static function convertDateTime($dateTimeString, $srcTimeZone, $dstTimeZone)
    {
        return static::convert($dateTimeString, $srcTimeZone, $dstTimeZone, self::MYSQL_DATE_TIME_FORMAT);
    }

    /**
     * Converts date string from one timezone to another
     * @param string $dateString
     * @param string|\DateTimeZone $srcTimeZone
     * @param string|\DateTimeZone $dstTimeZone
     * @return string
     */
    public static function convertDate($dateString, $srcTimeZone, $dstTimeZone)
    {
        return static::convert($dateString, $srcTimeZone, $dstTimeZone, static::MYSQL_DATE_FORMAT);
    }

    /**
     * @param string $dateTimeString
     * @param string|\DateTimeZone $srcTimeZone
     * @param string|\DateTimeZone $dstTimeZone
     * @param string $format
     * @return bool|string
     */
    private static function convert($dateTimeString, $srcTimeZone, $dstTimeZone, $format)
    {
        if (!($srcTimeZone instanceof \DateTimeZone)) {
            $srcTimeZone = new \DateTimeZone($srcTimeZone);
        }
        $dateTime = \DateTime::createFromFormat(
            $format,
            $dateTimeString,
            $srcTimeZone
        );
        $dateTime = static::convertDateTimeObject($dateTime, $dstTimeZone);
        if ($dateTime instanceof \DateTime) {
            return $dateTime->format(self::MYSQL_DATE_TIME_FORMAT);
        }
        return false;
    }

    /**
     * Converts datetime object tp specified timezone
     * @param \DateTime $dateTimeObject
     * @param string|\DateTimeZone $dstTimeZone
     * @return bool|\DateTime
     */
    public static function convertDateTimeObject($dateTimeObject, $dstTimeZone)
    {
        if (!($dstTimeZone instanceof \DateTimeZone)) {
            $dstTimeZone = new \DateTimeZone($dstTimeZone);
        }
        if ($dateTimeObject instanceof \DateTime) {
            $dateTimeObject->setTimezone($dstTimeZone);
            return $dateTimeObject;
        }
        return false;
    }

    /**
     * Creates new DateTime object in UTC time zone based on passed dateTime string
     * If null is passed as $dateTimeString then the DateTime object with current date and time in UTC time zone is returned
     * @param string|null $dateTimeString
     * @return bool|\DateTime
     */
    public static function getUtcDateTimeObject($dateTimeString = null)
    {
        $utcTimeZone = new \DateTimeZone(static::UTC_TIME_ZONE);
        if (is_null($dateTimeString)) {
            $dateTime = new \DateTime('now', $utcTimeZone);
        } else {
            $dateTime = \DateTime::createFromFormat(
                self::MYSQL_DATE_TIME_FORMAT,
                $dateTimeString,
                $utcTimeZone
            );
        }
        return $dateTime;
    }

    /**
     * Returns date string in format suitable for displaying, e.g. "25 February 2017"
     * @param \DateTime $dateTimeObject
     * @return string
     */
    public static function getDisplayDate($dateTimeObject)
    {
        $clientDateTimeObject = static::getClientDateTimeObject($dateTimeObject);
        return $clientDateTimeObject ? $clientDateTimeObject->format(Client::get()->getDateFormat(static::FORMAT_DISPLAY_DATE)) : '';
    }

    /**
     * Returns time string in format suitable for displaying, e.g. "2:15 PM"
     * @param \DateTime $dateTimeObject
     * @return string
     */
    public static function getDisplayTime($dateTimeObject)
    {
        $clientDateTimeObject = static::getClientDateTimeObject($dateTimeObject);
        return $clientDateTimeObject ? $clientDateTimeObject->format(Client::get()->getDateFormat(static::FORMAT_DISPLAY_TIME)) : '';
    }

    public static function getDateFormat($format, $type = self::FORMAT_TYPE_PHP, $dateFormats = [])
    {
        if (isset($dateFormats[$format])) {
            $formatTemplate = $dateFormats[$format];
        } else {
            $formatTemplate = static::getDateFormats()['YYYY-MM-DD']['dateFormat'][$format];
        }

        switch ($type) {
            case static::FORMAT_TYPE_PHP:
                $formatString = $formatTemplate;
                break;
            case static::FORMAT_TYPE_DATEPICKER:
                $formatString = static::dateFormatPhpTo($formatTemplate, [
                    // Day
                    'd' => 'dd',
                    'D' => 'D',
                    'j' => 'd',
                    'l' => 'DD',
                    'N' => '',
                    'S' => '',
                    'w' => '',
                    'z' => 'o',
                    // Week
                    'W' => '',
                    // Month
                    'F' => 'MM',
                    'm' => 'mm',
                    'M' => 'M',
                    'n' => 'm',
                    't' => '',
                    // Year
                    'L' => '',
                    'o' => '',
                    'Y' => 'yyyy',
                    'y' => 'yy',
                    // Time
                    'a' => '',
                    'A' => '',
                    'B' => '',
                    'g' => '',
                    'G' => '',
                    'h' => '',
                    'H' => '',
                    'i' => '',
                    's' => '',
                    'u' => ''
                ]);
                break;
            case static::FORMAT_TYPE_MOMENTS:
                $formatString = static::dateFormatPhpTo($formatTemplate, [
                    'd' => 'DD',
                    'D' => 'ddd',
                    'j' => 'D',
                    'l' => 'dddd',
                    'N' => 'E',
                    'S' => 'o',
                    'w' => 'e',
                    'z' => 'DDD',
                    'W' => 'W',
                    'F' => 'MMMM',
                    'm' => 'MM',
                    'M' => 'MMM',
                    'n' => 'M',
                    't' => '', // No equivalent.
                    'L' => '', // No equivalent.
                    'o' => 'YYYY',
                    'Y' => 'YYYY',
                    'y' => 'YY',
                    'a' => 'a',
                    'A' => 'A',
                    'B' => '', // No equivalent.
                    'g' => 'h',
                    'G' => 'H',
                    'h' => 'hh',
                    'H' => 'HH',
                    'i' => 'mm',
                    's' => 'ss',
                    'u' => 'SSS',
                    'e' => 'zz', // Deprecated since version 1.6.0 of Moment.js.
                    'I' => '', // No equivalent.
                    'O' => '', // No equivalent.
                    'P' => '', // No equivalent.
                    'T' => '', // No equivalent.
                    'Z' => '', // No equivalent.
                    'c' => '', // No equivalent.
                    'r' => '', // No equivalent.
                    'U' => 'X',
                ]);
                break;
            default:
                throw new \Exception("Unsupported date format type: {$type}");
        }

        return $formatString;
    }

    public static function dateFormatPhpTo($php_format, $SYMBOLS_MATCHING)
    {
        $result_format = "";
        $escaping = false;
        for ($i = 0; $i < strlen($php_format); $i++) {
            $char = $php_format[$i];
            if ($char === '\\') // PHP date format escaping character
            {
                $i++;
                if ($escaping) {
                    $result_format .= $php_format[$i];
                } else {
                    $result_format .= '\'' . $php_format[$i];
                }
                $escaping = true;
            } else {
                if ($escaping) {
                    $result_format .= "'";
                    $escaping = false;
                }
                if (isset($SYMBOLS_MATCHING[$char])) {
                    $result_format .= $SYMBOLS_MATCHING[$char];
                } else {
                    $result_format .= $char;
                }
            }
        }
        return $result_format;
    }

    /**
     * Format string date to client date format
     * @param string $date
     * @param string $format format id see Date::FORMAT_*
     * @return false|string
     */
    public static function formatDate($date, $format)
    {
        return date(Client::get()->getDateFormat($format), strtotime($date));
    }
}