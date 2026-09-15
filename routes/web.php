<?php

use App\Livewire\DummyModule;
use App\Livewire\Lobby;
use Illuminate\Support\Facades\Route;

Route::get('/', Lobby::class)->name('lobby');
Route::get('/app/{module}/{path?}', DummyModule::class)->where('path', '.*')->name('app.module');
