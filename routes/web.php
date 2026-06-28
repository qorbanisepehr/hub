<?php

use Illuminate\Support\Facades\Route;

Route::get('/{path?}', function (?string $path = null) {
    return view('app');
})->where('path', '^(?!api).*');
