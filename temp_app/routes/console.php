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

// The website calendar. Five minutes is short enough that ticking an event
// feels immediate; a run that changes nothing costs one read, so the shop stays
// well inside Shopify's rate limits. If the PC is off, the storefront simply
// keeps showing what it already has.
Schedule::command('imprint:shopify-calendar')->everyFiveMinutes()->withoutOverlapping();
