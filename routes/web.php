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
    return view('admin/login');
});



//Login Admin
Route::resource('login','SessionController');

Route::group(['middleware' => ['auth']], function () {

Route::get('/', 'AdminController@index');



//Dashboard
Route::resource('dashboard','AdminController');

//Category
Route::resource('category','CategoryController');


//Logout
Route::get('/logout','SessionController@destroy');

});