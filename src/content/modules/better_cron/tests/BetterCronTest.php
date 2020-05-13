<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use function UliCMS\HTML\stringContainsHtml;
use function UliCMS\Utils\ConvertToSeconds\convertToSeconds;
use UliCMS\Utils\ConvertToSeconds\TimeUnit;

class BetterCronTest extends TestCase {

    protected $level = 1;

    public function __construct() {
        parent::__construct();
        // With this environment variable you can configure which tests are run
        // the value can be between 1 and 8
        // level 1 means only the seconds() function is tested,
        // level 3 would test seconds(), minutes() and hours()
        // level 8 means that all functions including decades() is tested
        // I think nobody will ever run this test suite at test level 8
        // or even use the decades() method
        if (getenv("BETTER_CRON_TEST_LEVEL")) {
            $this->level = intval(getenv("BETTER_CRON_TEST_LEVEL"));
        }
    }

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
        if ($this->level < 2) {
            $this->markTestSkipped('Skipped because of BETTER_CRON_TEST_LEVEL');
            return;
        }
        $this->doTest(TimeUnit::MINUTES);
    }

    public function testHours() {
        if ($this->level < 3) {
            $this->markTestSkipped('Skipped because of BETTER_CRON_TEST_LEVEL');
            return;
        }

        $this->doTest(TimeUnit::HOURS);
    }

    public function testDays() {
        if ($this->level < 4) {
            $this->markTestSkipped('Skipped because of BETTER_CRON_TEST_LEVEL');
            return;
        }
        $this->doTest(TimeUnit::DAYS);
    }

    public function testWeeks() {
        if ($this->level < 5) {
            $this->markTestSkipped('Skipped because of BETTER_CRON_TEST_LEVEL');
            return;
        }
        $this->doTest(TimeUnit::WEEKS);
    }

    public function testMonths() {
        if ($this->level < 6) {
            $this->markTestSkipped('Skipped because of BETTER_CRON_TEST_LEVEL');
            return;
        }
        $this->doTest(TimeUnit::MONTHS);
    }

    public function testYears() {
        if ($this->level < 7) {
            $this->markTestSkipped('Skipped because of BETTER_CRON_TEST_LEVEL');
            return;
        }
        $this->doTest(TimeUnit::YEARS);
    }

    public function testDecades() {
        if ($this->level < 8) {
            $this->markTestSkipped('Skipped because of BETTER_CRON_TEST_LEVEL');
            return;
        }
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

    protected function doTest($unit) {
        $testIdentifier = "phpunit/" . uniqid();

        ob_start();
        $this->callBetterCron($testIdentifier, $unit, function() {
            echo "foo1";
        });

        $this->assertEquals("foo1", ob_get_clean());

        $allJobs1 = BetterCron::getAllCronjobs();
        $this->assertGreaterThan(time() - 10, $allJobs1[$testIdentifier]);

        sleep(convertToSeconds($unit == TimeUnit::SECONDS ? 2 : 1, $unit));

        ob_start();
        $this->callBetterCron($testIdentifier, $unit, function() {
            echo "foo2";
        });

        $this->assertEmpty(ob_get_clean());

        $allJobs2 = BetterCron::getAllCronjobs();
        $this->assertEquals($allJobs2[$testIdentifier], $allJobs2[$testIdentifier]);

        sleep(
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
        BetterCron::seconds("phpunit/foo", 1, function() {
            
        });
        BetterCron::seconds("phpunit/bar", 1, function() {
            
        });
        $controller = new BetterCron();
        $settingsPage = $controller->settings();
        $this->assertTrue(stringContainsHtml($settingsPage));
        $this->assertStringContainsString(date("Y"), $settingsPage);
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
