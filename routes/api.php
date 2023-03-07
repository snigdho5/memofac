<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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


Route::post('send_otp', 'ApiController@send_otp');
Route::post('send_otp_ios', 'ApiController@send_otp_ios');
Route::post('login', 'ApiController@login');
Route::post('loginWithPassword', 'ApiController@loginWithPassword');

Route::post('register', 'ApiController@register');
Route::get('check_version ', 'ApiController@app_version');
Route::get('country_list ', 'ApiController@country_list');

Route::get('test ', 'ApiController@test');
Route::get('test_notification ', 'ApiController@test_notification');
Route::post('subCategoryListNew', 'ApiController@subCategoryListNew');

Route::group(['middleware' => 'auth.jwt'], function () {
    Route::post('logout', 'ApiController@logout');
    Route::post('profile', 'ApiController@profile');
    Route::post('home', 'ApiController@home');
    Route::post('edit_profile', 'ApiController@edit_profile');
    Route::post('delete_profile_image', 'ApiController@delete_profile_image');
    Route::post('deleteUser', 'ApiController@deleteUser');
    Route::post('memo_list', 'ApiController@memo_list');
    Route::post('memo_list_new', 'ApiController@memo_list_new');
    Route::post('memo_list_for_recapture', 'ApiController@memo_list_for_recapture');
    Route::post('memorate', 'ApiController@memorate');
    Route::post('time_line', 'ApiController@time_line');
    Route::post('singlepost', 'ApiController@singlePost');
    Route::post('wishlist', 'ApiController@wishlist');
    Route::post('wishlist_memo', 'ApiController@wishlist_memo');
    Route::post('wishlist_folder', 'ApiController@wishlist_folder');
    Route::post('mainCategoryList', 'ApiController@mainCategoryList');
    Route::post('subCategoryList', 'ApiController@subCategoryList');
    Route::post('rateMemoList', 'ApiController@rateMemoList');
    Route::post('userDetails', 'ApiController@userDetails');
    Route::post('userCategoryDetails', 'ApiController@userCategoryDetails');
    Route::post('addContact', 'ApiController@addContact');
    Route::post('contactList', 'ApiController@contactList');
    Route::post('addMasterContact', 'ApiController@addMasterContact');
    Route::post('mastercontactList', 'ApiController@mastercontactList');
    Route::post('appContactList', 'ApiController@appContactList');
    Route::post('categoryDetails', 'ApiController@categoryDetails');
    Route::post('memoDetails', 'ApiController@memoDetails');
    Route::post('memoRelatedPost', 'ApiController@memoRelatedPost');
    Route::post('addCommment', 'ApiController@addCommment');
    Route::post('deleteCommment', 'ApiController@deleteCommment');
    Route::post('addReact', 'ApiController@addReact');
    Route::post('listComReact', 'ApiController@listComReact');
    Route::post('addRecapture', 'ApiController@addRecapture');
    Route::post('myReacapture', 'ApiController@myReacapture');
    Route::post('addMemo', 'ApiController@addMemo');
    Route::post('addPrimaryGroup', 'ApiController@addPrimaryGroup');
    Route::post('addSecondaryGroup', 'ApiController@addSecondaryGroup');
    Route::post('createGroup', 'ApiController@createGroup');
    Route::post('getGroupList', 'ApiController@getGroupList');
    Route::post('getGroupMemberList', 'ApiController@getGroupMemberList');
    Route::post('addMemberGroup', 'ApiController@addMemberGroup');
    Route::post('removerMemberGroup', 'ApiController@removerMemberGroup');
    Route::post('getGalleryImage', 'ApiController@getGalleryImage');
    Route::post('getCollectionFolder', 'ApiController@getCollectionFolder');
    Route::post('createPrimaryFolder', 'ApiController@createPrimaryFolder');
    Route::post('createSecondaryFolder', 'ApiController@createSecondaryFolder');
    Route::post('createSecondaryFolder', 'ApiController@createSecondaryFolder');
    Route::post('errorReport', 'ApiController@errorReport');


    Route::post('otherUserDetails', 'ApiController@otherUserDetails');
    Route::post('otherUserCategoryDetails', 'ApiController@otherUserCategoryDetails');
    Route::post('userReacapture', 'ApiController@userReacapture');
    Route::post('getWishlistGalleryImage', 'ApiController@getWishlistGalleryImage');
    Route::post('wishlistPost', 'ApiController@wishlistPost');
    Route::post('wishlistMemoList', 'ApiController@wishlistMemoList');
    Route::post('wishlistMemoCategoryList', 'ApiController@wishlistMemoCategoryList');
    Route::post('listRatedUser', 'ApiController@listRatedUser');
    Route::post('deletePost', 'ApiController@deletePost');
    Route::post('deleteMasterContact', 'ApiController@deleteMasterContact');

    Route::post('seen_memo', 'ApiController@markMemoSeen');
    Route::post('trending', 'ApiController@trending');
    Route::post('report_problem', 'ApiController@report_problem');
    Route::post('report_memo', 'ApiController@report_memo');


    Route::post('peoplelist', 'ApiController@peoplelist');
    Route::post('like_minded', 'ApiController@likeMinded');
    Route::post('different_minded', 'ApiController@differentMinded');
    Route::post('darkModeToggle', 'ApiController@darkModeToggle');


    Route::post('report_cats', 'ApiController@report_cats');
    Route::post('report_post', 'ApiController@report_post');

    Route::post('report_usr_cats', 'ApiController@report_usr_cats');
    Route::post('report_usr_report', 'ApiController@report_usr_report');

    
    Route::post('block_user', 'ApiController@block_user');
    Route::post('blocked_user', 'ApiController@blocked_user');
});
