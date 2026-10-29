<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

// One-off: make the sister app's accounts exactly match this app's.
// Accounts that exist only in the sister app are deleted.
Artisan::command('users:sync-sister', function () {
    if (!\App\Support\SisterUserSync::enabled()) {
        return $this->error('SISTER_DB_DATABASE is not set in .env.');
    }

    $emails = [];
    foreach (\App\Models\User::all() as $user) {
        \App\Support\SisterUserSync::push($user);
        $emails[] = strtolower($user->email);
    }

    $extra = DB::connection('sister')->table('users')->get(['email'])
        ->reject(fn ($row) => in_array(strtolower($row->email), $emails, true));
    foreach ($extra as $row) {
        \App\Support\SisterUserSync::remove($row->email);
    }

    $this->info(count($emails) . ' accounts copied, ' . $extra->count() . ' extra removed from the sister app.');
})->purpose('Copy every user account to the sister app');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
