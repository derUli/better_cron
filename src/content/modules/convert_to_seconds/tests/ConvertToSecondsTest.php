<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use function UliCMS\Utils\ConvertToSeconds\convertToSeconds;

class ConvertToSecondsTest extends TestCase {

    public function testConvertReturnsSeconds() {
        $units = [
            "seconds" => 1,
            "minutes" => 60,
            "hours" => 3600,
            "days" => 86400,
            "weeks" => 604800,
            "months" => 2592000,
            "years" => 31536000,
            "decades" => 315360000
        ];

        foreach ($units as $unit => $expectedSeconds) {
            $this->assertEquals(
                    $expectedSeconds,
                    convertToSeconds(
                            1,
                            $unit
                    ),
                    "1 $unit is not $expectedSeconds seconds"
            );
        }
    }

    public function testConvertThrowsBadMethodCallException() {
        $this->expectException(BadMethodCallException::class);
        convertToSeconds(
                4,
                "lightyears"
        );
    }

}
