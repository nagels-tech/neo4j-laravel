<?php

use App\Http\Controllers\DemoCaptureController;
use App\Http\Controllers\HtmlMovieController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('movies.index');
});

Route::get('/movies', [HtmlMovieController::class, 'index'])->name('movies.index');
Route::post('/movies', [HtmlMovieController::class, 'store'])->name('movies.store');
Route::post('/movies/actors', [HtmlMovieController::class, 'addActor'])->name('movies.actors.store');
Route::get('/movies/{title}', [HtmlMovieController::class, 'show'])->name('movies.show');
Route::delete('/movies/{title}', [HtmlMovieController::class, 'destroy'])->name('movies.destroy');
Route::get('/movies/{title}/similar', [HtmlMovieController::class, 'findSimilar'])->name('movies.similar');

// Debugbar capture demos (real Cypher via Client / Driver / Session / Transaction).
Route::get('/demo/client', [DemoCaptureController::class, 'client']);
Route::get('/demo/driver', [DemoCaptureController::class, 'driver']);
Route::get('/demo/session', [DemoCaptureController::class, 'session']);
Route::get('/demo/transaction', [DemoCaptureController::class, 'transaction']);
