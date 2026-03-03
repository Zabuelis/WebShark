<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Schedule::command('app:delete-expired-redis-jobs')->everyMinute();
Schedule::command('app:delete-expired-ip-markers')->everyMinute();
