<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CrudController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('crud', 			'App\Http\Controllers\CrudController@getAllRecords');
Route::get('crud/{id}', 	'App\Http\Controllers\CrudController@getRecord');
Route::post('crud', 		'App\Http\Controllers\CrudController@createRecord');
Route::put('crud/{id}', 	'App\Http\Controllers\CrudController@updateRecord');
Route::delete('crud/{id}',	'App\Http\Controllers\CrudController@deleteRecord');
