<?php

namespace CCTC\DataEntryLogModule;

use DateTime;
use DateTimeRC;

require_once APP_PATH_DOCROOT . "/Classes/DateTimeRC.php";


class Utility {

    // Default number of years to look back for the minimum date filter
    const DEFAULT_MIN_DATE_YEARS_AGO = 5;

    public static function MakeFormLink($baseUrl, $projectId, $recordId, $eventId, $formName, $fldName, $instance, $val): string
    {
        if($instance !== null)
        {
            $instance = "&instance=" . $instance;
        }

        return "<a href='{$baseUrl}/DataEntry/index.php?pid={$projectId}&id={$recordId}&event_id={$eventId}&page={$formName}{$instance}&fldfocus={$fldName}#{$fldName}-tr'>{$val}</a>";
    }

    // groups an array
    public static function groupBy($array, $function): array
    {
        /*  usage:
         *  $dcGrouped = groupBy($dataChanges, function ($item) {
                return $item->getKey();
            });
         */

        $dictionary = [];
        if ($array) {
            foreach ($array as $item) {
                $dictionary[$function($item)][] = $item;
            }
        }
        return $dictionary;
    }

    //users preferred format
    static function UserDateFormat() : string
    {
        return DateTimeRC::get_user_format_php();
    }

    //users preferred format as date and time
    static function UserDateTimeFormat() : string
    {
        return self::UserDateFormat() . ' H:i:s';
    }

    //users preferred format as date and time (hours and minutes only)
    static function UserDateTimeFormatNoSeconds() : string
    {
        return self::UserDateFormat() . ' H:i';
    }

    //full date time string in users preferred format
    public static function FullDateTimeInUserFormatAsString(DateTime $d) : string
    {
        return $d->format(self::UserDateTimeFormat());
    }

    public static function DateTimeNoSecondsInUserFormatAsString(DateTime $d) : string
    {
        return $d->format(self::UserDateTimeFormatNoSeconds());
    }

    //now
    public static function Now() : DateTime
    {
        return date_create(date('Y-m-d H:i:s'));
    }

    //full format now for date and time in user format
    public static function NowInUserFormatAsString() : string
    {
        return self::Now()->format(self::UserDateTimeFormat());
    }

    //now with no seconds for date and time in user format
    public static function NowInUserFormatAsStringNoSeconds() : string
    {
        return self::Now()->format(self::UserDateTimeFormatNoSeconds());
    }

    //returns the date time now adjusted with the given modifier
    public static function NowAdjusted(?string $modifier) : string
    {
        if($modifier == null) {
            return self::Now()->format(self::UserDateTimeFormatNoSeconds());
        }

        try {
            return self::DateTimeNoSecondsInUserFormatAsString(self::Now()->modify($modifier));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Returns the default minimum date for date range filters.
     * Uses a relative date (years ago from today) to avoid becoming outdated.
     */
    public static function DefaultMinDate() : DateTime
    {
        $yearsAgo = self::DEFAULT_MIN_DATE_YEARS_AGO;
        return self::Now()->modify("-{$yearsAgo} years")->setTime(0, 0, 0);
    }

    /**
     * Returns the default minimum date formatted in the user's preferred format.
     */
    public static function DefaultMinDateInUserFormatAsString() : string
    {
        return self::FullDateTimeInUserFormatAsString(self::DefaultMinDate());
    }

    //converts a given date string to the given format or default format if no format given
    //returns null if date is null, empty, or if parsing fails
    public static function DateStringAsDateTime(?string $date, ?string $format = null) : ?DateTime
    {
        if($date === null || $date === "") return null;

        $formatToUse = $format === null ? self::UserDateTimeFormatNoSeconds(): $format;
        $dateTime = DateTime::createFromFormat($formatToUse, $date);
        return $dateTime === false ? null : $dateTime;
    }

    // returns a nullable string date as a format compatible with the timestamp function
    // returns null if null or empty given, or if date parsing fails
    public static function DateStringToDbFormat(?string $date) : ?string
    {
        if($date === null || $date === "") return null;

        $dateTime = DateTime::createFromFormat(self::UserDateTimeFormatNoSeconds(), $date);
        if($dateTime === false) {
            return null;
        }
        return $dateTime->format('YmdHis');
    }
}