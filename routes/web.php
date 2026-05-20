<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

require __DIR__.'/modules/auth.php';
require __DIR__.'/modules/customer.php';
