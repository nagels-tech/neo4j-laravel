<?php

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
