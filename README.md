# Better Cron

Better Cronjobs for UliCMS.
Provides methods to run a function in a regular time interval (e.g. every 4 hours).

It's best practice to call BetterCron in the "cron" Hook.
The first argument of all methods is an identifier for the Cronjob which must be unique.
The second argument is a timespan as integer (e.g. 14).
The third argument musst be an anonymous function or a string containing a function name.
To run a public controller function enter the name like "MyController::myFunction".

## Example

```php
<?php
// run a function every 30 seconds
BetterCron::seconds("module/my_module/job1", 30, function () {
    // Do Something
});

// run a function every 10 minutes
BetterCron::minutes("module/my_module/job2", 10, "my_function");

// run a function every 4 hours
BetterCron::hours("module/my_module/job3", 4, "MyController::myFunction");

// run a function every 7 days
BetterCron::days("module/my_module/job4", 7, function () {
    // Do Something
});

// run a function every 3 months
BetterCron::months("module/my_module/job5", 3, function () {
    // Do Something
});

// run a function every 5 years
BetterCron::years("module/my_module/job6", 5, function () {
    // Do Something
});
?>
```
