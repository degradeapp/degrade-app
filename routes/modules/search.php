<?php

use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/search', [SearchController::class, 'index']);
});
