<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['status' => 'ok', 'app' => config('app.name')]);
});

require __DIR__.'/auth.php';
