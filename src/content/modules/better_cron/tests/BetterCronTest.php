<?php

use PHPUnit\Framework\TestCase;

class BetterCronTest extends TestCase {

    public function tearDown() {
        Database::deleteFrom("cronjobs", "name like 'phpunit/%'");
    }

    public function testSecondsTestWithCallable() {
        $testIdentifier = "phpunit/" . uniqid();

        ob_start();
        $this->callJobsSeconds($testIdentifier, function() {
            echo "foo1";
        });
        $this->assertEquals("foo1", ob_get_clean());

        $allJobs1 = BetterCron::getAllCronjobs();
        $this->assertGreaterThan(time() - 10, $allJobs1[$testIdentifier]);

        sleep(2);

        ob_start();
        $this->callJobsSeconds($testIdentifier, function() {
            echo "foo2";
        });

        $this->assertEmpty(ob_get_clean());

        $allJobs2 = BetterCron::getAllCronjobs();
        $this->assertEquals($allJobs2[$testIdentifier], $allJobs2[$testIdentifier]);

        sleep(6);
        ob_start();
        $this->callJobsSeconds($testIdentifier, function() {
            echo "foo3";
        });
        $this->assertEquals("foo3", ob_get_clean());

        $allJobs3 = BetterCron::getAllCronjobs();
        $this->assertGreaterThan($allJobs2[$testIdentifier], $allJobs3[$testIdentifier]);
    }

    public function testMinutes() {
        $this->doTest("minutes");
    }

    public function calculateSeconds(int $interval, string $unit): int {
        switch ($unit) {
            case 'minutes':
                return $interval * 60;
            case 'hours':
                return $interval * 60 * 60;
            case 'days':
                return $interval * 60 * 60 * 24;
        }
        return $interval;
    }

    public function callBetterCron(
            string $name,
            string $unit,
            closure $callable
    ): void {
        $methodName = "BetterCron::{$unit}";
        call_user_func($methodName, $name, 2, $callable);
    }

    protected function doTest($unit) {
        $testIdentifier = "phpunit/" . uniqid();

        ob_start();
        $this->callBetterCron($testIdentifier, $unit, function() {
            echo "foo1";
        });

        $this->assertEquals("foo1", ob_get_clean());

        $allJobs1 = BetterCron::getAllCronjobs();
        $this->assertGreaterThan(time() - 10, $allJobs1[$testIdentifier]);

        sleep($this->calculateSeconds(1, "minutes"));

        ob_start();
        $this->callBetterCron($testIdentifier, $unit, function() {
            echo "foo2";
        });

        $this->assertEmpty(ob_get_clean());

        $allJobs2 = BetterCron::getAllCronjobs();
        $this->assertEquals($allJobs2[$testIdentifier], $allJobs2[$testIdentifier]);

        sleep($this->calculateSeconds(1, "minutes"));

        ob_start();
        $this->callBetterCron($testIdentifier, $unit, function() {
            echo "foo3";
        });
        $this->assertEquals("foo3", ob_get_clean());

        $allJobs3 = BetterCron::getAllCronjobs();
        $this->assertGreaterThan($allJobs2[$testIdentifier], $allJobs3[$testIdentifier]);
    }

    protected function callJobsSeconds($name, $callback) {
        BetterCron::seconds($name, 6, $callback);
    }

}
