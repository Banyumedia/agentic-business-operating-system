<?php

use App\Livewire\DummyModule;
use App\Livewire\Lobby;
use App\Livewire\Settings;
use Illuminate\Support\Facades\Route;

Route::get('/', Lobby::class)->name('lobby');
Route::get('/app/settings', Settings::class)->name('app.settings');
Route::get('/app/{module}/{path?}', DummyModule::class)->where('path', '.*')->name('app.module');
