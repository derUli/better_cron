<?php

declare(strict_types=1);

// This module provides methods to run functions in a regular interval
class BetterCron extends MainClass {

    public function afterHtml(): void {
        do_event("register_cronjobs");
    }

    // Run a method every X seconds
    public static function seconds(string $job, int $seconds, $callback): void {
        // When was the last run of this job?
        $currentTime = time();
        $last_run = self::getLastRun($job);

        // Is the time range between the last run and now larger or equal to
        // $seconds?
        // If not then abort here.
        if ($currentTime - $last_run < $seconds) {
            return;
        }
        if (is_string($callback)) {
            // update last run for this job before running it
            // to prevent running the job multiple at the same time
            self::updateLastRun($job);
            // Callback can be a controller method name as string
            // e.g. MyController::myMethod
            if (str_contains("::", $callback)) {
                self::executeControllerCallback($callback);
            } else {
                // if $callback is a string without ::
                // then it is a normal (non controller) method name
                self::updateLastRun($job);
                call_user_func($callback);
            }
        } else if (is_callable($callback)) {
            // if $callback is a callbable function then execute it
            self::updateLastRun($job);
            $callback();
        }
    }

    // parse a string in the format MyController::myMethod and call
    // a controller action (if it exists)
    protected static function executeControllerCallback(string $callback): void {
        $args = explode("::", $callback);
        $sClass = $args[0];
        $sMethod = $args[1];
        // If this method exists, execute it
        // FIXME: if the job doesn't exists log an error
        if (ControllerRegistry::get($sClass) and
                method_exists(ControllerRegistry::get($sClass), $sMethod)) {
            ControllerRegistry::get($sClass)->$sMethod();
        }
    }

    // Run a method every X minutes
    public static function minutes(string $job, int $minutes, $callback): void {
        self::seconds(
                $job,
                self::calculateSeconds($minutes, "minutes"),
                $callback
        );
    }

    // Run a method every X hours
    public static function hours(string $job, int $hours, $callback): void {
        self::seconds(
                $job,
                self::calculateSeconds($hours, "hours"),
                $callback
        );
    }

    // Run a method every X days
    public static function days(string $job, int $hours, $callback): void {
        self::seconds(
                $job,
                self::calculateSeconds($hours, "days"),
                $callback
        );
    }

    // Run a method every X weeks
    public static function weeks(string $job, int $weeks, $callback): void {
        self::seconds(
                $job,
                self::calculateSeconds($weeks, "weeks"),
                $callback
        );
    }

    // Run a method every X months
    public static function months(string $job, int $months, $callback): void {
        self::seconds(
                $job,
                self::calculateSeconds($months, "months"),
                $callback
        );
    }

    // Run a method every X years
    public static function years(string $job, int $years, $callback): void {
        self::seconds(
                $job,
                self::calculateSeconds($years, "years"),
                $callback
        );
    }

    public static function decades(string $job, int $decades, $callback): void {
        self::seconds(
                $job,
                self::calculateSeconds($decades, "decades"),
                $callback
        );
    }

    // calculate a time in a given unit in seconds
    public static function calculateSeconds(int $timespan, string $unit): int {
        switch ($unit) {
            case 'minutes':
                return $timespan * 60;
            case 'hours':
                return $timespan * 60 * 60;
            case 'days':
                return $timespan * 60 * 60 * 24;
            case 'weeks':
                return $timespan * 60 * 60 * 24 * 7;
            case 'months':
                return $timespan * 60 * 60 * 24 * 30;
            case 'years':
                return $timespan * 60 * 60 * 24 * 365;
            case 'decades':
                return $timespan * 60 * 60 * 24 * 365 * 10;
            default:
                return $timespan;
        }
    }

    // returns the timestamp when did a job run the last time
    // if not run yet return 0 (year 1970)
    private static function getLastRun($name): int {
        $last_run = 0;

        $query = Database::pQuery(
                        "select last_run from `{prefix}cronjobs` where name = ?",
                        [
                            $name
                        ],
                        true);
        if (Database::any($query)) {
            $result = Database::fetchObject($query);
            $last_run = intval($result->last_run);
        }
        return $last_run;
    }

    // update the last run date of a cronjob
    private static function updateLastRun(string $name): void {
        // if this job exists update in database do an sql update else
        // an sql insert
        $query = Database::selectAll("cronjobs", ["name"], "name = ?", [$name]);

        $args = Database::any($query) ? [
            time(),
            $name
                ] : [
            $name,
            time()
        ];
        $sql = Database::any($query) ?
                "update `{prefix}cronjobs` set last_run = ? where name = ?" :
                "insert into `{prefix}cronjobs` (name, last_run) "
                . "values(?, ?)";

        Database::pQuery(
                $sql,
                $args,
                true
        );
    }

    // get all cronjobs in database as array of
    // name => timestamp
    public static function getAllCronjobs(): array {
        $cronjobs = array();
        $query = Database::query(
                        "select name, last_run from `{prefix}cronjobs` "
                        . "order by name",
                        true
        );
        while ($row = Database::fetchObject($query)) {
            $cronjobs[$row->name] = intval($row->last_run);
        }
        return $cronjobs;
    }

    // Settins page
    public function settings(): string {
        return Template::executeModuleTemplate("better_cron", "list.php");
    }

    // As the method name says translates the headline for the module's settings
    // page
    public function getSettingsHeadline(): string {
        return get_translation("cronjobs");
    }

    // before uninstall rollback migrations (Drop cronjobs Table)
    public function uninstall(): void {
        $migrator = new DBMigrator(
                "package/better_cron",
                ModuleHelper::buildRessourcePath("better_cron", "sql/down")
        );
        $migrator->rollback();
    }

    public function testCallback() {
        if (isCLI()) {
            echo "foo";
        }
    }

    public function registerCronjobs() {
        if(isCLI()){
            idefine("CRONJOBS_REGISTERED", true);
        }
    }
}
