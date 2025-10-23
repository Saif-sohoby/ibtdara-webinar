<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebinarRegistrationController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\IbtdaraRegistrationController;




Route::get('/', function () {
    return view('welcome');
});


Route::get('/register/{token}', [WebinarRegistrationController::class, 'showForm'])->name('webinar.register');
Route::post('/register/{token}', [WebinarRegistrationController::class, 'registerParticipant'])->name('webinar.register.post');
Route::get('/join/{uniqueCode}', [JoinController::class, 'handleJoin'])->name('webinar.join');



Route::get('register-ibtdara-webinar', [IbtdaraRegistrationController::class, 'showOrRedirect'])
    ->name('ibtdara.register.show');

Route::post('register-ibtdara-webinar', [IbtdaraRegistrationController::class, 'registerParticipant'])
    ->name('ibtdara.register.submit');