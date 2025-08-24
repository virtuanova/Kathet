<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to LMS Platform API',
        'version' => '1.0.0',
        'documentation' => '/api/documentation'
    ]);
});
