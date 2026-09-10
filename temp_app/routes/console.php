<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hourly, not at a set hour: the shop machine is off overnight, so a backup
// booked for 01:30 never ran once. The command itself stops after the first
// success of the day, so this is one backup a day, taken whenever the computer
// happens to be switched on.
Schedule::command('imprint:backup')->hourly()->withoutOverlapping();
Schedule::command('imprint:tunnel-check')->everyFiveMinutes()->withoutOverlapping();

// The website calendar. Fifteen minutes is soon enough for an events diary and
// keeps the shop machine off Shopify's rate limits; if the PC is off, the
// storefront simply keeps showing what it already has.
Schedule::command('imprint:shopify-calendar')->everyFifteenMinutes()->withoutOverlapping();
