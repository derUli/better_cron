<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use function UliCMS\HTML\stringContainsHtml;
use function UliCMS\Utils\ConvertToSeconds\convertToSeconds;
use UliCMS\Utils\ConvertToSeconds\TimeUnit;

class BetterCronTest extends TestCase {
    public function setUp() {
        $migrator = new DBMigrator(
                "package/better_cron",
                ModuleHelper::buildRessourcePath("better_cron", "sql/up")
        );
        $migrator->migrate();
    }

    public function tearDown() {
        $migrator = new DBMigrator(
                "package/better_cron",
                ModuleHelper::buildRessourcePath("better_cron", "sql/down")
        );
        $migrator->rollback();
    }

    public function testTableExists() {
        $this->assertTrue(Database::tableExists("cronjobs"));
    }

    public function testAfterHTML() {
        $this->assertFalse(defined("CRONJOBS_REGISTERED"));

        $controller = new BetterCron();
        $controller->afterHtml();

        $this->assertTrue(defined("CRONJOBS_REGISTERED"));
    }

    public function testSeconds() {
        $this->doTest(TimeUnit::SECONDS);
    }

    public function testMinutes() {
        $this->doTest(TimeUnit::MINUTES);
    }

    public function testHours() {
        $this->doTest(TimeUnit::HOURS);
    }

    public function testDays() {
        $this->doTest(TimeUnit::DAYS);
    }

    public function testWeeks() {        
        $this->doTest(TimeUnit::WEEKS);
    }

    public function testMonths() {       
        $this->doTest(TimeUnit::MONTHS);
    }

    public function testYears() {       
        $this->doTest(TimeUnit::YEARS);
    }

    public function testDecades() {
        $this->doTest(TimeUnit::DECADES);
    }

    public function testWithControllerCallback() {
        $testIdentifier = "phpunit/" . uniqid();

        ob_start();
        $this->callBetterCron(
                $testIdentifier,
                TimeUnit::SECONDS,
                "BetterCron::testCallback"
        );
        $this->assertEquals("foo", ob_get_clean());
    }

    public function testWithGlobalMethod() {
        $testIdentifier = "phpunit/" . uniqid();
        ob_start();
        $this->callBetterCron(
                $testIdentifier,
                TimeUnit::SECONDS,
                "year"
        );

        $this->assertGreaterThanOrEqual(2020, intval(ob_get_clean()));
    }

    public function testWithNonExistingControllerMethodThrowsException() {
        $testIdentifier = "phpunit/" . uniqid();
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage(
                "Callback method NoController::noMethod for the job $testIdentifier doesn't exist"
        );

        $this->callBetterCron(
                $testIdentifier,
                TimeUnit::SECONDS,
                "NoController::noMethod"
        );
    }

    public function testWithNonExistingMethodThrowsException() {
        $testIdentifier = "phpunit/" . uniqid();

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage(
                "Callback method no_method for the job $testIdentifier doesn't exist"
        );

        $this->callBetterCron(
                $testIdentifier,
                TimeUnit::SECONDS,
                "no_method"
        );
    }

    public function testWithNotCallableArgumentThrowsException() {
        $testIdentifier = "phpunit/" . uniqid();

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage(
                "Callback of job $testIdentifier is not callable"
        );
        $this->callBetterCron(
                $testIdentifier,
                TimeUnit::SECONDS,
                123
        );
    }

    protected function updateCurrentTime(int $time) {
        BetterCron::$currentTime = $time;
    }

    protected function addToCurrentTime(int $time) {
        BetterCron::$currentTime += $time;
    }

    protected function doTest($unit) {
        $testIdentifier = "phpunit/" . uniqid();

        $this->updateCurrentTime(0);

        BetterCron::updateLastRun($testIdentifier);

        $this->addToCurrentTime(
                convertToSeconds(4, "decades")
        );

        ob_start();
        $this->callBetterCron($testIdentifier, $unit, function() {
            echo "foo1";
        });

        $this->assertEquals("foo1", ob_get_clean());

        $allJobs1 = BetterCron::getAllCronjobs();
        $this->assertEquals(BetterCron::$currentTime, $allJobs1[$testIdentifier]);

        $this->addToCurrentTime(
                convertToSeconds($unit == TimeUnit::SECONDS ? 2 : 1, $unit)
        );

        ob_start();
        $this->callBetterCron($testIdentifier, $unit, function() {
            echo "foo2";
        });

        $this->assertEmpty(ob_get_clean());

        $allJobs2 = BetterCron::getAllCronjobs();
        $this->assertEquals($allJobs2[$testIdentifier], $allJobs2[$testIdentifier]);

        $this->addToCurrentTime(
                convertToSeconds(
                        $unit == TimeUnit::SECONDS ? 5 : 1,
                        $unit
                )
        );

        ob_start();
        $this->callBetterCron($testIdentifier, $unit, function() {
            echo "foo3";
        });
        $this->assertEquals("foo3", ob_get_clean());

        $allJobs3 = BetterCron::getAllCronjobs();
        $this->assertGreaterThan(
                $allJobs2[$testIdentifier],
                $allJobs3[$testIdentifier]
        );
    }

    public function callBetterCron(
            string $name,
            string $unit,
            $callable,
            $timespan = 2
    ): void {
        $methodName = "BetterCron::{$unit}";
        call_user_func(
                $methodName,
                $name,
                $unit == TimeUnit::SECONDS ? 5 : 2,
                $callable
        );
    }

    public function testGetSettingsHeadline() {
        $controller = new BetterCron();
        $this->assertEqualsIgnoringCase(
                "cronjobs", $controller->getSettingsHeadline()
        );
    }

    public function testGetSettings() {
        BetterCron::$currentTime = convertToSeconds(4, "decades");
        BetterCron::seconds("phpunit/foo", 1, function() {

        });
        BetterCron::seconds("phpunit/bar", 1, function() {

        });
        $controller = new BetterCron();
        $settingsPage = $controller->settings();
        $this->assertTrue(stringContainsHtml($settingsPage));
        $this->assertStringContainsString("22.12.2009", $settingsPage);
        $this->assertStringContainsString("phpunit/foo", $settingsPage);
        $this->assertStringContainsString("phpunit/bar", $settingsPage);
    }

    public function testUninstall() {
        $this->assertTrue(Database::tableExists("cronjobs"));

        $controller = new BetterCron();
        $controller->uninstall();

        $this->assertFalse(Database::tableExists("cronjobs"));
    }

}
