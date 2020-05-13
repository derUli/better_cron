<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use function UliCMS\HTML\stringContainsHtml;

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
    public function testAfterHTML(){
        $this->assertFalse(defined("CRONJOBS_REGISTERED"));
        
        $controller = new BetterCron();
        $controller->afterHtml();
        
        $this->assertTrue(defined("CRONJOBS_REGISTERED"));
    }

    public function testSeconds() {
        $this->doTest("seconds");
    }

    public function testMinutes() {
        if ($this->level < 2) {
            $this->markTestSkipped('Skipped because of BETTER_CRON_TEST_LEVEL');
            return;
        }
        $this->doTest("minutes");
    }

    public function testHours() {
        if ($this->level < 3) {
            $this->markTestSkipped('Skipped because of BETTER_CRON_TEST_LEVEL');
            return;
        }

        $this->doTest("hours");
    }

    public function testDays() {
        if ($this->level < 4) {
            $this->markTestSkipped('Skipped because of BETTER_CRON_TEST_LEVEL');
            return;
        }
        $this->doTest("days");
    }

    public function testWeeks() {
        if ($this->level < 5) {
            $this->markTestSkipped('Skipped because of BETTER_CRON_TEST_LEVEL');
            return;
        }
        $this->doTest("weeks");
    }

    public function testMonths() {
        if ($this->level < 6) {
            $this->markTestSkipped('Skipped because of BETTER_CRON_TEST_LEVEL');
            return;
        }
        $this->doTest("months");
    }

    public function testYears() {
        if ($this->level < 7) {
            $this->markTestSkipped('Skipped because of BETTER_CRON_TEST_LEVEL');
            return;
        }
        $this->doTest("years");
    }

    public function testDecades() {
        if ($this->level < 8) {
            $this->markTestSkipped('Skipped because of BETTER_CRON_TEST_LEVEL');
            return;
        }
        $this->doTest("decades");
    }

    public function testWithControllerCallback() {
        $testIdentifier = "phpunit/" . uniqid();

        ob_start();
        $this->callBetterCron(
                $testIdentifier,
                "seconds",
                "BetterCron::testCallback"
        );
        $this->assertEquals("foo", ob_get_clean());
    }

    public function testWithGlobalMethod() {
        $testIdentifier = "phpunit/" . uniqid();
        ob_start();
        $this->callBetterCron(
                $testIdentifier,
                "seconds",
                "year"
        );

        $this->assertGreaterThanOrEqual(2020, intval(ob_get_clean()));
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

        sleep(BetterCron::calculateSeconds($unit == 'seconds' ? 2 : 1, $unit));

        ob_start();
        $this->callBetterCron($testIdentifier, $unit, function() {
            echo "foo2";
        });

        $this->assertEmpty(ob_get_clean());

        $allJobs2 = BetterCron::getAllCronjobs();
        $this->assertEquals($allJobs2[$testIdentifier], $allJobs2[$testIdentifier]);

        sleep(
                BetterCron::calculateSeconds(
                        $unit == 'seconds' ? 5 : 1,
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
                $unit == 'seconds' ? 5 : 2,
                $callable
        );
    }

    public function testConvert() {
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
                    BetterCron::calculateSeconds(
                            1,
                            $unit
                    ),
                    "1 $unit is not $expectedSeconds seconds"
            );
        }
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
