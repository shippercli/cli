<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/jokes', function () {
    return response()->json([
        'joke' => 'Why do programmers prefer dark mode? Because light attracts bugs!',
        'type' => 'programming',
        'setup' => 'Why do programmers prefer dark mode?',
        'punchline' => 'Because light attracts bugs!',
    ]);
});
