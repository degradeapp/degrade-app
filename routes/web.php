<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

require __DIR__.'/modules/auth.php';
require __DIR__.'/modules/customer.php';
require __DIR__.'/modules/barber.php';
require __DIR__.'/modules/service.php';
require __DIR__.'/modules/appointment.php';
