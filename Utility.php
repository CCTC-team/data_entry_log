<?php

namespace CCTC\DataEntryLogModule;

use DateTime;
use DateTimeRC;

require_once APP_PATH_DOCROOT . "/Classes/DateTimeRC.php";


class Utility {

    public static function getBaseUrl() : string
    {
        global $module;

        //returns something like https://localhost:8443/redcap_v13.8.1/ExternalModules/?prefix=log_helper&page=somepage&pid=22
        $url = $module->getUrl("somepage.php");

        //use regex to pull everything prior to the ExternalModules part
        $basePat = "/https:\/\/.*(?=\/ExternalModules)/";
        preg_match($basePat, $url, $urlMatches);

        return $urlMatches[0];
    }

    public static function getREDCapUrlPart() : string
    {
        $fullUrl = self::getBaseUrl();
        preg_match("/\/redcap_v\d+.\d+.\d+/", $fullUrl, $matches);
        return $matches[0];
    }

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

    //default min date
    public static function DefaultMinDate() : DateTime
    {
        return date_create(date("2022-01-01 00:00:00"));
    }

    //this could be based on first log entry but doesn't really matter for now
    public static function DefaultMinDateInUserFormatAsString() : string
    {
        return self::FullDateTimeInUserFormatAsString(self::DefaultMinDate());
    }

    //converts a given date string to the given format or default format if no format given
    public static function DateStringAsDateTime(?string $date, ?string $format = null) : ?DateTime
    {
        if($date == null) return null;

        $formatToUse = $format == null ? self::UserDateTimeFormatNoSeconds(): $format;
        return DateTime::createFromFormat($formatToUse, $date);
    }

    // returns a nullable string date as a format compatible with the timestamp function
    // returns null if null given
    public static function DateStringToDbFormat(?string $date) : ?string
    {
        if($date == null) return null;

        $dateTime = DateTime::createFromFormat(self::UserDateTimeFormatNoSeconds(), $date);
        return $dateTime->format('YmdHis');
    }
}