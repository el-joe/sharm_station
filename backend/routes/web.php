<?php

use App\Http\Controllers\IcalExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// iCal export of a room's blocked nights for other channels (secret token in the URL).
Route::get('ical/rooms/{room}/{token}.ics', IcalExportController::class)
    ->whereNumber('room')
    ->where('token', '[A-Za-z0-9]{20,64}')
    ->middleware('throttle:60,1')
    ->name('ical.room');
