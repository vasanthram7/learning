<?php

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

Route::get('/', function () {
    return view('welcome');
});


Route::get('crud', 'CrudController@getAllStudents');
Route::get('crud/{id}', 'CrudController@getStudent');
Route::post('crud', 'CrudController@createStudent');
Route::put('crud/{id}', 'CrudController@updateStudent');
Route::delete('crud/{id}','CrudController@deleteStudent');