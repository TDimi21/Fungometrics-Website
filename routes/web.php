<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|

*/

Route::get('/test/{id}', function (string $id): void {
    preg_match(
        pattern: "/(.{8})(.{4})(.{4})(.{4})(.{12})/",
        subject: $id,
        matches: $matches
    );
    unset($matches[0]);

    $userId = Arr::join($matches, '-');

    return ;
});

Route::get('{any}', fn () => view('welcome'))->where('any', '.*');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
