<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('audit:retention')->dailyAt('01:00')->withoutOverlapping();

// Same slot and the same shape of rule: a retention window nothing reads from,
// which only holds if something runs it without being asked.
Schedule::command('exports:prune')->dailyAt('01:30')->withoutOverlapping();
