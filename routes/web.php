<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Lobby;
use App\Livewire\DummyModule;

Route::get('/', Lobby::class)->name('lobby');
Route::get('/app/{module}/{path?}', DummyModule::class)->where('path', '.*')->name('app.module');
