<?php

use Illuminate\Support\Facades\Schedule;

// Process no-shows nightly at midnight
Schedule::command('reservations:process-no-shows')->dailyAt('00:00');
