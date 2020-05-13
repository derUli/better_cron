<?php

namespace UliCMS\Utils\ConvertToSeconds;

use BadMethodCallException;

function convertToSeconds(int $timespan, string $unit): int {
    switch ($unit) {
        case TimeUnit::SECONDS:
            return $timespan;
        case TimeUnit::MINUTES:
            return $timespan * 60;
        case TimeUnit::HOURS:
            return $timespan * 60 * 60;
        case TimeUnit::DAYS:
            return $timespan * 60 * 60 * 24;
        case TimeUnit::WEEKS:
            return $timespan * 60 * 60 * 24 * 7;
        case TimeUnit::MONTHS:
            return $timespan * 60 * 60 * 24 * 30;
        case TimeUnit::YEARS:
            return $timespan * 60 * 60 * 24 * 365;
        case TimeUnit::DECADES:
            return $timespan * 60 * 60 * 24 * 365 * 10;
        default:
            throw new BadMethodCallException("$unit is an unknown time unit.");
    }
}
