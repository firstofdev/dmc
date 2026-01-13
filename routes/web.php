<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PropertyController;

Route::get('/', function () { return view('dashboard'); }); // الصفحة الرئيسية

// روابط العقارات
Route::resource('properties', PropertyController::class);
