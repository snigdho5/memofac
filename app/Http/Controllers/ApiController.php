<?php

namespace App\Http\Controllers;

use JWTAuth;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Validator;
use Illuminate\support\str;
use App\User;
use App\AppVersion;
use App\UserLogin;
use App\Country;
use App\UserOtp;
use App\Memo;
use App\Post;
use App\PostDetail;
use App\PostImage;
use App\PostReact;
use App\PostComment;
use App\PostWishlist;
use App\MemoWishlist;
use App\PostRating;
use App\Recapture;
use App\RecapImage;
use App\MainCategory;
use App\ExpCategory;
use App\UserContact;
use App\MasterContact;
use App\Group;
use App\GroupMember;
use App\PrimaryFolder;
use App\SecondaryFolder;
use App\FolderWishlist;
use App\Notification;
use App\SeenMemo;
use App\Report;
use Carbon\Carbon;


class ApiController extends Controller
{


    public function __construct()
    {
        $this->user = new User;
        date_default_timezone_set("Asia/Calcutta");
    }


    private function getImageUrl($image)
    {

        return asset("public/uploads/images/$image");
    }


    private function getFolderImageUrl($image, $url)
    {

        return "https://admin.memofac.in/public/images/$url/$image";
        // return asset("admin/public/uploads/images/$url/$image");
    }

    private function countExpercinaces($id)
    {
        //give total user wjo have rated the memo 
        $data = PostRating::where(['post_id' => $id])->count();
        return $data;
    }

    private function meRating($id, $user_id)
    {
        //My Rating against memo
        $data = PostRating::where(['user_id' => $user_id, 'post_id' => $id])->pluck('rating')->first();
        if ($data) {
            return round($data, 1);
        }
        return 0;
    }

    private function getRating($id)
    {
        //Avg memo rating //global rating
        $data = PostRating::where(['post_id' => $id])->where('rating', '!=', '0.0')->pluck('rating')->avg();
        if ($data) {
            return round($data, 1);
        }
        return 0;
    }

    private function getKnownRating($id, $user_id)
    {
        //Avg memo rating for knownone
        $data = PostRating::join('user_contact', 'user_contact.contact_user_id', '=', 'post_ratings.user_id')->where(['post_ratings.post_id' => $id, 'user_contact.user_id' => $user_id])->where('rating', '!=', '0.0')->pluck('rating')->avg();
        if ($data) {
            return round($data, 1);
        }
        return 0;
    }

    private function getCloseoneRating($id, $user_id)
    {
        //Avg memo rating for knownone
        $data = PostRating::join('user_contact', 'user_contact.contact_user_id', '=', 'post_ratings.user_id')->join('group_members', 'group_members.member_id', '=', 'user_contact.id')->where(['post_ratings.post_id' => $id, 'group_members.group_id' => 2, 'group_members.created_by' => $user_id])->where('rating', '!=', '0.0')->pluck('rating')->avg();
        if ($data) {
            return round($data, 1);
        }
        return 0;
    }

    private function checkWishlistPost($post_id, $user_id)
    {
        $wish = PostWishlist::where(['post_id' => $post_id, 'user_id' => $user_id])->first();
        if ($wish) {
            return true;
        }
        return false;
    }

    private function checkWishlistMemo($memo_id, $user_id)
    {
        $wish = MemoWishlist::where(['memo_id' => $memo_id, 'user_id' => $user_id])->first();
        if ($wish) {
            return true;
        }
        return false;
    }

    private function checkWishlistFolder($secondary_id, $user_id)
    {
        $wish = FolderWishlist::where(['secondary_id' => $secondary_id, 'user_id' => $user_id])->first();
        if ($wish) {
            return true;
        }
        return false;
    }

    private function checkExperienceMemo($memo_id, $user_id)
    {
        $data = PostRating::where(['user_id' => $user_id, 'post_id' => $memo_id])->first();
        if ($data) {
            return true;
        }
        return false;
    }

    private function getContactName($user_id, $contact_user_id, $name)
    {
        if ($user_id == $contact_user_id) {
            return $name;
        }

        $contact = DB::table('user_contact')->select('name')->where(['contact_user_id' => $contact_user_id, 'user_id' => $user_id])->first();
        if (!empty($contact)) {
            return $contact->name;
        }
        return $name;
    }

    private function getUserData()
    {
    }

    private function getUserType($user_id, $contact_user_id)
    {
        if ($user_id == $contact_user_id) {
            return '1';
        }

        $contact = DB::table('user_contact')->select('id')->where(['contact_user_id' => $contact_user_id, 'user_id' => $user_id])->first();
        if (!empty($contact)) {
            return "1";
        }
        return '3';
    }

    private function getKnowncontact($user_id)
    {
        //get count of user from contacts who has onboarded on app.
        $contact = DB::table('user_contact')->where('user_id', '=', $user_id)->count();
        return $contact;
    }

    private function getMycontact($user_id)
    {
        //get count of user from contacts who has onboarded on app.
        $contact = DB::table('user_contact')->select('contact_user_id')->where('user_id', '=', $user_id)->get();
        return $contact;
    }

    private function getMyGroups($user_id, $contact_user_id)
    {
        $a = array(3);
        //post created by user, viewing user
        $contact = DB::table('user_contact')->select('id')->where(['contact_user_id' => $contact_user_id, 'user_id' => $user_id])->first();
        if (!empty($contact)) {
            array_push($a, 1);
            $gm = DB::table('members_by_group')->select('group_id')->where(['contact_user_id' => $contact_user_id, 'created_by' => $user_id])->get();
            if (!empty($gm)) {
                foreach ($gm as $g) {
                    array_push($a, $g->group_id);
                }
            }
        }
        return implode(',', $a);
    }

    private function getUserList($memo_id, $user_id, $type = '3', $limit = 100)
    {
        if ($type == '1') {
            //for contacts

            $users = DB::table('memo_user_rate_detail')->select(['user_id as id', 'name', 'image', 'rating', 'share_with'])->where('post_id', '=', $memo_id)->whereRaw('`user_id` IN (SELECT `contact_user_id` from `mf_user_contact` where `user_id` = "' . $user_id . '")')->where('share_with', '!=', 0)->whereIn('share_with', ['1', '3'])->orderBy('memo_user_rate_detail.updated_at', 'DESC')->take($limit)->get();
        } elseif ($type == '2') {
            //for closeones
            $users = DB::table('memo_user_rate_detail')->select(['user_id as id', 'name', 'image', 'rating', 'share_with'])->where('post_id', '=', $memo_id)->whereRaw('(`share_with` IN (SELECT `group_id` FROM `mf_members_by_group` WHERE `created_by` = `mf_memo_user_rate_detail`.`user_id` AND `contact_user_id` =  "' . $user_id . '"))')->where('share_with', '!=', 0)->whereIn('share_with', ['2', '3'])->orderBy('memo_user_rate_detail.updated_at', 'DESC')->take($limit)->get();
        } elseif ($type == '3') {
            //for public
            $users = DB::table('memo_user_rate_detail')->select(['user_id as id', 'name', 'image', 'rating', 'share_with'])->where('post_id', '=', $memo_id)->whereRaw('((`share_with` = 3) OR (`share_with` = 1 AND `user_id` IN (SELECT `contact_user_id` from `mf_user_contact` where `user_id` = "' . $user_id . '")) OR (`share_with` IN (SELECT `group_id` FROM `mf_members_by_group` WHERE `created_by` = `mf_memo_user_rate_detail`.`user_id` AND `contact_user_id` =  "' . $user_id . '")) OR (`share_with` = 0 AND `user_id` = "' . $user_id . '") )')->orderBy('memo_user_rate_detail.updated_at', 'DESC')->take($limit)->get();
        }

        return $users;
    }
    public $loginAfterSignUp = true;



    //============================= Memofac API ==================================//


    public function app_version()
    {
        $app_version = AppVersion::first();
        return response()->json([
            'result' => 'success',
            'message' => '',
            'version' => $app_version,
        ], 200);
    }

    public function country_list()
    {
        $content = Country::Select(['id', 'name', 'dial_code'])->get();
        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content,
        ], 200);
    }
    private function send_message($country_code, $mobile, $message)
    {
        $smsSerice = 2;
        if ($smsSerice == 1) {
            //BULK SMS SENDER

            $code = str_replace("+", "", $country_code);
            $curl = curl_init();



            $data = array();
            $data['api_id'] = "APIZPgOScYt66193";
            $data['api_password'] = "lQjPxutG";
            $data['sms_type'] = "Transactional";
            $data['sms_encoding'] = "text";
            if ($code == '91') {
                $data['sender'] = "MEMOFC";
            } else {
                $data['sender'] = "MEMOFAC";
            }

            $data['number'] = $code . $mobile;
            $data['message'] = $message;
            $data['template_id'] = "1207162505887918048";

            $data_string = json_encode($data);

            $ch = curl_init('https://www.bulksmsplans.com/api/send_sms');

            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data_string)
                )
            );

            $res = curl_exec($ch);
            $result = curl_close($ch);
            return $res;
        } else if ($smsSerice == 2) {
            //textlocal
            $curl = curl_init();

            $code = str_replace("+", "", $country_code);
            $curl = curl_init();

            $apiKey = "NmY0OTU1NGY1NTM4Mzg3MTQ5NDY3MTc1NTA2YTRmNDY=";
            if ($code == '91') {
                $sender = "MEMOFC";
            } else {
                $sender = "MEMOFAC";
            }

            $number = $code . $mobile;

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.textlocal.in/send/?apiKey=' . $apiKey . '&sender=' . $sender . '&numbers=' . $number . '&message=' . urlencode($message),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Cookie: PHPSESSID=l51a7r26jm3n8c4njt6odh0u22'
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            return $response;
        } else {
        }
    }


    public function send_otp(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'country_code' => 'required',
            'phone' => 'required',
        ]);

        $status = 'new';

        if ($validator->fails()) {

            return response()->json([
                'result' => 'failure',
                'otp' => '',
                'message' => json_encode($validator->errors()),
                'status' => $status
            ], 400);
        }
        $mobile = $request['phone'];
        $country_code = $request['country_code'];
        $check  = User::select(['name', 'phone', 'image'])->where(['phone' => $mobile])->first();
        if (!empty($check)) {
            $status = 'old';
            if ($check->image !== '' && $check->image != null) {
                $check->image =  asset('public/images/' . $check->image);
            }
        }


        if ($mobile == '9903272861') {
            $otp = 123456;
            $message = $otp . " is your verification code. Enjoy sharing experiences ! Team Memofac";
            //$a = $this->send_message($country_code,$mobile,$message);
            $a = 'This is developer contact no.';
        } else {
            $otp = rand(100000, 999999);

            $message = $otp . " is your verification code. Enjoy sharing experiences ! Team Memofac";
            $a = $this->send_message($country_code, $mobile, $message);
        }



        $time = date("Y-m-d H:i:s", strtotime('15 minutes'));

        UserOtp::updateOrcreate([
            'country_code' => $country_code,
            'mobile' => $mobile
        ], [
            'otp' => $otp,
            'timestamps' => $time,
        ]);

        return response()->json([
            'result' => 'success',
            'message' => 'SMS Sent SuccessFully',
            'status' => $status,
            'res' => $a,
            'user' => $check
        ], 200);
    }




    public function send_otp_ios(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'country_code' => 'required',
            'phone' => 'required',
        ]);

        $status = 'new';

        if ($validator->fails()) {

            return response()->json([
                'result' => 'failure',
                'otp' => '',
                'message' => json_encode($validator->errors()),
                'status' => $status
            ], 400);
        }
        $mobile = $request['phone'];
        $country_code = $request['country_code'];
        $check  = User::select(['name', 'phone', 'image'])->where(['phone' => $mobile])->first();
        if (!empty($check)) {
            $status = 'old';
            if ($check->image !== '' && $check->image != null) {
                $check->image =  asset('public/images/' . $check->image);
            }
        }


        if ($mobile == '9903272861') {
            $otp = 123456;
            $message = $otp . " is your verification code. Enjoy sharing experiences ! Team Memofac";
            //$a = $this->send_message($country_code,$mobile,$message);
            $a = 'This is developer contact no.';
        } else {
            $otp = rand(100000, 999999);
            // $otp = 123456;

            $message = $otp . " is your verification code. Enjoy sharing experiences ! Team Memofac";
            $a = $this->send_message($country_code, $mobile, $message);
        }



        $time = date("Y-m-d H:i:s", strtotime('15 minutes'));

        UserOtp::updateOrcreate([
            'country_code' => $country_code,
            'mobile' => $mobile
        ], [
            'otp' => $otp,
            'timestamps' => $time,
        ]);

        return response()->json([
            'result' => 'success',
            'message' => 'SMS Sent SuccessFully',
            'status' => $status,
            'res' => $a,
            'user' => $check
        ], 200);
    }

    public function login(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'phone' => 'required',
            'otp' => 'required',
            'deviceID' => '',
            'deviceToken' => '',
            'deviceType' => '',
        ]);
        $user = null;
        $status = 'new';
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'token' => null,
                'status' => $status,
                'message' => json_encode($validator->errors()),
                'user' => $user
            ], 400);
        }
        $time = date("Y-m-d H:i:s", strtotime('-15 minutes'));
        $verify_otp  = UserOtp::where(['mobile' => $request->only('phone'), 'otp' => $request['otp']])->where('timestamps', '>', $time)->first();
        if (empty($verify_otp)) {
            return response()->json([
                'result' => 'failure',
                'token' => null,
                'status' => $status,
                'message' => 'Invalid Otp.',
                'user' => $user
            ], 200);
        }
        $credentials = $request->only('phone');
        $user = User::where(['phone' => $credentials])->first();
        $check  = User::where(['phone' => $credentials])->first();
        if (!empty($user)) {
            $status = 'old';
        }
        try {
            if (!empty($user)) {
                if (!$token = JWTAuth::fromUser($user)) {
                    return response()->json([
                        'result' => 'failure',
                        'token' => null,
                        'status' => $status,
                        'message' => 'invalid_credentials',
                        'user' => null
                    ], 400);
                }
            } else {
                return response()->json([
                    'result' => 'success',
                    'status' => $status,
                    'message' => '',
                    'token' => null,
                    'user' => $user
                ], 200);
            }
        } catch (JWTException $e) {
            return response()->json([
                'result' => 'failure',
                'token' => null,
                'status' => $status,
                'message' => 'could_not_create_token',
                'user' => null
            ], 500);
        }
        $deviceID = $request->input("deviceID");
        $deviceToken = $request->input("deviceToken");
        $deviceType = $request->input("deviceType");
        $device_info = UserLogin::where(['user_id' => $user->id])->first();
        if (!empty($device_info)) {
            $device_info->deviceToken = $deviceToken;
            $device_info->deviceType = $deviceType;
            $device_info->save();
            //unset($user->id);
            if ($user->image !== '' && $user->image != null) {
                $user->image =  asset('public/images/' . $user->image);
            }
            return response()->json([
                'result' => 'success',
                'token' => $token,
                'message' => 'Successful Login',
                'status' => $status,
                'user' => $user
            ], 200);
        }
        UserLogin::create([
            "user_id" => $user->id,
            "ip_address" => $request->ip(),
            "deviceID" => $deviceID,
            "deviceToken" => $deviceToken,
            "deviceType" => $deviceType,
        ]);
        //unset($user->id);
        if ($user->image !== '' && $user->image != null) {
            $user->image =  asset('public/images/' . $user->image);
        }
        return response()->json([
            'result' => 'success',
            'token' => $token,
            'message' => 'Successful Login',
            'status' => $status,
            'user' => $user
        ], 200);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|max:255',
            'phone' => 'required|unique:users',
            'deviceID' => '',
            'deviceToken' => '',
            'deviceType' => '',
        ]);
        if ($validator->fails()) {

            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'token' => null,
                'user' => null
            ], 400);
        }
        $country_code = (empty($request->country_code)) ? '+91' : $request->country_code;

        $user = new User();

        $user->name = $request->username;

        $user->username = $request->username;

        // $user->email = $request->email;

        $user->country_code = $country_code;
        $user->phone = $request->phone;

        // $user->password = $request->password;

        $user->dob = $request->dob;

        $user->gender = $request->gender;

        $image = $request->file('image');
        if (!empty($image)) {
            //Store Image In Folder
            $imageName = time() . '.' . request()->image->getClientOriginalExtension();
            request()->image->move(public_path('images/'), $imageName);

            $user->image = $imageName;
        } else {
            $user->image = NULL;
        }

        $user->save();

        $credentials = $request->only('phone');
        $user = User::where($credentials)->first();

        try {
            if (!empty($user)) {
                if (!$token = JWTAuth::fromUser($user)) {
                    return response()->json([
                        'result' => 'failure',
                        'token' => null,
                        'message' => 'invalid_credentials',
                        'user' => null
                    ], 400);
                }
            } else {
                return response()->json([
                    'result' => 'failure',
                    'token' => null,
                    'message' => 'invalid_credentials',
                    'user' => null
                ], 400);
            }
        } catch (JWTException $e) {
            return response()->json([
                'result' => 'failure',
                'token' => null,
                'message' => 'could_not_create_token',
                'user' => null
            ], 500);
        }
        $deviceID = $request->input("deviceID");
        $deviceToken = $request->input("deviceToken");
        $deviceType = $request->input("deviceType");
        $device_info = UserLogin::where(['user_id' => $user->id])->first();
        UserLogin::create([
            "user_id" => $user->id,
            "ip_address" => $request->ip(),
            "deviceID" => $deviceID,
            "deviceToken" => $deviceToken,
            "deviceType" => $deviceType,
        ]);

        if (!empty($user->id)) {
            if ($user->image !== '' && $user->image != null) {
                $user->image =  asset('public/images/' . $user->image);
            }
            $member_ids = DB::table('user_contact')->selectRaw('DISTINCT(user_id)')->where('contact_user_id', '=', $user->id)->get();
            if (!empty($member_ids)) {
                $title = 'New User';

                foreach ($member_ids as $mid) {
                    $body = $this->getContactName($mid->user_id, $user->id, $request->username) . ' joined the app';
                    $loginDetail = UserLogin::where(['user_id' => $mid->user_id])->get();
                    if (!empty($loginDetail)) {
                        foreach ($loginDetail as $l) {
                            //$this->send_notification($title, $body, $l->deviceToken , $user->id);
                            $sendData = array(
                                'body' => 'Joined your app',
                                'title' => $this->getContactName($l->user_id, $user->id, $request->username),
                                'sound' => 'Default',
                                'data' => array(
                                    'Type' => 'contact_joined',
                                    'user' => $user
                                ),
                                'image' => $user->image
                            );
                            $this->fcmNotification($l->deviceToken, $sendData);
                        }

                        // Notification::create(['user_id'=>$mid->user_id,'notification'=>$body,
                        // 'r_userid'=>$user->id,'post_id'=>0]);
                    }
                }
            }
        }
        // unset($user->id);


        return response()->json([
            'result' => 'success',
            'token' => $token,
            'message' => 'Successful Login',
            'user' => $user
        ], 200);
    }

    public function mainCategoryList(Request $request)
    {

        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        $content = MainCategory::select(['id', 'name'])->where(['status' => 'Y'])->latest()->get();
        foreach ($content as $key => $row) {
            $subcategory = ExpCategory::select(['id', 'category_name', 'type', 'icon', 'priority'])->where(['main_cate_id' => $row->id, 'status' => 'Y'])->orderBy('priority', 'DESC')->get();
            foreach ($subcategory as $key => $value) {
                $value->icon = $this->getFolderImageUrl($value->icon, 'icon');
            }

            $row->subcategory = $subcategory;
        }
        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }


    public function wishlist_folder(Request $request)
    {

        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'secondary_id' => 'required'
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }
        $user_id = $user->id;
        $secondary_id = $request->secondary_id;

        $msg = '';
        if (!empty($secondary_id)) {
            $sid = explode(',', $secondary_id);
            foreach ($sid as $id) {
                $data = FolderWishlist::where(['user_id' => $user_id, 'secondary_id' => $id])->first();
                if (empty($data)) {
                    FolderWishlist::updateOrcreate([
                        'user_id' => $user_id,
                        'secondary_id' => $id
                    ], []);
                } else {
                    FolderWishlist::where(['secondary_id' => $id, 'user_id' => $user_id])->delete();
                }
            }
            $msg = 'Favourite Updated';
        }



        return response()->json([
            'result' => 'success',
            'message' => $msg,
        ], 200);
    }

    // SEARCH MEMO
    public function memo_list_for_recapture(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        $search = (empty($request->search_text)) ? '' : $request->search_text;
        if (!empty($search)) {

            // $content = DB::select('SELECT * FROM `mf_experiences` WHERE  title like "%' . $search . '%" order by case when title LIKE "' . $search . '%" then 1 else 2 end')->get();
            // $content = DB::select( DB::raw('SELECT * FROM `mf_experiences` WHERE  title like "%' . $search . '%" order by case when title LIKE "' . $search . '%" then 1 else 2 end') );

            $content = Memo::select(['experiences.id', 'experiences.title', 'experiences.description', 'experiences.image'])
                ->where('experiences.title', 'like', $search . '%')
                // ->where('experiences.title', 'like', '%' . $search . '%')
                // ->orderByRaw('case when experiences.title LIKE "' . $search . '%" then 1 else 2 end')
                // ->orderBy('experiences.recomended', 'ASC')
                ->paginate(30);

            // $content = Memo::select(['experiences.id', 'experiences.title', 'experiences.description', 'experiences.image'])->where('experiences.title', 'like', '%' . $search . '%')->orderBy('experiences.recomended', 'ASC')->paginate(30);
        } else {
            // $s = DB::table('seen_memo')->select('memos')->where('user_id', '=', $user->id)->first();
            // $seen_list = 0;
            // if (!empty($s) && !empty($s->memos)) {
            //     $seen_list = $s->memos;
            // }

            //get fav cat
            // $fc = DB::table('folder_wishlists')->selectRaw("GROUP_CONCAT(secondary_id,'') as fav_cat")->where('user_id', '=', $user->id)->first();
            // $fav_cat = 0;
            // if (!empty($fc) && !empty($fc->fav_cat)) {
            //     $fav_cat = $fc->fav_cat;
            // }

            //content -  this query have contact rated & global rated while orderby issue increase time
            //$content = DB::table('experiences')->selectRaw("id, title, description, image, IF(`mf_experiences`.`id` IN (".$seen_list."), '1', '0') as seen,(SELECT count(*) FROM `mf_user_contact` WHERE `user_id` = ".$user->id." AND `user_id` != `contact_user_id` AND `contact_user_id` IN (SELECT `user_id` FROM `mf_post_ratings` WHERE `post_id` = `mf_experiences`.`id`)) as contacts_rated, (SELECT COUNT(*) FROM `mf_post_ratings` WHERE `post_id` = `mf_experiences`.`id`) as global_rated , IF (`mf_experiences`.`category_id` IN (".$fav_cat."), '1','0') as fav_cat, recomended")->orderBy('seen','asc')->orderBy('seen','asc')->orderBy('contacts_rated','desc')->orderByRaw('(CASE WHEN contacts_rated = 0 THEN global_rated ELSE contacts_rated END) DESC')->orderBy('fav_cat','desc')->orderBy('recomended','asc')->orderBy('title','asc')->orderBy('description','asc')->orderBy('priority_key','asc')->paginate(30);

            //get recommended memo algo in default search
            // $content = DB::table('experiences')->selectRaw("id, title, description, image, recomended, IF(`mf_experiences`.`id` IN (" . $seen_list . "), '1', '0') as seen, IF (`mf_experiences`.`category_id` IN (" . $fav_cat . "), '1','0') as fav_cat, (SELECT COUNT(*) FROM `mf_post_ratings` WHERE `post_id` = `mf_experiences`.`id`) as global_rated, (SELECT count(*) FROM `mf_user_contact` WHERE `user_id` = " . $user->id . " AND `user_id` != `contact_user_id` AND `contact_user_id` IN (SELECT `user_id` FROM `mf_post_ratings` WHERE `post_id` = `mf_experiences`.`id`)) as contacts_rated ")->orderBy('seen', 'asc')->orderBy('fav_cat', 'desc')->orderBy('recomended', 'asc')->orderBy('title', 'asc')->orderBy('description', 'asc')->orderBy('priority_key', 'asc')->paginate(30);
            $content = [];
        }
        if ($content) {
            foreach ($content as  $row) {
                $row->image = $this->getFolderImageUrl($row->image, 'icon');
                $row->totalExp = $this->countExpercinaces($row->id);
            }
        }

        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }

    // RECOMMENDED MEMO
    public function memo_list(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        // $secondary_id = (empty($request->secondary_id)) ? 0 : $request->secondary_id;


        // if ($secondary_id != 0) {
        //get all experienced memo list
        $s = DB::table('post_ratings')
            ->selectRaw("GROUP_CONCAT(post_id,'') as memos")
            // ->where('user_id', '=', $user->id)
            ->where('rating', '>', 0)
            ->orderBy('rating', 'desc')
            ->first();
        $memos = 0;
        if (!empty($s) && (!empty($s->memos))) {
            $memos = explode(",",$s->memos);
        }
        // print_r($memos);die;
        //get memo against secondary_id
        $content = Memo::selectRaw("id, title, description, image, top, recomended")
            // ->where(['category_id' => $secondary_id, 'status' => 'Y'])
            ->where(['status' => 'Y'])
            ->whereIn('id', $memos)
            // ->whereIn('id', [8770,6889,6821,1343,8777,8873,33414,2482,2485,6363,969])

            // ->orderBy('exp', 'ASC')
            // ->orderBy('top', 'ASC')
            // ->orderBy('recomended', 'ASC')
            ->orderBy('title', 'ASC')
        //     ->orderByRaw(
        //         "CASE WHEN in IN (".$memos.") THEN 1 ELSE 0 END DESC"
        //    )
            ->paginate(20);

        // print_r($content);die;
        // } else {
        //get seen memo_list
        // $s = DB::table('seen_memo')->select('memos')->where('user_id', '=', $user->id)->first();
        // $seen_list = 0;
        // if (!empty($s) && (!empty($s->memos))) {
        //     $seen_list = $s->memos;
        // }
        //get fav cat
        // $fc = DB::table('folder_wishlists')->selectRaw("GROUP_CONCAT(secondary_id,'') as fav_cat")->where('user_id', '=', $user->id)->first();
        // $fav_cat = 0;
        // if (!empty($fc) && !empty($fc->fav_cat)) {
        //     $fav_cat = $fc->fav_cat;
        // }

        //get recommended memo
        // $content = DB::table('experiences')->selectRaw("id, title, description, image, IF(`mf_experiences`.`id` IN (".$seen_list."), '1', '0') as seen,(SELECT count(*) FROM `mf_user_contact` WHERE `user_id` = ".$user->id." AND `user_id` != `contact_user_id` AND `contact_user_id` IN (SELECT `user_id` FROM `mf_post_ratings` WHERE `post_id` = `mf_experiences`.`id`)) as contacts_rated, (SELECT COUNT(*) FROM `mf_post_ratings` WHERE `post_id` = `mf_experiences`.`id`) as global_rated , IF (`mf_experiences`.`category_id` IN (".$fav_cat."), '1','0') as fav_cat, recomended")->orderBy('seen','asc')->orderBy('seen','asc')->orderBy('contacts_rated','desc')->orderByRaw('(CASE WHEN contacts_rated = 0 THEN global_rated ELSE contacts_rated END) DESC')->orderBy('fav_cat','desc')->orderBy('recomended','asc')->orderBy('title','asc')->orderBy('description','asc')->orderBy('priority_key','asc')->paginate(20);


        // $content = DB::table('experiences')
        //     ->selectRaw("id, title, description, image, IF(`mf_experiences`.`id` IN (" . $seen_list . "), '1', '0') as seen, recomended")
        //     ->orderBy('seen', 'asc')
        //     ->orderBy('recomended', 'asc')
        //     ->orderBy('title', 'asc')
        //     ->orderBy('description', 'asc')
        //     ->orderBy('priority_key', 'asc')
        //     ->paginate(20);

        // $content = DB::table('experiences')
        //     ->selectRaw("id, title, description, image, IF(`mf_experiences`.`id` IN (" . $seen_list . "), '1', '0') as seen, IF (`mf_experiences`.`category_id` IN (" . $fav_cat . "), '1','0') as fav_cat, recomended")
        // ->orderBy('seen', 'asc')
        // ->orderBy('seen', 'asc')
        // ->orderBy('fav_cat', 'desc')
        // ->orderBy('recomended', 'asc')
        // ->orderBy('title', 'asc')
        // ->orderBy('description', 'asc')
        //         ->orderBy('priority_key', 'asc')
        //         ->paginate(20);
        // }



        foreach ($content as  $row) {

            if ($row->image != '' && $row->image != null) {
                $row->image = $this->getFolderImageUrl($row->image, 'icon');
            }
            //for public users list
            $users =  $this->getUserList($row->id, $user->id, '3', '4');

            foreach ($users as $key => $value) {
                if ($value->image != '' && $value->image != null) {
                    $value->image = asset('public/images/' . $value->image);
                } else {
                    $value->image = null;
                }
                $value->name = $this->getContactName($user->id, $value->id, $value->name);
                $value->type = $this->getUserType($user->id, $value->id,);
                //$value->user_name = $this->getContactName($user->id,$value->id,$value->user_name);
                //$rating = rand(3,9);
                //$value->rating = $this->meRating($row->id,$user->id);
            }

            $row->users = $users;

            //gallery images

            $gallery_data = PostImage::select(['post_images.image'])->join('posts', 'post_images.post_id', '=', 'posts.id')->where(['posts.exp_id' => $row->id])->whereIn('posts.share_with', [3])->orderBy('post_images.updated_at', 'DESC')->take(8)->get();

            foreach ($gallery_data as $key => $value) {
                $value->image = $this->getImageUrl($value->image);
            }

            $row->gallery_data = $gallery_data;


            $row->all = $this->getRating($row->id);
            $row->known = $this->getKnownRating($row->id, $user->id);
            $row->close = $this->getCloseoneRating($row->id, $user->id);
            $row->me = $this->meRating($row->id, $user->id);
            $row->average_rating = $this->getRating($row->id);
            $row->wish = $this->checkWishlistMemo($row->id, $user->id);
            $row->exp = $this->checkExperienceMemo($row->id, $user->id);
            $row->totalExp = $this->countExpercinaces($row->id);
        }
        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }

    public function listRatedUser(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }
        $memo_id = (empty($request->id)) ? '' : $request->id;
        $type = (empty($request->type)) ? '3' : $request->type;

        $users = $this->getUserList($memo_id, $user->id, $type);

        //$users = User::select('id','name','username','image')->where('id','!=',$user->id)->inRandomOrder()->limit(5)->get();

        foreach ($users as $u) {
            if ($u->image !== '' && $u->image != null) {
                $u->image =  asset('public/images/' . $u->image);
            } else {
                $u->image = null;
            }
            $u->name = $this->getContactName($user->id, $u->id, $u->name);
            $u->type = $this->getUserType($user->id, $u->id);
            // $u->rating = rand(6,9);
        }

        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $users
        ], 200);
    }


    public function markMemoSeen(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;

        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }

        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        $memo_id = (empty($request->id)) ? '' : $request->id;
        $seen_list = $memo_id;

        $s = DB::table('seen_memo')->select('memos')->where('user_id', '=', $user->id)->first();
        if (!empty($s)) {
            $seen_list_array = explode(',', $s->memos);
            if (!in_array($memo_id, $seen_list_array, true)) {
                array_push($seen_list_array, $memo_id);
            }
            $seen_list = implode(',', $seen_list_array);
        }

        SeenMemo::updateOrcreate(['user_id' => $user->id], ['memos' => $seen_list]);
        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $seen_list
        ], 200);
    }


    public function addRecapture(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'memo_id' => '',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
            ], 401);
        }

        $text = $request->text;
        $exp_on = $request->exp_on;

        $share_with = $request->share_with;
        $memo_id = json_decode($request->memo_id);

        $primary = array();
        $secondary_folder = array();
        // $primary_folder = json_decode($request->primary_folder);
        // foreach ($primary_folder as $key => $value) {
        //    $secondary_folder = array_merge($secondary_folder,$value->secondary_id);
        // }
        // $secondary_id = implode(",",$secondary_folder);
        // $primary = implode(',', array_column($primary_folder, 'id'));
        $exp_id = implode(',', array_column($memo_id, 'id'));
        $pid = 0;
        $data = array();
        //print_r($request->memo_id);die;

        //echo json_encode($request->input());

        if (!empty($request->text) || !empty(request('image'))) {
            $data = Post::create([
                'user_id' => $user->id,
                'text' => $text,
                'exp_id' => $exp_id,
                'exp_on' => $exp_on,
                'primary_folder' => '',
                'secondary_folder' => '',
                'share_with' => $share_with
            ]);

            $post_id = $data->id;
            $pid = $data->id;
            if (isset($post_id)) {
                if (!empty(request('image'))) {
                    foreach (request('image') as $key => $value) {
                        if (!empty(request('image')[$key])) {
                            $postImage[$key] = time() . '_' . $key . '.' . request()->image[$key]->getClientOriginalExtension();
                            request()->image[$key]->move(public_path('uploads/images/'), $postImage[$key]);
                        } else {
                            $postImage[$key] = '';
                        }

                        PostImage::create([
                            'post_id' => $post_id,
                            'image' => $postImage[$key],
                        ]);
                    }
                }
            }
        }


        // $title = $user->username;
        if (isset($exp_id)) {

            $getMemo = DB::table('experiences')
                ->select('experiences.id', 'experiences.title', 'experiences.description', 'exp_categories.category_name')
                ->join('exp_categories', 'exp_categories.id', '=', 'experiences.category_id')
                ->where('experiences.id', $exp_id)
                ->first();


            // $getMemo = Memo::with(['mf_exp_categories'])
            //     ->select(['id', 'title', 'description'])
            //     ->where(['id' => $exp_id])
            //     ->first();

            if (isset($getMemo->id)) {
                $body = 'rated "' . $getMemo->title . ' (' . $getMemo->category_name . ')"';
            } else {
                $body = 'rated an experience.';
            }
        } else {
            $body = 'rated an experience.';
        }

        foreach ($memo_id as $key => $value) {
            PostRating::updateOrcreate([
                'user_id' => $user->id,
                'post_id' => $value->id,
                'share_with' => $share_with
            ], [
                'rating' => $value->rate,
                'pid' => $pid
            ]);

            //send notification
            if ($value->rate > 0 && ($value->rate < 4 || $value->rate > 7) && $share_with != '0') {
                $contacts = $this->getMycontact($user->id);
                if (!empty($contacts)) {
                    foreach ($contacts as $cn) {
                        //share_with groups
                        $share_ids = $this->getMyGroups($cn->contact_user_id, $user->id);
                        $groups = explode(",", $share_ids);
                        if (in_array($share_with, $groups)) {
                            $loginDetail = UserLogin::where('user_id', '!=', $user->id)->where('user_id', '=', $cn->contact_user_id)->get();
                            if (!empty($loginDetail)) {
                                foreach ($loginDetail as $l) {

                                    $title = $this->getContactName($l->user_id, $user->id, $user->username);
                                    $sendData = array(
                                        'body' => $body,
                                        'title' => $title,
                                        'sound' => 'Default',
                                        'data' => array(
                                            'Type' => 'rated_an_experience',
                                            'user' => $user,
                                            'memoDetails' => $memo_id
                                        ),
                                        'image' => $user->image
                                    );
                                    $this->fcmNotification($l->deviceToken, $sendData);
                                    Notification::create(['user_id' => $user->id, 'notification' => json_encode($sendData), 'r_userid' => $l->user_id, 'post_id' => $post_id, 'read_at' => $share_ids]);
                                }
                            }
                        }
                    }
                }
            }
        }

        if (!empty($data)) {
            $image = PostImage::where(['post_id' => $data->id])->get();
            $myRecation = PostReact::where(['post_id' => $data->id, 'user_id' => $user->id])->first();
            if ($myRecation) {
                $data->myRecation = $myRecation->reactType;
            } else {
                $data->myRecation = -1;
            }

            foreach ($image as $key => $value) {
                $value->image = $this->getImageUrl($value->image);
            }

            $data->images = $image;

            $wish = PostWishlist::where(['post_id' => $data->id, 'user_id' => $user->id])->first();
            $data->wish = false;
            if ($wish) {
                $data->wish = true;
            }
            $data->exp = false;

            $memo = array();
            if (!empty($data->exp_id)) {
                $aa = explode(',', $data->exp_id);
                $memo = Memo::select(['id', 'title', 'image'])->whereIn('id', $aa)->get();
            }

            // dd($memo);
            foreach ($memo as $key => $r) {
                $r->image = $this->getFolderImageUrl($r->image, $url = 'icon');
                $r->rating = $this->meRating($r->id, $user->id);
            }

            $data->memos = $memo;
        }
        return response()->json([
            'result' => 'success',
            'message' => 'Recapture',
            'content' => $data
        ], 200);
    }

    public function memorate(Request $request)
    {

        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'memo_id' => 'required',
            'rating' => 'required',

        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }
        $post_id = $request->memo_id;
        $user_id = $user->id;
        $rating = $request->rating;
        $share_with = $request->share_with;
        $exp_on = $request->exp_on;
        $primary = '';
        $secondary_folder = array();
        $secondary_id = '';
        $pid = 0;
        if (!empty($request->savedIN)) {
            $savedIN = json_decode($request->savedIN);

            foreach ($savedIN as $key => $value) {
                $secondary_folder = array_merge($secondary_folder, $value->secondary_id);
            }
            $secondary_id = implode(",", $secondary_folder);
            $primary = implode(',', array_column($savedIN, 'id'));
        }

        PostRating::updateOrcreate([
            'user_id' => $user_id,
            'post_id' => $post_id
        ], [
            'rating' => $rating,
            'exp_on' => $exp_on,
            'share_with' => $share_with,
            'primary_folder' => $primary,
            'secondary_folder' => $secondary_id,
            'pid' => $pid
        ]);

        //$title = $user->username;

        $getMemo = DB::table('experiences')
            ->select('experiences.id', 'experiences.title', 'experiences.description', 'exp_categories.category_name')
            ->join('exp_categories', 'exp_categories.id', '=', 'experiences.category_id')
            ->where('experiences.id', $post_id)
            ->first();

        // $getMemo = Memo::select(['id', 'title', 'description'])->where(['id' => $post_id])->first();

        if (isset($getMemo->id)) {
            $body = 'rated "' . $getMemo->title . ' (' . $getMemo->category_name . ')"';
        } else {
            $body = 'rated an experience.';
        }


        //send notification
        if ($rating > 0) {
            // if ($rating > 0 && ($rating < 4 || $rating > 7) && $share_with != '0') {
            $contacts = $this->getMycontact($user->id);
            if (!empty($contacts)) {
                foreach ($contacts as $cn) {
                    //share_with groups
                    $share_ids = $this->getMyGroups($cn->contact_user_id, $user->id);
                    $groups = explode(",", $share_ids);
                    if (in_array($share_with, $groups)) {
                        $loginDetail = UserLogin::where('user_id', '!=', $user->id)->where('user_id', '=', $cn->contact_user_id)->get();
                        if (!empty($loginDetail)) {
                            foreach ($loginDetail as $l) {
                                $title = $this->getContactName($l->user_id, $user->id, $user->username);
                                $sendData = array(
                                    'body' => $body,
                                    'title' => $title,
                                    'sound' => 'Default',
                                    'data' => array(
                                        'Type' => 'rated_an_experience',
                                        'user' => $user,
                                        'memoDetails' => $request->memo_id
                                    ),
                                    'image' => $user->image
                                );
                                $this->fcmNotification($l->deviceToken, $sendData);
                                Notification::create([
                                    'user_id' => $user->id,
                                    'notification' => json_encode($sendData),
                                    'r_userid' => $l->user_id,
                                    'post_id' => $post_id,
                                    'read_at' => $share_ids
                                ]);
                            }
                        }
                    }
                }
            }
        }


        $msg  = 'Update Rating';
        return response()->json([
            'result' => 'success',
            'message' => $msg,
        ], 200);
    }


    public function time_line(Request $request)
    {

        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $memo = array();

        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }

        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        $content = DB::table('post_details')->whereRaw('(`share_with` = 3) OR (`share_with` = 1 AND `user_id` IN (SELECT `contact_user_id` from `mf_user_contact` where `user_id` = "' . $user->id . '")) OR (`share_with` IN (SELECT `group_id` FROM `mf_members_by_group` WHERE `created_by` = `mf_post_details`.`user_id` AND `contact_user_id` =  "' . $user->id . '")) OR (`share_with` IN (1,2,3) AND `user_id` = "' . $user->id . '")  OR (`share_with` = 0 AND `user_id` = "' . $user->id . '")')->latest()->paginate(20);

        //$member_ids = DB::table('user_contact')->select('id')->where('contact_user_id','=',$user->id)->get();
        //dd(DB::getQueryLog());
        foreach ($content as $key =>  $row) {

            if ($row->share_with == 0 && $user->id != $row->user_id) {
                unset($content->data->$key);
            } else {

                $image = PostImage::where(['post_id' => $row->id])->get();
                $myRecation = PostReact::where(['post_id' => $row->id, 'user_id' => $user->id])->first();

                if ($myRecation) {
                    $row->myRecation = $myRecation->reactType;
                } else {
                    $row->myRecation = -1;
                }

                foreach ($image as $key => $value) {
                    $value->image = $this->getImageUrl($value->image);
                }

                $row->images = $image;
                if ($row->user_image !== '' && $row->user_image != null) {
                    $row->user_image =  asset('public/images/' . $row->user_image);
                } else {
                    $row->user_image = null;
                }

                $wish = PostWishlist::where(['post_id' => $row->id, 'user_id' => $user->id])->first();
                $row->wish = false;
                if ($wish) {
                    $row->wish = true;
                }

                $row->exp = false;
                $memo = array();

                if (!empty($row->exp_id)) {
                    $aa = explode(',', $row->exp_id);
                    $memo = Memo::select(['id', 'title', 'image'])->whereIn('id', $aa)->get();
                }

                // dd($memo);
                foreach ($memo as $key => $r) {
                    $r->image = $this->getFolderImageUrl($r->image, $url = 'icon');
                    $r->rating = $this->getRating($r->id);
                    // $r->rating = $this->meRating($r->id, $user->id);
                    // echo $r->id . '<>' .  $user->id.'<br>';
                }
                $row->memos = $memo;
                $row->user_name = $this->getContactName($user->id, $row->user_id, $row->user_name);
            }
        }

        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content,
            'member_ids' => array()
        ], 200);
    }


    public function singlePost(Request $request)
    {

        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'post_id'
        ]);
        $content = array();
        $memo = array();

        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        $post_id = (!empty($request->post_id)) ? $request->post_id : '';

        $content = DB::table('post_details')->where('id', $post_id)->first();

        //$member_ids = DB::table('user_contact')->select('id')->where('contact_user_id','=',$user->id)->get();
        //dd(DB::getQueryLog());
        //foreach ($content as  $row) {

        if (!empty($content)) {
            $image = PostImage::where(['post_id' => $post_id])->get();
            $myRecation = PostReact::where(['post_id' => $post_id, 'user_id' => $user->id])->first();
            if ($myRecation) {
                $content->myRecation = $myRecation->reactType;
            } else {
                $content->myRecation = -1;
            }

            foreach ($image as $key => $value) {
                $value->image = $this->getImageUrl($value->image);
            }
            $content->images = $image;

            if ($content->user_image !== '' && $content->user_image != null) {
                $content->user_image =  asset('public/images/' . $content->user_image);
            } else {
                $content->user_image = null;
            }

            $wish = PostWishlist::where(['post_id' => $post_id, 'user_id' => $user->id])->first();
            $content->wish = false;
            if ($wish) {
                $content->wish = true;
            }
            $content->exp = false;

            $memo = array();
            if (!empty($content->exp_id)) {
                $aa = explode(',', $content->exp_id);
                $memo = Memo::select(['id', 'title', 'image'])->whereIn('id', $aa)->get();
            }

            foreach ($memo as $key => $r) {
                $r->image = $this->getFolderImageUrl($r->image, $url = 'icon');
                $r->rating = $this->meRating($r->id, $user->id);
            }

            $content->memos = $memo;
            $content->user_name = $this->getContactName($user->id, $content->user_id, $content->user_name);
        }


        //}

        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }

    public function addCommment(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'comment' => 'required',
            'post_id' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
            ], 401);
        }
        $comment = $request->comment;
        $post_id = $request->post_id;

        if ($user->image !== '' && $user->image != null) {
            $user->image =  asset('public/images/' . $user->image);
        }

        PostComment::create([
            'user_id' => $user->id,
            'post_id' => $post_id,
            'comment' => $comment
        ]);

        //get post user_id
        $post = PostDetail::find($post_id);
        if (!empty($post)) {
            //$title = $user->username;
            $body = 'Commented on your post';
            //$type = 'comment';
            if ($post->user_id != $user->id) {
                $loginDetail = UserLogin::where(['user_id' => $post->user_id])->get();
                if (!empty($loginDetail)) {
                    foreach ($loginDetail as $l) {
                        $title = $this->getContactName($l->user_id, $user->id, $user->username);
                        $sendData = array(
                            'body' => $body,
                            'title' => $title,
                            'sound' => 'Default',
                            'data' => array(
                                'Type' => 'comment',
                                'user' => $user,
                                'post' => $post
                            ),
                            'image' => $user->image
                        );
                        $this->fcmNotification($l->deviceToken, $sendData);
                    }
                }
            }
        }
        return response()->json([
            'result' => 'success',
            'message' => 'comment Added',
        ], 200);
    }


    public function deleteCommment(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'comment_id' => 'required',
        ]);

        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
            ], 401);
        }

        $PostComment = PostComment::find($request->comment_id);
        if ($PostComment) {
            // $destroy = PostComment::destroy(2);
            $PostComment->delete();
        }

        if ($PostComment) {

            $data = [
                'result' => 'success',
                'message' => 'Comment deleted'
            ];
        } else {

            $data = [
                'result' => 'failure',
                'message' => 'Comment not deleted'
            ];
        }

        return response()->json($data, 200);
    }

    public function addReact(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'react_type' => 'required',
            'post_id' => 'required',

        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
            ], 401);
        }

        if ($user->image !== '' && $user->image != null) {
            $user->image =  asset('public/images/' . $user->image);
        }

        $react_type = $request->react_type;
        $post_id = $request->post_id;
        PostReact::UpdateOrCreate(
            [
                'user_id' => $user->id,
                'post_id' => $post_id
            ],
            [
                'reactType' => $react_type
            ]
        );

        //get post user_id
        $post = PostDetail::find($post_id);
        if (!empty($post)) {
            //$title = $user->username;
            $body = 'Liked your post';
            //$type = 'comment';
            if ($post->user_id != $user->id) {
                $loginDetail = UserLogin::where(['user_id' => $post->user_id])->get();
                if (!empty($loginDetail)) {
                    foreach ($loginDetail as $l) {
                        $title = $this->getContactName($l->user_id, $user->id, $user->username);
                        $sendData = array(
                            'body' => $body,
                            'title' => $title,
                            'sound' => 'Default',
                            'data' => array(
                                'Type' => 'reaction',
                                'user' => $user,
                                'post' => $post
                            ),
                            'image' => $user->image
                        );
                        if ($react_type != -1) {
                            $this->fcmNotification($l->deviceToken, $sendData);
                        } else {
                            // $this->fcmNotification($l->deviceToken, $sendData);
                        }
                    }
                }
            }
        }
        return response()->json([
            'result' => 'success',
            'message' => 'Reacted',
        ], 200);
    }

    public function listComReact(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'post_id' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
            ], 401);
        }

        $post_id = $request->post_id;
        $post_react = PostReact::select(['post_reacts.*', 'users.name', 'users.image'])->join('users', 'post_reacts.user_id', '=', 'users.id')->where(['post_reacts.post_id' => $post_id])->where('post_reacts.reactType', '!=', '-1')->get();
        $post_com = PostComment::select(['post_comments.*', 'users.name', 'users.image'])->join('users', 'post_comments.user_id', '=', 'users.id')->where(['post_comments.post_id' => $post_id])->get();
        $userdata = array();
        foreach ($post_react as $key =>  $row) {
            if ($row->image != '' && $row->image != null) {
                $row->image = asset('public/images/' . $row->image);
            } else {
                $row->image = null;
            }
            $row->name = $this->getContactName($user->id, $row->user_id, $row->name);
        }

        foreach ($post_com as $key =>  $row) {
            if ($row->image != '' && $row->image != null) {
                $row->image = asset('public/images/' . $row->image);
            } else {
                $row->image = null;
            }
            $row->name = $this->getContactName($user->id, $row->user_id, $row->name);
        }

        $content = [
            'post_react' => $post_react,
            'post_com' => $post_com
        ];

        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }

    //wishlist post
    public function wishlist(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'post_id' => 'required'
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }
        $post_id = $request->post_id;
        $user_id = $user->id;
        $primary_id = (empty($request->primary_id)) ? 0 : $request->primary_id;
        $secondary_id = (empty($request->secondary_id)) ? 0 : $request->secondary_id;
        $data = PostWishlist::where(['user_id' => $user_id, 'post_id' => $post_id])->first();
        $post = PostDetail::find($post_id);
        if (empty($data)) {
            PostWishlist::updateOrcreate([
                'user_id' => $user_id,
                'post_id' => $post_id,
                'primary_id' => $primary_id,
                'secondary_id' => $secondary_id
            ], []);

            if (!empty($post->exp_id)) {
                $memo_list =  explode(',', $post->exp_id);
                if (!empty($memo_list)) {
                    foreach ($memo_list as $memo_id) {
                        MemoWishlist::updateOrcreate([
                            'user_id' => $user_id,
                            'memo_id' => $memo_id,
                            'primary_id' => $primary_id,
                            'secondary_id' => $secondary_id
                        ], []);
                    }
                }
            }

            $msg  = 'Added Wishlist';
        } else {
            PostWishlist::where(['post_id' => $post_id, 'user_id' => $user_id])->delete();
            if (!empty($post->exp_id)) {
                $memo_list =  explode(',', $post->exp_id);
                if (!empty($memo_list)) {
                    foreach ($memo_list as $memo_id) {
                        MemoWishlist::where(['memo_id' => $memo_id, 'user_id' => $user_id])->delete();
                    }
                }
            }

            $msg  = 'Remove Wishlist';
        }
        return response()->json([
            'result' => 'success',
            'message' => $msg,
        ], 200);
    }

    //wishlist memo
    public function wishlist_memo(Request $request)
    {

        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'memo_id' => 'required'
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }
        $memo_id = $request->memo_id;
        $user_id = $user->id;
        $primary_id = (empty($request->primary_id)) ? 1 : $request->primary_id;
        $secondary_id = (empty($request->secondary_id)) ? 1 : $request->secondary_id;
        $data = MemoWishlist::where(['user_id' => $user_id, 'memo_id' => $memo_id])->first();
        if (empty($data)) {
            MemoWishlist::updateOrcreate([
                'user_id' => $user_id,
                'memo_id' => $memo_id,
                'primary_id' => $primary_id,
                'secondary_id' => $secondary_id
            ], []);
            $msg  = 'Added Wishlist';
        } else {
            MemoWishlist::where(['memo_id' => $memo_id, 'user_id' => $user_id])->delete();
            $msg  = 'Remove Wishlist';
        }
        return response()->json([
            'result' => 'success',
            'message' => $msg,
        ], 200);
    }

    //User Profile
    public function profile(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'user' => $user
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        $user->slug = strtoupper($user->slug);
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'user' => $user
            ], 401);
        }
        $total_rated_memo = PostRating::where(['user_id' => $user->id])->count();

        return response()->json([
            'result' => 'success',
            'message' => '',
            'user' => $user,
            'total_rated_memo' => ($total_rated_memo) ? $total_rated_memo : '0'
        ], 200);
    }

    //User Detail
    public function userDetails(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $data = array();

        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }


        if ($user->image !== '' && $user->image != null) {
            $user->image =  asset('public/images/' . $user->image);
        } else {
            $user->image = null;
        }

        $friends = MasterContact::select(['id'])->where(['user_id' => $user->id])->count();

        $total_rated_memo = PostRating::where(['user_id' => $user->id])->count();

        $user->total_rated_memo = ($total_rated_memo) ? $total_rated_memo : '0';

        $rated_memo = DB::table('exp_cat')->select(['exp_cat.id', 'exp_cat.category_id', 'exp_cat.title', 'exp_cat.description', 'exp_cat.image', 'exp_cat.rating', 'post_ratings.post_id'])->join('post_ratings', 'exp_cat.id', '=', 'post_ratings.post_id')->where(['post_ratings.user_id' => $user->id])->orderBy('post_ratings.updated_at', 'DESC')->get();

        foreach ($rated_memo as $value) {
            $value->all = $this->getRating($value->id);
            $value->known = $this->getKnownRating($value->id, $user->id);
            $value->close = $this->getCloseoneRating($value->id, $user->id);
            $value->me = $this->meRating($value->id, $user->id);
            $value->totalExp = $this->countExpercinaces($value->id);
            $value->image = $this->getFolderImageUrl($value->image, 'icon');

            // $catDetails = ExpCategory::select(['id', 'category_name', 'type', 'icon'])->where(['id' => $value->category_id, 'status' => 'Y'])->first();
            // $value->category_name = $catDetails->category_name;

            $catDetails = ExpCategory::select(['id', 'category_name', 'type', 'icon'])->where(['status' => 'Y'])->whereIn('id', [$value->category_id])->get();

            $value->category = $catDetails;
            $catDetails = [];
            unset($value->category_id);
        }

        $data = PostImage::select(['post_images.image'])->join('posts', 'post_images.post_id', '=', 'posts.id')->where(['posts.user_id' => $user->id])->orderBy('post_images.updated_at', 'DESC')->take(8)->get();
        foreach ($data as $key => $value) {
            $value->image = $this->getImageUrl($value->image);
        }
        $content = [
            'friends' => $friends,
            'rated_memo' => $rated_memo,
            'gallery' => $data
        ];
        $total_contacts = MasterContact::where(['master_contacts.user_id' => $user->id])->count();


        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content,
            'user' => $user,
            'total_contacts' => $total_contacts,
            // 'total_rated_memo' => $total_rated_memo
        ], 200);
    }

    //User Detail
    public function userCategoryDetails(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $data = array();

        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }

        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        if ($user->image !== '' && $user->image != null) {
            $user->image =  asset('public/images/' . $user->image);
        } else {
            $user->image = null;
        }

        // $friends = MasterContact::select(['id'])->where(['user_id' => $user->id])->count();

        // $total_rated_memo = PostRating::where(['user_id' => $user->id])->count();

        // $user->total_rated_memo = ($total_rated_memo) ? $total_rated_memo : '0';

        $rated_memo = DB::table('exp_cat')->select(['exp_cat.category_id'])->join('post_ratings', 'exp_cat.id', '=', 'post_ratings.post_id')->where(['post_ratings.user_id' => $user->id])->groupBy('exp_cat.category_id')->orderBy('post_ratings.updated_at', 'DESC')->get();

        foreach ($rated_memo as $value) {
            // $value->all = $this->getRating($value->id);
            // $value->known = $this->getKnownRating($value->id, $user->id);
            // $value->close = $this->getCloseoneRating($value->id, $user->id);
            // $value->me = $this->meRating($value->id, $user->id);
            // $value->totalExp = $this->countExpercinaces($value->id);
            // $value->image = $this->getFolderImageUrl($value->image, 'icon');

            $catDetails = ExpCategory::select(['id', 'category_name', 'type', 'icon'])->where(['id' => $value->category_id, 'status' => 'Y'])->first();
            $value->category_name = $catDetails->category_name;
        }

        // $data = PostImage::select(['post_images.image'])->join('posts', 'post_images.post_id', '=', 'posts.id')->where(['posts.user_id' => $user->id])->orderBy('post_images.updated_at', 'DESC')->take(8)->get();
        // foreach ($data as $key => $value) {
        //     $value->image = $this->getImageUrl($value->image);
        // }
        $content = [
            // 'friends' => $friends,
            'rated_memo' => $rated_memo,
            // 'gallery' => $data
        ];
        // $total_contacts = MasterContact::where(['master_contacts.user_id' => $user->id])->count();


        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content,
            'user' => $user,
            // 'total_contacts' => $total_contacts,
            // 'total_rated_memo' => $total_rated_memo
        ], 200);
    }

    //dark mode toggle
    public function darkModeToggle(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'status' => 'required|in:true,false',
        ]);
        $content = array();

        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }


        // if ($user->dark_mode !== '' && $user->dark_mode != null) {
        $user->dark_mode =  $request->status;
        // } else {
        //     $user->dark_mode = false;
        // }
        $user->save();


        return response()->json([
            'result' => 'success',
            'message' => 'Status updated!',
            'dark_mode_status' => $request->status,
        ], 200);
    }

    //dark mode toggle
    public function deleteUser(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'phone' => 'required'
        ]);
        $content = array();

        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => 'User not found!',
                'content' => $content
            ], 401);
        }

        $user = User::where(['phone' => $request->phone])->first();
        //delete all user activities
        MasterContact::where(['user_id' => $user->id])->delete();
        UserLogin::where(['user_id' => $user->id])->delete();
        UserOtp::where(['mobile' =>  $user->phone])->delete();
        $post = Post::where(['user_id' => $user->id])->get();
        foreach ($post as $key => $value) {
            PostImage::where('post_id', $value->id)->delete();
            PostComment::where('post_id', $value->id)->delete();
            PostReact::where('post_id', $value->id)->delete();
            PostWishlist::where('post_id', $value->id)->delete();
            PostRating::where('pid', $value->id)->delete();
            Post::where(['id' => $value->id])->delete();
        }

        $user->delete();

        return response()->json([
            'result' => 'success',
            'message' => 'User deleted with its all activities!',
            'status' => 1
        ], 200);
    }

    //GET WISHLIST POST
    public function wishlistPost(Request $request)
    {

        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $memo = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        if (!empty($request->primary_id) && $request->primary_id != 0) {
            $content = DB::table('post_details')->select('post_details.*')->join('post_wishlists', 'post_wishlists.post_id', '=', 'post_details.id')->where(['post_wishlists.user_id' => $user->id, 'post_wishlists.primary_id' => $request->primary_id])->orderBy('post_wishlists.updated_at', 'DESC')->paginate(30);
            if (!empty($request->secondary_id) && $request->secondary_id != 0) {
                $content = DB::table('post_details')->select('post_details.*')->join('post_wishlists', 'post_wishlists.post_id', '=', 'post_details.id')->where(['post_wishlists.user_id' => $user->id, 'post_wishlists.secondary_id' => $request->secondary_id])->orderBy('post_wishlists.updated_at', 'DESC')->paginate(30);
            }
        } else {
            $content = DB::table('post_details')->select('post_details.*')->join('post_wishlists', 'post_wishlists.post_id', '=', 'post_details.id')->where(['post_wishlists.user_id' => $user->id])->orderBy('post_wishlists.updated_at', 'DESC')->paginate(30);
        }



        foreach ($content as  $row) {
            $image = PostImage::where(['post_id' => $row->id])->get();
            $myRecation = PostReact::where(['post_id' => $row->id, 'user_id' => $user->id])->first();
            if ($myRecation) {
                $row->myRecation = $myRecation->reactType;
            } else {
                $row->myRecation = -1;
            }
            foreach ($image as $key => $value) {
                $value->image = $this->getImageUrl($value->image);
            }
            $row->images = $image;
            if ($row->user_image !== '' && $row->user_image != null) {
                $row->user_image =  asset('public/images/' . $row->user_image);
            } else {
                $row->user_image = null;
            }

            $row->user_name = $this->getContactName($user->id, $row->user_id, $row->user_name);
            $row->wish = true;
            $row->exp = true;
            //$row->test = $user->id.'-'.$row->user_id.'-'.$row->user_name;
            // $memo_id = explode(",", $row->exp_id);
            // $memo = DB::table('exp_cat')->select(['id','title','image','icon','rating'])->where(['id'=>$row->exp_id])->get();
            // foreach ($memo as $key => $r) {
            //   $r->image = $this->getImageUrl($r->image);
            //   $r->icon = $this->getFolderImageUrl($r->icon,$url='icon');

            // }

            $memo = array();
            if (!empty($row->exp_id)) {
                $aa = explode(',', $row->exp_id);
                $memo = Memo::select(['id', 'title', 'image'])->whereIn('id', $aa)->get();
            }

            // $memo = Memo::select(['id', 'title', 'image'])->whereIn('id', array($row->exp_id))->get();
            foreach ($memo as $key => $r) {
                $r->image = $this->getFolderImageUrl($r->image, $url = 'icon');
                // $r->rating = $this->meRating($r->id, $user->id);
                $r->rating = $this->getRating($r->id);
            }

            $row->memos = $memo;
        }

        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }


    public function wishlistMemoList(Request $request)
    {

        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $memo = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        //get experience memos
        $fc = DB::table('post_ratings')->selectRaw("GROUP_CONCAT(post_id,'') as memos")->where('user_id', '=', $user->id)->first();
        $memo_list = '0';
        if (!empty($fc)) {
            $memo_list = $fc->memos;
        }
        if (!empty($request->primary_id) && $request->primary_id != 0) {
            $content = DB::table('experiences')->join('memo_wishlists', 'memo_wishlists.memo_id', '=', 'experiences.id')->where(['memo_wishlists.user_id' => $user->id, 'memo_wishlists.primary_id' => $request->primary_id])->whereRaw('memo_id NOT IN ("' . $memo_list . '")')->orderBy('memo_wishlists.updated_at', 'DESC')->get();
            if (!empty($request->secondary_id) && $request->secondary_id != 0) {
                $content = DB::table('experiences')->join('memo_wishlists', 'memo_wishlists.memo_id', '=', 'experiences.id')->where(['memo_wishlists.user_id' => $user->id, 'memo_wishlists.secondary_id' => $request->secondary_id])->whereRaw('memo_id NOT IN ("' . $memo_list . '")')->orderBy('memo_wishlists.updated_at', 'DESC')->get();
            }
        } else {
            $content = DB::table('experiences')->join('memo_wishlists', 'memo_wishlists.memo_id', '=', 'experiences.id')->where(['memo_wishlists.user_id' => $user->id])->whereRaw('memo_id NOT IN ("' . $memo_list . '")')->orderBy('memo_wishlists.updated_at', 'DESC')->get();
        }



        foreach ($content as $key => $r) {
            $r->image = $this->getFolderImageUrl($r->image, $url = 'icon');
            $r->rating = $this->meRating($r->id, $user->id);

            $catDetails = ExpCategory::select(['id', 'category_name', 'type', 'icon'])->where(['id' => $r->category_id, 'status' => 'Y'])->first();
            $r->category_name = $catDetails->category_name;
        }

        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }

    public function wishlistMemoCategoryList(Request $request)
    {

        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $memo = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        //get experience memos
        $fc = DB::table('post_ratings')->selectRaw("GROUP_CONCAT(post_id,'') as memos")->where('user_id', '=', $user->id)->first();
        $memo_list = '0';
        if (!empty($fc)) {
            $memo_list = $fc->memos;
        }
        if (!empty($request->primary_id) && $request->primary_id != 0) {
            $content = DB::table('experiences')->join('memo_wishlists', 'memo_wishlists.memo_id', '=', 'experiences.id')->where(['memo_wishlists.user_id' => $user->id, 'memo_wishlists.primary_id' => $request->primary_id])->whereRaw('memo_id NOT IN ("' . $memo_list . '")')->orderBy('memo_wishlists.updated_at', 'DESC')->get();
            if (!empty($request->secondary_id) && $request->secondary_id != 0) {
                $content = DB::table('experiences')->join('memo_wishlists', 'memo_wishlists.memo_id', '=', 'experiences.id')->where(['memo_wishlists.user_id' => $user->id, 'memo_wishlists.secondary_id' => $request->secondary_id])->whereRaw('memo_id NOT IN ("' . $memo_list . '")')->orderBy('memo_wishlists.updated_at', 'DESC')->get();
            }
        } else {
            $content = DB::table('experiences')->join('memo_wishlists', 'memo_wishlists.memo_id', '=', 'experiences.id')->where(['memo_wishlists.user_id' => $user->id])->whereRaw('memo_id NOT IN ("' . $memo_list . '")')->orderBy('memo_wishlists.updated_at', 'DESC')->get();
        }



        foreach ($content as $key => $r) {
            // $r->image = $this->getFolderImageUrl($r->image, $url = 'icon');
            // $r->rating = $this->meRating($r->id, $user->id);
            unset($r->id);
            unset($r->parent_id);
            unset($r->priority_key);
            unset($r->title);
            unset($r->priority_key);
            unset($r->image);
            unset($r->description);
            unset($r->recomended);
            unset($r->description);
            unset($r->description);
            unset($r->description);

            $catDetails = ExpCategory::select(['id', 'category_name', 'type', 'icon'])->where(['id' => $r->category_id, 'status' => 'Y'])->first();
            $r->category_name = $catDetails->category_name;
        }

        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }

    public function getWishlistGalleryImage(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
            ], 401);
        }
        //DB::enableQueryLog();
        $data = PostImage::select(['post_images.image'])->join('posts', 'post_images.post_id', '=', 'posts.id')->join('post_wishlists', 'post_wishlists.post_id', '=', 'posts.id')->where(['post_wishlists.user_id' => $user->id])->orderBy('post_wishlists.updated_at', 'DESC')->get();
        //dd(DB::getQueryLog());
        foreach ($data as $key => $value) {
            $value->image = $this->getImageUrl($value->image);
        }
        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $data
        ], 200);
    }

    public function deleteMasterContact(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required'
        ]);

        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'status' => 0
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => 'User not found!',
                'status' => 0
            ], 401);
        }

        MasterContact::where(['user_id' => $user->id])->delete();

        return response()->json([
            'result' => 'success',
            'message' => 'All the contacts are successfully deleted!',
            'status' => 1
        ], 200);
    }

    public function addMasterContact(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'data' => 'required'
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
            ], 401);
        }

        $data = $request->data;
        $name = '';
        $numbers = '';
        $country_code = (empty($request->country_code)) ? '+91' : $request->country_code;

        //delete contacts
        MasterContact::where(['user_id' => $user->id])->delete();
        foreach ($data as $key => $value) {

            if (!empty($data[$key]['phoneNumbers']) && !empty($data[$key]['givenName'])) {

                if (!empty($data[$key]['middleName'])) {
                    $name = trim($data[$key]['givenName'] . ' ' . $data[$key]['middleName'] . ' ' . $data[$key]['familyName']);
                } else {
                    $name = trim($data[$key]['givenName'] . ' ' . $data[$key]['familyName']);
                }


                foreach ($data[$key]['phoneNumbers'] as $num) {
                    $numbers = $num['number'];

                    $numbers = str_replace(' ', '', $numbers);
                    $numbers = str_replace('(', '', $numbers);
                    $numbers = str_replace(')', '', $numbers);
                    $numbers = str_replace('-', '', $numbers);
                    $numbers = str_replace('_', '', $numbers);

                    if (substr($numbers, 0, 1) === '0') {
                        $numbers = ltrim($numbers, '0');
                    }

                    if (substr($numbers, 0, 1) === '+') {
                        $numbers = $numbers;
                    } else {
                        $numbers = $country_code . $numbers;
                    }

                    MasterContact::updateOrcreate([
                        'user_id' => $user->id,
                        'number' => preg_replace('/[\s]+/', ' ', $numbers)
                    ], ['name' => $name]);
                }
            }
        }


        $content = MasterContact::select(['master_contacts.id', 'master_contacts.name', 'master_contacts.number', 'users.id as uid'])->leftjoin('users', DB::raw('CONCAT_WS("",mf_users.country_code,mf_users.phone)'), '=', 'master_contacts.number')->where(['user_id' => $user->id])->orderByRaw('-uid DESC')->orderBy('master_contacts.name', 'ASC')->get();
        foreach ($content as $key => $value) {
            $value->memo_user = false;
            if (!empty($value->uid)) {
                $value->memo_user = true;
            }

            //get image
            $trimmed_phone = str_replace('+91', '', $value->number);
            $userDetails = User::select(['name', 'phone', 'image'])->where(['phone' => $trimmed_phone])->first();
            if (isset($userDetails) && $userDetails->image != '' && $userDetails->image != null) {
                $value->image = asset('public/images/' . $userDetails->image);
            } else {
                $value->image = null;
            }
        }

        return response()->json([
            'result' => 'success',
            'message' => 'Contact Added',
            'content' => $content
        ], 200);
    }

    public function mastercontactList(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }


        $content = MasterContact::select(['master_contacts.id', 'master_contacts.name', 'master_contacts.number', 'users.id as uid'])->leftjoin('users', DB::raw('CONCAT_WS("",mf_users.country_code,mf_users.phone)'), '=', 'master_contacts.number')->where(['user_id' => $user->id])->orderByRaw('-uid DESC')->orderBy('master_contacts.name', 'ASC')->get();
        foreach ($content as $key => $value) {

            $value->memo_user = false;
            if (!empty($value->uid)) {
                $value->memo_user = true;
                $total_rated_memo = PostRating::where(['user_id' => $value->uid])->count();
                $value->total_rated_memo = ($total_rated_memo) ? $total_rated_memo : '0';
            }

            //get image
            $trimmed_phone = str_replace('+91', '', $value->number);
            $userDetails = User::select(['name', 'phone', 'image'])->where(['phone' => $trimmed_phone])->first();
            if (isset($userDetails) && $userDetails->image != '' && $userDetails->image != null) {
                $value->image = asset('public/images/' . $userDetails->image);
            } else {
                $value->image = null;
            }
        }


        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }



    public function appContactList(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }


        $content = MasterContact::select(['master_contacts.id', 'master_contacts.name', 'master_contacts.number', 'users.id as uid'])->leftjoin('users', DB::raw('CONCAT_WS("",mf_users.country_code,mf_users.phone)'), '=', 'master_contacts.number')->where(['user_id' => $user->id])->whereNotNull('users.id')->orderByRaw('-uid DESC')->orderBy('master_contacts.name', 'ASC')->get();
        foreach ($content as $key => $value) {
            $value->memo_user = true;
            $total_rated_memo = PostRating::where(['user_id' => $value->uid])->count();
            $value->total_rated_memo = ($total_rated_memo) ? $total_rated_memo : '0';

            //get image
            $trimmed_phone = str_replace('+91', '', $value->number);
            $userDetails = User::select(['name', 'phone', 'image'])->where(['phone' => $trimmed_phone])->first();
            if (isset($userDetails) && $userDetails->image != '' && $userDetails->image != null) {
                $value->image = asset('public/images/' . $userDetails->image);
            } else {
                $value->image = null;
            }
        }


        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }

    public function createGroup(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'name' => '',
        ]);
        $content = array();
        $memo = array();

        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        $group = Group::create([
            'name' => request('name'),
            'user_id' => $user->id,
        ]);
        return response()->json([
            'result' => 'success',
            'message' => 'Group Added',
        ], 200);
    }


    public function getGroupList(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
            ], 401);
        }

        $content =  Group::select(['id', 'name'])->where(['user_id' => $user->id])->orWhere('priority', '!=', 0)->orderBy('priority', 'DESC')->get();
        //array_push($content,$a);

        foreach ($content as $key => $value) {

            $value->count = GroupMember::where(['group_id' => $value->id, 'created_by' => $user->id])->count();
            if ($value->id == 1) {
                $value->count = MasterContact::where(['user_id' => $user->id])->count();
            }
        }
        //$content.push($a);
        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }

    public function getGroupMemberList(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
            ], 401);
        }
        $content =  Group::select(['id', 'name'])->where(['user_id' => $user->id])->orWhere('id', 2)->get();
        foreach ($content as $key => $value) {
            if ($value->id == 2) {
                $value->count = GroupMember::where(['group_id' => $value->id, 'created_by' => $user->id])->count();
                $value->member = GroupMember::select(['master_contacts.id', 'master_contacts.name', 'master_contacts.number'])->join('master_contacts', 'group_members.member_id', '=', 'master_contacts.id')->where(['group_members.group_id' => $value->id, 'group_members.created_by' => $user->id])->get();
            } else {
                $value->count = GroupMember::where(['group_id' => $value->id])->count();
                $value->member = GroupMember::select(['master_contacts.id', 'master_contacts.name', 'master_contacts.number'])->join('master_contacts', 'group_members.member_id', '=', 'master_contacts.id')->where(['group_members.group_id' => $value->id])->get();
            }

            //get image
            $trimmed_phone = str_replace('+91', '', $value->number);
            $userDetails = User::select(['name', 'phone', 'image'])->where(['phone' => $trimmed_phone])->first();
            if ($userDetails) {
                $value->image =  asset('public/images/' . $userDetails->image);
            } else {
                $value->image = null;
            }
        }


        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }

    public function addMemberGroup(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'group_id' => '',
            'member_id' => ''
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        $member_id = $request->member_id;
        $group_id = $request->group_id;
        if (isset($group_id)) {
            if (!empty($member_id)) {
                foreach ($member_id as $key => $value) {
                    // GroupMember::updateOrcreate([],[
                    //     'group_id'=>$group_id,
                    //     'member_id'=>$value['id'],
                    // ]);
                    $a = GroupMember::where(['group_id' => $group_id, 'member_id' => $value['id'], 'created_by' => $user->id])->first();
                    if (empty($a)) {
                        GroupMember::create([
                            'group_id' => $group_id,
                            'member_id' => $value['id'],
                            'created_by' => $user->id
                        ]);
                    }
                }
            }
        }
        return response()->json([
            'result' => 'success',
            'message' => 'Member Added',
        ], 200);
    }


    public function removerMemberGroup(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'group_id' => '',
            'member_id' => ''
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        $member_id = $request->member_id;
        $group_id = $request->group_id;
        GroupMember::where(['group_id' => $group_id, 'member_id' => $member_id])->delete();

        return response()->json([
            'result' => 'success',
            'message' => 'Member Removed.',
        ], 200);
    }

    //user rated memos
    public function rateMemoList(Request $request)
    {

        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }

        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        $secondary_id = (empty($request->secondary_id)) ? '' : $request->secondary_id;
        $user_id = (empty($request->user_id)) ? $user->id : $request->user_id;
        //post_id is memo_id
        if (empty($request->user_id)) {
            if (!empty($secondary_id)) {
                $content = Memo::select(['experiences.id', 'experiences.category_id', 'experiences.title', 'experiences.description', 'image', 'post_ratings.rating'])->join('post_ratings', 'experiences.id', '=', 'post_ratings.post_id')->where(['post_ratings.user_id' => $user_id, 'experiences.category_id' => $secondary_id])->orderBy('post_ratings.updated_at', 'DESC')->get();
            } else {
                $content = Memo::select(['experiences.id', 'experiences.category_id', 'experiences.title', 'experiences.description', 'image', 'post_ratings.rating'])->join('post_ratings', 'experiences.id', '=', 'post_ratings.post_id')->where(['post_ratings.user_id' => $user_id])->orderBy('post_ratings.updated_at', 'DESC')->get();
            }
        } else {
            //other profile
            $share_ids = $this->getMyGroups($user_id, $user->id);
            $groups = explode(",", $share_ids);
            //DB::enableQueryLog();
            // $content = DB::table('exp_cat')->select(['exp_cat.id', 'exp_cat.category_id', 'exp_cat.title', 'exp_cat.description', 'exp_cat.image', 'post_ratings.rating', 'post_ratings.post_id as memo_id'])->join('post_ratings', 'exp_cat.id', '=', 'post_ratings.post_id')->where(['post_ratings.user_id' => $user_id])->whereIn('post_ratings.share_with', $groups)->orderBy('post_ratings.updated_at', 'DESC')->get();
            $content = Memo::select(['experiences.id', 'experiences.category_id', 'experiences.title', 'experiences.description', 'image', 'post_ratings.rating'])->join('post_ratings', 'experiences.id', '=', 'post_ratings.post_id')->where(['post_ratings.user_id' => $user_id])->orderBy('post_ratings.updated_at', 'DESC')->get();
            //dd(DB::getQueryLog());
        }


        // dd(DB::getQueryLog());
        foreach ($content as $row) {
            $row->all = $this->getRating($row->id);
            $row->known = $this->getKnownRating($row->id, $user_id);
            $row->close = $this->getCloseoneRating($row->id, $user_id);
            $row->me = $this->meRating($row->id, $user_id);
            $row->totalExp = $this->countExpercinaces($row->id);
            $row->image = $this->getFolderImageUrl($row->image, 'icon');
        }


        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }

    //GALLERY - SELF + OTHERS
    public function getGalleryImage(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
            ], 401);
        }

        $user_id = (empty($request->user_id)) ? $user->id : $request->user_id;

        if (empty($request->user_id)) {

            $data = PostImage::select(['post_images.image'])->join('posts', 'post_images.post_id', '=', 'posts.id')->where(['posts.user_id' => $user_id])->orderBy('post_images.updated_at', 'DESC')->get();
        } else {
            //other profile
            $share_ids = $this->getMyGroups($user_id, $user->id);
            $groups = explode(",", $share_ids);
            $data = PostImage::select(['post_images.image'])->join('posts', 'post_images.post_id', '=', 'posts.id')->where(['posts.user_id' => $user_id])->whereIn('posts.share_with', $groups)->orderBy('post_images.updated_at', 'DESC')->get();
        }

        foreach ($data as $key => $value) {
            $value->image = $this->getImageUrl($value->image);
        }
        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $data
        ], 200);
    }

    //my posts
    public function myReacapture(Request $request)
    {

        $validator =  Validator::make($request->all(), [
            'token' => 'required'
        ]);
        $content = array();
        $memo = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }
        $secondary_id = $request->secondary_id;
        $primary_id = $request->primary_id;
        if ($primary_id != 0) {
            $content = DB::table('post_details')->whereRaw("find_in_set('$primary_id',primary_folder)")->where(['user_id' => $user->id])->latest()->paginate(10);
            if ($secondary_id != 0) {
                $content = DB::table('post_details')->where(['user_id' => $user->id])->whereRaw("find_in_set('$primary_id',primary_folder)")->whereRaw("find_in_set('$secondary_id',secondary_folder)")->latest()->paginate(10);
            }
        }

        $content = DB::table('post_details')->where(['user_id' => $user->id])->latest()->paginate(10);
        foreach ($content as  $row) {
            $image = PostImage::where(['post_id' => $row->id])->get();
            $myRecation = PostReact::where(['post_id' => $row->id, 'user_id' => $user->id])->first();
            if ($myRecation) {
                $row->myRecation = $myRecation->reactType;
            } else {
                $row->myRecation = -1;
            }
            foreach ($image as $key => $value) {
                $value->image = $this->getImageUrl($value->image);
            }
            $row->images = $image;
            if ($row->user_image !== '' && $row->user_image != null) {
                $row->user_image =  asset('public/images/' . $row->user_image);
            } else {
                $row->user_image = null;
            }
            $wish = PostWishlist::where(['post_id' => $row->id, 'user_id' => $user->id])->first();
            $row->wish = false;
            if ($wish) {
                $row->wish = true;
            }
            $row->exp = true;
            $memo_ids = explode(",", $row->exp_id);
            // $memo = DB::table('exp_cat')->select(['id','title','image','icon','rating'])->where(['id'=>$row->exp_id])->get();
            // foreach ($memo as $key => $r) {
            //   $r->image = $this->getImageUrl($r->image);
            //   $r->icon = $this->getFolderImageUrl($r->icon,$url='icon');

            // }

            $memo = Memo::select(['id', 'title', 'image'])->whereIn('id', $memo_ids)->get();
            foreach ($memo as $key => $r) {
                $r->image = $this->getFolderImageUrl($r->image, $url = 'icon');
                $r->rating = $this->meRating($r->id, $user->id);
            }

            $row->memos = $memo;
        }

        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }

    //Delete Post
    public function deletePost(Request $request)
    {

        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'post_id' => 'required'
        ]);
        $content = array();
        $memo = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        $post = Post::find($request->post_id);
        $post->delete();

        $post_image = PostImage::where('post_id', $request->post_id)->delete();

        PostComment::where('post_id', $request->post_id)->delete();

        PostReact::where('post_id', $request->post_id)->delete();

        PostWishlist::where('post_id', $request->post_id)->delete();

        PostRating::where('pid', $request->post_id)->delete();

        return response()->json([
            'result' => 'success',
            'message' => 'Successfuly post is deleted'
        ], 200);
    }

    //edit profile
    public function edit_profile(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'name' => 'required',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:8192',
            'email' => ''
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content,
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content,
            ], 401);
        }
        $user_id = $user->id;
        $image = $request->file('image');
        if (!empty($image)) {
            //Store Image In Folder
            $imageName = time() . '.' . request()->image->getClientOriginalExtension();
            request()->image->move(public_path('images/'), $imageName);
            $data = array(
                'name' => request('name'),
                'username' => request('name'),
                'image' => $imageName,
                'email' => request('email'),
                'dob' => request('dob'),
                'gender' => request('gender'),
            );
        } else {
            $data = array(
                'name' => request('name'),
                'username' => request('name'),
                'email' => request('email'),
                'dob' => request('dob'),
                'gender' => request('gender'),
            );
        }
        User::where(['id' => $user_id])->update($data);
        $result = User::where(['id' => $user_id])->first();
        // unset($result->id);
        if ($result->image !== '' && $result->image != null) {
            $result->image =  asset('public/images/' . $result->image);
        }
        return response()->json([
            'result' => 'success',
            'message' => 'Profile updated',
            'content' => $result,
        ], 200);
    }

    public function delete_profile_image(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content,
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content,
            ], 401);
        }
        $user_id = $user->id;

        $data = array(
            'image' => request('name')
        );
        User::where(['id' => $user_id])->update($data);
        $result = User::where(['id' => $user_id])->first();
        unset($result->id);
        // if ($result->image !== '' && $result->image != null) {
        //     $result->image =  asset('public/images/' . $result->image);
        // }
        return response()->json([
            'result' => 'success',
            'message' => 'Profile image deleted',
            'content' => $result,
        ], 200);
    }


    public function logout(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'deviceID' => 'max:255',
        ]);

        if ($validator->fails()) {

            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors())
            ], 400);
        }

        try {
            JWTAuth::invalidate($request->token);
            $user_login = UserLogin::where(['deviceID' => $request->input("deviceID")])->delete();
            return response()->json([
                'result' => 'success',
                'message' => 'User logged out successfully'
            ], 200);
        } catch (JWTException $exception) {
            return response()->json([
                'result' => 'failure',
                'message' => 'Sorry, the user cannot be logged out'
            ], 500);
        }
    }

    //Trending
    public function trending(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        // $content = Memo::select(['experiences.id','experiences.title','experiences.description','experiences.image','post_ratings.id as rating_id','post_ratings.updated_at'])->leftjoin('post_ratings','post_ratings.post_id','=','experiences.id')->whereRaw('DATE(`mf_post_ratings`.`updated_at`) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)')->orderBy('post_ratings.updated_at','DESC')->paginate(30);

        $content = DB::table('memo_rate_deatils')->select('post_id as id', 'title', 'description', 'image', 'id as rating_id', DB::raw('MAX(updated_at) AS updated_at'))->whereRaw('DATE(`mf_memo_rate_deatils`.`updated_at`) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)')->groupby('post_id')->orderBy('updated_at', 'DESC')->paginate(30);

        foreach ($content as  $row) {
            if ($row->image != '' && $row->image != null) {
                $row->image = $this->getFolderImageUrl($row->image, 'icon');
            }


            $users = $this->getUserList($row->id, $user->id, '3', 10);

            foreach ($users as $key => $value) {
                if ($value->image != '' && $value->image != null) {
                    $value->image = asset('public/images/' . $value->image);
                } else {
                    $value->image = null;
                }
                $value->name = $this->getContactName($user->id, $value->id, $value->name);
                $value->type = $this->getUserType($user->id, $value->id,);
                //$value->user_name = $this->getContactName($user->id,$value->id,$value->user_name);
                //$rating = rand(3,9);
                //$value->rating = $this->meRating($row->id,$user->id);
            }

            $row->users = $users;

            $row->all = $this->getRating($row->id);
            $row->known = $this->getKnownRating($row->id, $user->id);
            $row->close = $this->getCloseoneRating($row->id, $user->id);
            $row->me = $this->meRating($row->id, $user->id);
            $row->average_rating = $this->getRating($row->id);
            $row->wish = $this->checkWishlistMemo($row->id, $user->id);
            $row->exp = $this->checkExperienceMemo($row->id, $user->id);
            $row->totalExp = $this->countExpercinaces($row->id);
        }
        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }

    //Report a problem
    public function report_problem(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'description' => 'required'
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        $description = (empty($request->description)) ? '' : $request->description;
        //save report problem in db
        $content =  Report::create([
            "report_type" => 'problem',
            "memo_id" => 0,
            "user_id" => $user->id,
            "description" => $description,
            "status" => 'request',
        ]);

        return response()->json([
            'result' => 'success',
            'message' => 'Your problem is reported to Administrator. Our Team will resolve your issue ASAP.'
        ], 200);
    }

    public function memoDetails(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'memo_id' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }
        //get seen memo_list
        $s = DB::table('seen_memo')->select('memos')->where('user_id', '=', $user->id)->first();
        $seen_array = array();
        if (!empty($s)) {
            $seen_array = explode(',', $s->memos);
        }
        $memo_id = $request->memo_id;
        $content = DB::table('exp_cat')->where(['id' => $memo_id])->first();
        if ($content) {
            $content->image = $this->getFolderImageUrl($content->image, 'icon');
            $content->all = $this->getRating($memo_id);
            $content->known = $this->getKnownRating($memo_id, $user->id);
            $content->close = $this->getCloseoneRating($memo_id, $user->id);
            $content->me = $this->meRating($memo_id, $user->id);
            $content->totalExp = $this->countExpercinaces($memo_id);
            $content->wish = $this->checkWishlistMemo($memo_id, $user->id);
            $content->exp = $this->checkExperienceMemo($memo_id, $user->id);
            //gallery for my knownone who have experienced this
            $related_post = PostDetail::whereRaw('FIND_IN_SET(?,exp_id)', [$memo_id])->get();
            $a = array();
            foreach ($related_post as  $row) {
                $image = PostImage::where(['post_id' => $row->id])->get();
                //$row->gallery = $image;
                foreach ($image as $i) {
                    array_push($a, $i);
                }
            }
            $content->gallery = $a;
            if (in_array($memo_id, $seen_array)) {
                $content->seen = 1;
            } else {
                $content->seen = 0;
            }
        }

        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }

    public function memoRelatedPost(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'memo_id' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        $memo_id = $request->memo_id;
        //apply logic 

        $related_post = PostDetail::whereRaw('FIND_IN_SET(?,exp_id)', $memo_id)
            // ->where(['share_with' => '1,2,3'])
            ->paginate(20);

        // print_r($related_post);die;
        foreach ($related_post as $key => $row) {

            if ($row->share_with == 0 && $user->id != $row->user_id) {
                unset($related_post[$key]);
            } else {
                $image = PostImage::where(['post_id' => $row->id])->get();
                $myRecation = PostReact::where(['post_id' => $row->id, 'user_id' => $user->id])->first();
                if ($myRecation) {
                    $row->myRecation = $myRecation->reactType;
                } else {
                    $row->myRecation = -1;
                }
                foreach ($image as $key => $value) {
                    $value->image = $this->getImageUrl($value->image);
                }
                $row->images = $image;
                if ($row->user_image !== '' && $row->user_image != null) {
                    $row->user_image =  asset('public/images/' . $row->user_image);
                } else {
                    $row->user_image = null;
                }
                $wish = PostWishlist::where(['post_id' => $row->id, 'user_id' => $user->id])->first();
                $row->wish = false;
                if ($wish) {
                    $row->wish = true;
                }
                $row->exp = false;
                $row->user_name = $this->getContactName($user->id, $row->user_id, $row->user_name);
                //date parse
                $createdAt = Carbon::parse($row->created_at);
                $updatedAt = Carbon::parse($row->updated_at);
                unset($row->updated_at);
                unset($row->created_at);
                $row->created_at = $createdAt->format('Y-m-d h:i:s');
                $row->created_at_formatted = $createdAt->format('Y-m-d h:i:s a');
                $row->updated_at = $updatedAt->format('Y-m-d h:i:s');
                $row->updated_at_formatted = $updatedAt->format('Y-m-d h:i:s a');

                $memo = array();
                if (!empty($row->exp_id)) {
                    $aa = explode(',', $row->exp_id);
                    $memo = Memo::select(['id', 'title', 'image'])->whereIn('id', $aa)->get();
                }

                // dd($memo);
                foreach ($memo as $key => $r) {
                    $r->image = $this->getFolderImageUrl($r->image, $url = 'icon');
                    $r->rating = $this->getRating($r->id);
                }

                $row->memos = $memo;
            }
        }

        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $related_post
        ], 200);
    }

    public function report_memo(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'description' => 'required',
            'memo_id' => 'required'
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        $description = (empty($request->description)) ? '' : $request->description;
        //save report problem in db
        $content =  Report::create([
            "report_type" => 'memo',
            "memo_id" => $request->memo_id,
            "user_id" => $user->id,
            "description" => $description,
            "status" => 'request',
        ]);
        return response()->json([
            'result' => 'success',
            'message' => 'Your problem against memo is reported to Administrator. Our Team will resolve your issue ASAP.'
        ], 200);
    }

    //Friends Profile
    public function otherUserDetails(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'userID' => 'required'
        ]);
        $content = array();
        $data = array();

        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        //other user
        $ouser = User::find($request->userID);

        if (!empty($ouser)) {
            if ($ouser->image !== '' && $ouser->image != null) {
                $ouser->image =  asset('public/images/' . $ouser->image);
            } else {
                $ouser->image = null;
            }
            $ouser->name = $this->getContactName($user->id, $ouser->id, $ouser->name);
            $ouser->username = $this->getContactName($user->id, $ouser->id, $ouser->username);

            $total_rated_memo = PostRating::where(['user_id' => $ouser->id])->count();

            $ouser->total_rated_memo = ($total_rated_memo) ? $total_rated_memo : '0';

            if ($request->userID == $user->id) {
                $rated_memo = DB::table('exp_cat')
                    ->select([
                        'exp_cat.id',
                        'exp_cat.category_id',
                        'exp_cat.title',
                        'exp_cat.image',
                        'exp_cat.rating',
                        'exp_cat.description',
                        'post_ratings.post_id',
                        'post_ratings.share_with'
                    ])
                    ->join('post_ratings', 'exp_cat.id', '=', 'post_ratings.post_id')
                    ->where('post_ratings.share_with', '!=', 0)
                    ->whereIn('post_ratings.share_with', ['2', '3'])
                    ->where(['post_ratings.user_id' => $ouser->id])
                    ->orderBy('post_ratings.updated_at', 'DESC')
                    ->get();
            } else {
                $share_ids = $this->getMyGroups($ouser->id, $user->id);
                $groups = explode(",", $share_ids);
                //DB::enableQueryLog();
                $rated_memo = DB::table('exp_cat')
                    ->select([
                        'exp_cat.id',
                        'exp_cat.category_id',
                        'exp_cat.title',
                        'exp_cat.image',
                        'exp_cat.rating',
                        'exp_cat.description',
                        'post_ratings.post_id',
                        'post_ratings.share_with'
                    ])
                    ->join('post_ratings', 'exp_cat.id', '=', 'post_ratings.post_id')
                    ->where(['post_ratings.user_id' => $ouser->id])
                    ->where('post_ratings.share_with', '!=', 0)
                    // ->whereIn('post_ratings.share_with', ['2', '3'])
                    ->whereIn('post_ratings.share_with', $groups)
                    ->orderBy('post_ratings.updated_at', 'DESC')
                    ->get();
                //dd(DB::getQueryLog());
            }


            // if (!empty($secondary_id)) {
            //     $content = Memo::select(['experiences.id', 'experiences.category_id', 'experiences.title', 'experiences.description', 'image', 'post_ratings.rating'])->join('post_ratings', 'experiences.id', '=', 'post_ratings.post_id')->where(['post_ratings.user_id' => $user->id, 'experiences.category_id' => $secondary_id])->orderBy('post_ratings.updated_at', 'DESC')->get();
            // } else {
            //     $content = Memo::select(['experiences.id', 'experiences.category_id', 'experiences.title', 'experiences.description', 'image', 'post_ratings.rating'])->join('post_ratings', 'experiences.id', '=', 'post_ratings.post_id')->where(['post_ratings.user_id' => $user->id])->orderBy('post_ratings.updated_at', 'DESC')->get();
            // }
            $catDetails = [];

            foreach ($rated_memo as $value) {
                $value->all = $this->getRating($value->id);
                $value->known = $this->getKnownRating($value->id, $ouser->id);
                $value->close = $this->getCloseoneRating($value->id, $ouser->id);
                $value->me = $this->meRating($value->id, $ouser->id);

                $value->totalExp = $this->countExpercinaces($value->id);
                $value->image = $this->getFolderImageUrl($value->image, 'icon');

                $catDetails = ExpCategory::select(['id', 'category_name', 'type', 'icon'])->where(['status' => 'Y'])->whereIn('id', [$value->category_id])->get();

                $value->category = $catDetails;
                $catDetails = [];
                unset($value->category_id);
            }

            // print_r($category);die;

            if ($request->userID == $user->id) {
                $data = PostImage::select(['post_images.image'])->join('posts', 'post_images.post_id', '=', 'posts.id')->where(['posts.user_id' => $ouser->id])->orderBy('post_images.updated_at', 'DESC')->take(8)->get();
            } else {
                $share_ids = $this->getMyGroups($ouser->id, $user->id);
                $groups = explode(",", $share_ids);


                $data = PostImage::select(['post_images.image'])->join('posts', 'post_images.post_id', '=', 'posts.id')->where(['posts.user_id' => $ouser->id])->whereIn('posts.share_with', $groups)->orderBy('post_images.updated_at', 'DESC')->take(8)->get();
            }


            foreach ($data as $key => $value) {
                $value->image = $this->getImageUrl($value->image);
            }

            $content = [
                'rated_memo' => $rated_memo,
                'gallery' => $data
            ];
            $total_contacts = MasterContact::where(['master_contacts.user_id' => $ouser->id])->count();

            return response()->json([
                'result' => 'success',
                'message' => '',
                'content' => $content,
                'user' => $ouser,
                'total_contacts' => $total_contacts,
                // 'total_rated_memo' => $total_rated_memo
            ], 200);
        } else {

            return response()->json([
                'result' => 'failure',
                'message' => 'Invalid User',
                'content' => array(),
                'user' => (object)array(),
                'total_contacts' => 0,
                'total_rated_memo' => 0
            ], 400);
        }
    }


    //Friends Profile
    public function otherUserCategoryDetails(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'userID' => 'required'
        ]);
        $content = array();
        $data = array();

        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        //other user
        $ouser = User::find($request->userID);

        if (!empty($ouser)) {
            // if ($ouser->image !== '' && $ouser->image != null) {
            //     $ouser->image =  asset('public/images/' . $ouser->image);
            // } else {
            //     $ouser->image = null;
            // }
            // $ouser->name = $this->getContactName($user->id, $ouser->id, $ouser->name);
            // $ouser->username = $this->getContactName($user->id, $ouser->id, $ouser->username);

            // $total_rated_memo = PostRating::where(['user_id' => $ouser->id])->count();

            // $ouser->total_rated_memo = ($total_rated_memo) ? $total_rated_memo : '0';

            if ($request->userID == $user->id) {
                $rated_memo = DB::table('exp_cat')
                    ->select([
                        // 'exp_cat.id',
                        'exp_cat.category_id',
                        // 'exp_cat.title',
                        // 'exp_cat.image',
                        // 'exp_cat.rating',
                        // 'exp_cat.description',
                        // 'post_ratings.post_id',
                        // 'post_ratings.share_with'
                    ])
                    ->join('post_ratings', 'exp_cat.id', '=', 'post_ratings.post_id')
                    ->where('post_ratings.share_with', '!=', 0)
                    ->whereIn('post_ratings.share_with', ['2', '3'])
                    ->where(['post_ratings.user_id' => $ouser->id])
                    ->groupBy('exp_cat.category_id')
                    ->orderBy('post_ratings.updated_at', 'DESC')
                    ->get();
            } else {
                $share_ids = $this->getMyGroups($ouser->id, $user->id);
                $groups = explode(",", $share_ids);
                //DB::enableQueryLog();
                $rated_memo = DB::table('exp_cat')
                    ->select([
                        // 'exp_cat.id',
                        'exp_cat.category_id',
                        // 'exp_cat.title',
                        // 'exp_cat.image',
                        // 'exp_cat.rating',
                        // 'exp_cat.description',
                        // 'post_ratings.post_id',
                        // 'post_ratings.share_with'
                    ])
                    ->join('post_ratings', 'exp_cat.id', '=', 'post_ratings.post_id')
                    ->where(['post_ratings.user_id' => $ouser->id])
                    ->where('post_ratings.share_with', '!=', 0)
                    // ->whereIn('post_ratings.share_with', ['2', '3'])
                    ->whereIn('post_ratings.share_with', $groups)
                    ->groupBy('exp_cat.category_id')
                    ->orderBy('post_ratings.updated_at', 'DESC')
                    ->get();
                //dd(DB::getQueryLog());
            }


            // if (!empty($secondary_id)) {
            //     $content = Memo::select(['experiences.id', 'experiences.category_id', 'experiences.title', 'experiences.description', 'image', 'post_ratings.rating'])->join('post_ratings', 'experiences.id', '=', 'post_ratings.post_id')->where(['post_ratings.user_id' => $user->id, 'experiences.category_id' => $secondary_id])->orderBy('post_ratings.updated_at', 'DESC')->get();
            // } else {
            //     $content = Memo::select(['experiences.id', 'experiences.category_id', 'experiences.title', 'experiences.description', 'image', 'post_ratings.rating'])->join('post_ratings', 'experiences.id', '=', 'post_ratings.post_id')->where(['post_ratings.user_id' => $user->id])->orderBy('post_ratings.updated_at', 'DESC')->get();
            // }

            foreach ($rated_memo as $value) {
                // $value->all = $this->getRating($value->id);
                // $value->known = $this->getKnownRating($value->id, $ouser->id);
                // $value->close = $this->getCloseoneRating($value->id, $ouser->id);
                // $value->me = $this->meRating($value->id, $ouser->id);

                // $value->totalExp = $this->countExpercinaces($value->id);
                // $value->image = $this->getFolderImageUrl($value->image, 'icon');

                $catDetails = ExpCategory::select(['id', 'category_name', 'type', 'icon'])->where(['id' => $value->category_id, 'status' => 'Y'])->first();
                $value->category_name = $catDetails->category_name;
            }

            // if ($request->userID == $user->id) {
            //     $data = PostImage::select(['post_images.image'])->join('posts', 'post_images.post_id', '=', 'posts.id')->where(['posts.user_id' => $ouser->id])->orderBy('post_images.updated_at', 'DESC')->take(8)->get();
            // } else {
            //     $share_ids = $this->getMyGroups($ouser->id, $user->id);
            //     $groups = explode(",", $share_ids);


            //     $data = PostImage::select(['post_images.image'])->join('posts', 'post_images.post_id', '=', 'posts.id')->where(['posts.user_id' => $ouser->id])->whereIn('posts.share_with', $groups)->orderBy('post_images.updated_at', 'DESC')->take(8)->get();
            // }


            // foreach ($data as $key => $value) {
            //     $value->image = $this->getImageUrl($value->image);
            // }

            $content = [
                'rated_memo' => $rated_memo,
                // 'gallery' => $data
            ];
            // $total_contacts = MasterContact::where(['master_contacts.user_id' => $ouser->id])->count();

            return response()->json([
                'result' => 'success',
                'message' => '',
                'content' => $content,
                'user' => $ouser,
                // 'total_contacts' => $total_contacts,
                // 'total_rated_memo' => $total_rated_memo
            ], 200);
        } else {

            return response()->json([
                'result' => 'failure',
                'message' => 'Invalid User',
                'content' => array(),
                'user' => (object)array(),
                'total_contacts' => 0,
                'total_rated_memo' => 0
            ], 400);
        }
    }

    //Friends Posts
    public function userReacapture(Request $request)
    {

        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'userID' => 'required'
        ]);
        $content = array();
        $memo = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }
        //other user
        $ouser = User::find($request->userID);

        //logic apply
        if ($request->userID == $user->id) {
            $content = DB::table('post_details')->where(['user_id' => $ouser->id])->latest()->paginate(10);
            $user_id = $user->id;
        } else {
            $share_ids = $this->getMyGroups($ouser->id, $user->id);
            $content = DB::table('post_details')->where(['user_id' => $ouser->id])->whereRaw('FIND_IN_SET(share_with,"' . $share_ids . '")')->latest()->paginate(10);

            $user_id = $ouser->id;
        }

        foreach ($content as  $row) {
            $image = PostImage::where(['post_id' => $row->id])->get();
            $myRecation = PostReact::where(['post_id' => $row->id, 'user_id' => $user_id])->first();
            if ($myRecation) {
                $row->myRecation = $myRecation->reactType;
            } else {
                $row->myRecation = -1;
            }
            foreach ($image as $key => $value) {
                $value->image = $this->getImageUrl($value->image);
            }
            $row->images = $image;
            if ($row->user_image !== '' && $row->user_image != null) {
                $row->user_image =  asset('public/images/' . $row->user_image);
            } else {
                $row->user_image = null;
            }
            $row->user_name = $this->getContactName($user_id, $row->user_id, $row->user_name);
            $wish = PostWishlist::where(['post_id' => $row->id, 'user_id' => $user_id])->first();
            $row->wish = false;
            if ($wish) {
                $row->wish = true;
            }
            $row->exp = true;
            // $memo_id = explode(",", $row->exp_id);
            // $memo = DB::table('exp_cat')->select(['id','title','image','icon','rating'])->where(['id'=>$row->exp_id])->get();
            // foreach ($memo as $key => $r) {
            //   $r->image = $this->getImageUrl($r->image);
            //   $r->icon = $this->getFolderImageUrl($r->icon,$url='icon');

            // }

            $memo = array();
            if (!empty($row->exp_id)) {
                $aa = explode(',', $row->exp_id);
                $memo = Memo::select(['id', 'title', 'image'])->whereIn('id', $aa)->get();
            }

            // $memo = Memo::select(['id', 'title', 'image'])->whereIn('id', array($row->exp_id))->get();
            foreach ($memo as $key => $r) {
                $r->image = $this->getFolderImageUrl($r->image, $url = 'icon');
                $r->rating = $this->meRating($r->id, $user_id);
            }

            $row->memos = $memo;
        }

        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }


    public function categoryDetails(Request $request)
    {

        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        $category = MainCategory::select(['id', 'name'])->where(['status' => 'Y'])->first();
        $subcategory = ExpCategory::select(['id', 'category_name', 'type', 'icon'])->where(['main_cate_id' => $category->id, 'status' => 'Y'])->first();

        $subcategory->icon = $this->getFolderImageUrl($subcategory->icon, 'icon');


        $category_id = $subcategory->id;
        $memo = Memo::select(['id', 'title', 'description'])->where(['category_id' => $category_id])->get();
        foreach ($memo as $row) {

            $row->all = $this->getRating($row->id);
            $row->known = $this->getKnownRating($row->id, $user->id);
            $row->close = $this->getCloseoneRating($row->id, $user->id);
            $row->me = $this->meRating($row->id, $user->id);
            $row->totalExp = $this->countExpercinaces($row->id);
        }

        $content = [
            'category' => $category,
            'subcategory' => $subcategory,
            'memo' => $memo
        ];
        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }

    public function subCategoryList(Request $request)
    {

        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'category_id' => 'required'
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        $type = (!empty($request->type)) ? $request->type : '';
        //get fav cat
        $fc = DB::table('folder_wishlists')->selectRaw("GROUP_CONCAT(secondary_id,'') as fav_cat")->where('user_id', '=', $user->id)->first();
        $fav_cat = 0;
        if (!empty($fc) && !empty($fc->fav_cat)) {
            $fav_cat = $fc->fav_cat;
        }

        $category_id = $request->category_id;
        $content = ExpCategory::select(['id', 'category_name', 'type', 'icon'])->where(['main_cate_id' => $category_id, 'status' => 'Y'])->get();
        foreach ($content as $key => $value) {
            $value->icon = $this->getFolderImageUrl($value->icon, 'icon');
        }
        if ($category_id == 0) {

            if ($type == 'all') {
                $content = ExpCategory::selectRaw("id,category_name,type,icon,IF (id IN (" . $fav_cat . "), '1','0') as fav_cat, priority")->where(['status' => 'Y'])->orderBy('fav_cat', 'desc')->orderByRaw('CONVERT(priority, SIGNED) desc')->orderBy('category_name', 'asc')->get();
            } else {
                $content = ExpCategory::selectRaw("id,category_name,type,icon,IF (id IN (" . $fav_cat . "), '1','0') as fav_cat, priority")->where(['status' => 'Y'])->whereIn('id', function ($query) {
                    $query->select('category_id')
                        ->from(with(new Memo)->getTable())
                        ->where('status', 'Y');
                })->orderBy('fav_cat', 'desc')->orderByRaw('CONVERT(priority, SIGNED) desc')->orderBy('category_name', 'asc')->get();
            }


            // $content = ExpCategory::select(['id','category_name','type','icon'])->where(['status'=>'Y'])->get();
            foreach ($content as $key => $value) {
                $value->icon = $this->getFolderImageUrl($value->icon, 'icon');
            }
        }


        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }


    public function subCategoryListNew(Request $request)
    {

        $validator =  Validator::make($request->all(), [
            // 'token' => 'required',
            'category_id' => 'required'
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        // $user = JWTAuth::parseToken()->authenticate();
        // if (empty($user)) {
        //     return response()->json([
        //         'result' => 'failure',
        //         'message' => '',
        //         'content' => $content
        //     ], 401);
        // }

        $category_id = $request->category_id;
        if ($category_id == 0) {

            $content = ExpCategory::select(['id', 'main_cate_id', 'category_name', 'type', 'icon'])->where(['status' => 'Y'])->get();
            foreach ($content as $key => $value) {
                $value->icon = $this->getFolderImageUrl($value->icon, 'icon');
            }
        } else {

            $content = ExpCategory::select(['id', 'main_cate_id', 'category_name', 'type', 'icon'])->where(['main_cate_id' => $category_id, 'status' => 'Y'])->get();
            foreach ($content as $key => $value) {
                $value->icon = $this->getFolderImageUrl($value->icon, 'icon');
            }
        }

        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }

    public function addMemo(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
            ], 401);
        }
        $iconName = '';
        $title = $request->title;
        $description = $request->description;
        $parent_id = 0;
        $secondary_id = $request->secondary_id;
        $priority_key = $request->priority_key;
        // $image = $request->file('image');
        // $imageName = '';
        //  if (!empty($image)) {
        //     $imageName = time().'.'.request()->image->getClientOriginalExtension();
        //     request()->image->move(public_path('upload/images/'), $imageName);
        // }

        //check name & description
        if (!empty($description)) {
            $check = DB::table('experiences')->whereRaw('LOWER(title) = "' . strtolower($title) . '"')->whereRaw('LOWER(description) = "' . strtolower($description) . '"')->first();
        } else {
            $check = DB::table('experiences')->whereRaw('LOWER(title) = "' . strtolower($title) . '"')->first();
        }

        if (!empty($check)) {
            //check group
            $inputgroup = explode(',', $secondary_id);
            $outputgroup = explode(',', $check->category_id);
            $res = array_intersect($inputgroup, $outputgroup);

            if (!empty($res)) {
                return response()->json([
                    'result' => 'failure',
                    'message' => 'Memo with same configuration already exist',
                ], 200);
            }
        }



        if (!empty($priority_key)) {
            $data = ExpCategory::where(['id' => $priority_key])->first();
            $iconName = $data->icon;
        }
        Memo::create([
            'created_id' => $user->id,
            'title' => $title,
            'description' => $description,
            'parent_id' => $parent_id,
            'priority_key' => $priority_key,
            'image' => $iconName,
            'category_id' => $secondary_id,
        ]);
        return response()->json([
            'result' => 'success',
            'message' => 'Memo Added',
        ], 200);
    }

    //FIND PEOPLE
    public function peoplelist(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        $content = DB::table('memo_user_rate_detail')->selectRaw('user_id, count(*) as total_rated_memo, name, image')->where('rating', '!=', '0.0')->groupBy('user_id')->orderBy('total_rated_memo', 'DESC')->take(5)->get();

        foreach ($content as $c) {
            if ($c->image !== '' && $c->image != null) {
                $c->image =  asset('public/images/' . $c->image);
            }
            $c->name = $this->getContactName($user->id, $c->user_id, $c->name);
            $c->type = $this->getUserType($user->id, $c->user_id,);
        }
        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }

    public function likeMinded(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        $content = DB::table('memo_user_rate_detail as t')->selectRaw('user_id , COUNT(*) as total_memo_rate, COALESCE(ROUND(AVG(ABS(mf_t.`rating` - (SELECT `rating` FROM `mf_memo_user_rate_detail` WHERE `post_id` = mf_t.`post_id` AND `user_id` = ' . $user->id . ') )),2),0) as diff_rate, name, image')->whereRaw('post_id IN (SELECT `post_id` FROM `mf_memo_user_rate_detail` WHERE `user_id` = ' . $user->id . ')')->where('user_id', '!=', $user->id)->where('rating', '!=', '0.0')->groupBy('t.user_id')->having('total_memo_rate', '>', '9')->having('diff_rate', '<=', '1.3')->get();

        foreach ($content as $c) {
            if ($c->image !== '' && $c->image != null) {
                $c->image =  asset('public/images/' . $c->image);
            }
            $c->name = $this->getContactName($user->id, $c->user_id, $c->name);
            $c->type = $this->getUserType($user->id, $c->user_id,);
        }
        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }

    public function differentMinded(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        $content = DB::table('memo_user_rate_detail as t')->selectRaw('user_id , COUNT(*) as total_memo_rate, COALESCE(ROUND(AVG(ABS(mf_t.`rating` - (SELECT `rating` FROM `mf_memo_user_rate_detail` WHERE `post_id` = mf_t.`post_id` AND `user_id` = ' . $user->id . ') )),2),0) as diff_rate, name, image')->whereRaw('post_id IN (SELECT `post_id` FROM `mf_memo_user_rate_detail` WHERE `user_id` = ' . $user->id . ')')->where('user_id', '!=', $user->id)->where('rating', '!=', '0.0')->groupBy('t.user_id')->having('total_memo_rate', '>', '9')->having('diff_rate', '<=', '2.7')->get();

        foreach ($content as $c) {
            if ($c->image !== '' && $c->image != null) {
                $c->image =  asset('public/images/' . $c->image);
            }
            $c->name = $this->getContactName($user->id, $c->user_id, $c->name);
            $c->type = $this->getUserType($user->id, $c->user_id,);
        }

        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }

    public function home(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content,
                'page' => '1',
                'user' => $user
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'page' => '1',
                'content' => $content,
                'user' => $user
            ], 401);
        }
        $content = [];
        unset($user->id);
        // if($user->image!=='' && $user->image!=null){
        //        $user->image =  asset('public/images/'.$user->image);
        //  }

        return response()->json([
            'result' => 'success',
            'message' => '',
            'page' => '1',
            'content' => $content,
            'user' => $user
        ], 200);
    }


    public function contactList(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        $friends = MasterContact::select(['master_contacts.id', 'master_contacts.name', 'master_contacts.number'])->where(['master_contacts.user_id' => $user->id])->get();

        // print_r($friends);die;

        foreach ($friends as $key => $value) {
            $trimmed_phone = str_replace('+91', '', $value->number);
            $userDetails = User::select(['name', 'phone', 'image'])->where(['phone' => $trimmed_phone])->first();
            if ($userDetails) {
                $value->image =  asset('public/images/' . $userDetails->image);
            } else {
                $value->image = null;
            }
        }

        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $friends,
        ], 200);
    }


    public function addContact(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'contact_id' => 'required'
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
            ], 401);
        }

        UserContact::updateOrCreate([
            'contact_id' => $request->contact_id,
            'user_id' => $user->id
        ], []);
        return response()->json([
            'result' => 'success',
            'message' => 'Contact Added',
        ], 200);
    }


    public function addPrimaryGroup(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'name' => 'required|unique:main_categories',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
            ], 401);
        }

        $content = MainCategory::create([
            'created_id' => $user->id,
            'name' => $request->name,
            'status' => 'N',
        ]);
        return response()->json([
            'result' => 'success',
            'message' => 'Primary Group Request Added',
            'content' => $content
        ], 200);
    }

    public function addSecondaryGroup(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            //'primary_id'=>'required',
            'name' => '',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
            ], 400);
        }

        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
            ], 401);
        }
        $name = $request->name;
        //$primary_id = $request->primary_id;
        $primary_id = 0;
        $type = $request->type;
        // $image = $request->file('icon');
        $iconName = '';
        //  if (!empty($icon)) {
        //     $iconName = time().'.'.request()->icon->getClientOriginalExtension();
        //     request()->icon->move('../admin/upload/icons/', $iconName);
        // }
        $content = ExpCategory::create([
            'created_id' => $user->id,
            'main_cate_id' => $primary_id,
            'category_name' => $name,
            'type' => $type,
            'icon' => $iconName,
            'status' => 'N',
        ]);
        if ($content->icon != null) {
            $content->icon = $this->getFolderImageUrl($content->icon, $url = 'icon');
        }

        return response()->json([
            'result' => 'success',
            'message' => 'Secondary Group Request Added',
            'content' => $content
        ], 200);
    }


    public function createPrimaryFolder(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'name' => 'required|unique:primary_folders',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }
        $folder = PrimaryFolder::create([
            'name' => request('name'),
            'created_id' => $user->id,
        ]);
        return response()->json([
            'result' => 'success',
            'message' => 'Group Added',
            'content' => $folder
        ], 200);
    }

    public function createSecondaryFolder(Request $request)
    {
        $validator =  Validator::make($request->all(), [
            'token' => 'required',
            'name' => 'required',
            'primary_id' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }

        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        $data = SecondaryFolder::where(['name' => request('name'), 'parent_id' => request('primary_id')])->get();
        if ($data->isEmpty()) {
            $content = SecondaryFolder::create([
                'name' => request('name'),
                'parent_id' => request('primary_id'),
                'created_id' => $user->id,
            ]);
            return response()->json([
                'result' => 'success',
                'message' => 'Folder Created',
                'content' => $content
            ], 200);
        } else {
            return response()->json([
                'result' => 'success',
                'message' => 'Folder Name Already Exist.',
                'content' => $content
            ], 200);
        }
    }


    public function getCollectionFolder(Request $request)
    {

        $validator =  Validator::make($request->all(), [
            'token' => 'required',
        ]);
        $content = array();
        $user = null;
        if ($validator->fails()) {
            return response()->json([
                'result' => 'failure',
                'message' => json_encode($validator->errors()),
                'content' => $content
            ], 400);
        }
        $user = JWTAuth::parseToken()->authenticate();
        if (empty($user)) {
            return response()->json([
                'result' => 'failure',
                'message' => '',
                'content' => $content
            ], 401);
        }

        $content = PrimaryFolder::select(['id', 'name'])->where(['status' => 'Y', 'created_id' => 0])->orWhere('created_id', $user->id)->latest()->get();
        foreach ($content as $key => $row) {
            $row->secondary = SecondaryFolder::select(['id', 'name'])->where(['parent_id' => $row->id, 'status' => 'Y', 'created_id' => 0])->orWhere('created_id', $user->id)->get();
        }
        return response()->json([
            'result' => 'success',
            'message' => '',
            'content' => $content
        ], 200);
    }



    // NEW API






    public function test_notification()
    {
        $deviceToken = 'dw6BAQMuRzqE9VzwR90iaR:APA91bG2e1TwgYEs34gLhx7Q5YuZxXMYksBFPooUtijz6VjUwGOHKNVcM34bY7-E71Ykp1o2bldlDyJ1qttO7epdSOowzrtE83hZbQU_AkdxZYpstKTQTJYOVxWj6kmF7Gz513qiZhww';
        $sendData = array(
            'body' => 'New Order is allocated',
            'title' => 'Allocated',
            'type' => '',
            'sound' => 'Default'
        );
        $a = $this->fcmNotification($deviceToken, $sendData);
        var_dump($a);
    }

    public function send_notification($title, $body, $deviceToken, $type)
    {
        $deviceToken = $deviceToken;
        $sendData = array(
            'body' => $body,
            'title' => $title,
            'rid' => $type,
            'sound' => 'Default'
        );
        $this->fcmNotification($deviceToken, $sendData);
    }

    public function fcmNotification($device_id, $sendData)
    {
        #API access key from Google API's Console
        if (!defined('API_ACCESS_KEY')) {
            define('API_ACCESS_KEY', 'AAAAraWXlpg:APA91bE8CITnZQsGRwPoMaFREdzLkqL-VXmv1DnJFPIcc-pkvLpiZxzdLdm8OxroJq1-fSdaL_pNVrF6XOMvdREz_pF9fVWtRQHfi8oA-mxFJjpnXFVP5aqBt7LREFUrucmkZGfxDnO0');
        }

        $fields = array(
            'to'    => $device_id,
            'data'  => $sendData,
            'notification'  => $sendData
        );

        $headers = array(
            'Authorization: key=' . API_ACCESS_KEY,
            'Content-Type: application/json'
        );
        #Send Reponse To FireBase Server
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        $result = curl_exec($ch);
        //$data = json_decode($result);
        if ($result === false)
            die('Curl failed ' . curl_error($ch));

        curl_close($ch);
        return $result;
    }

    public function test($title = 'ICICI Bank', $description = '')
    {


        // if(!empty($description))
        // {
        //     $check = DB::table('experiences')->whereRaw('LOWER(title) = "'.strtolower($title).'"')->whereRaw('LOWER(description) = "'.strtolower($description).'"')->get();    
        // } else {
        //     $check = DB::table('experiences')->whereRaw('LOWER(title) = "'.strtolower($title).'"')->get();
        // }

        // PostRating::leftjoin('user_contact','user_contact.contact_user_id','=','post_ratings.user_id')->where(['user_contact.user_id'=>22])->where('rating','!=','0.0')->pluck('post_ratings.id as rating_id','rating')->avg();
        // MasterContact::select(['master_contacts.id','master_contacts.name','master_contacts.number','users.id as uid'])->leftjoin('users',DB::raw('CONCAT_WS("",mf_users.country_code,mf_users.phone)'),'=','master_contacts.number')->where(['user_id'=>22])->orderByRaw('-uid DESC')->orderBy('name','ASC')->get();

        // ExpCategory::select(['id','category_name','type','icon'])->where(['status'=>'Y'])->whereIn('id', function($query){
        //             $query->select('category_id')
        //             ->from(with(new Memo)->getTable())
        //             ->where('status', 'Y');
        //         })->get();

        // Memo::select(['experiences.id','experiences.title','experiences.description','experiences.image','post_ratings.id as rating_id'])->leftjoin('post_ratings','post_ratings.post_id','=','experiences.id')->orderBy('experiences.recomended','ASC')->orderByRaw('-rating_id DESC')->where('experiences.status','=','Y')->orderBy('experiences.title','ASC')->orderBy('experiences.updated_at')->get();

        //DB::table('memo_user_rate_detail')->where(['post_id'=>7317])->get();

        // $users = DB::table('memo_user_rate_detail')->select('memo_user_rate_detail.*')->where(['memo_user_rate_detail.post_id'=>7317])->whereIn('memo_user_rate_detail.user_id',function($query){
        //             $query->select('contact_user_id')
        //             ->from('user_contact')
        //             ->where('user_id',1);
        //         })->get();

        // $users = DB::table('memo_user_rate_detail')->select(['memo_user_rate_detail.user_id as id','memo_user_rate_detail.name','memo_user_rate_detail.image','memo_user_rate_detail.rating','memo_user_rate_detail.share_with'])->where(['post_id'=>1024,])->where('share_with',function($query){
        //     $groups = $this->getMyGroups(11,15);
        //     $query->select(DB::raw('FIND_IN_SET(share_with,"'.$groups.'")',));
        // })->get(); 

        // DB::table('memo_user_rate_detail')->where(['post_id'=>1024, 'share_with'=>'3'])->orWhereRaw('(`share_with` = 1 AND `user_id` IN (SELECT `contact_user_id` from `mf_user_contact` where `user_id` = 11))')->get();

        //$content = Memo::select(['experiences.id','experiences.title','experiences.description','experiences.image','post_ratings.id as rating_id','post_ratings.updated_at'])->leftjoin('post_ratings','post_ratings.post_id','=','experiences.id')->whereRaw('DATE(`mf_post_ratings`.`updated_at`) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)')->get();
        $user_id = 11;
        $memo_id = 9216;
        $secondary_id = 8;
        $search = 'phone';
        // DB::table('memo_user_rate_detail')->select(['user_id as id','name','image','rating','share_with'])->where('post_id','=',1024)->whereRaw('(`share_with` = 3) OR (`share_with` = 1 AND `user_id` IN (SELECT `contact_user_id` from `mf_user_contact` where `user_id` = "'.$user_id.'")) OR (`share_with` IN (SELECT `group_id` FROM `mf_members_by_group` WHERE `created_by` = `mf_memo_user_rate_detail`.`user_id` AND `contact_user_id` =  "'.$user_id.'"))')->whereRaw('CASE WHEN  user_id = "'.$user_id.'" then share_with = 0 else share_with !=0 end')->take(10)->get();
        //DB::table('memo_user_rate_detail')->select(['user_id as id','name','image','rating','share_with'])->where('post_id','=',$memo_id)->whereRaw('(`share_with` = 3) OR (`share_with` = 1 AND `user_id` IN (SELECT `contact_user_id` from `mf_user_contact` where `user_id` = "'.$user_id.'")) OR (`share_with` IN (SELECT `group_id` FROM `mf_members_by_group` WHERE `created_by` = `mf_memo_user_rate_detail`.`user_id` AND `contact_user_id` =  "'.$user_id.'") OR (`share_with` = 0 AND `user_id` = "'.$user_id.'") )')->take(100)->get(); 


        DB::enableQueryLog();

        //$users = DB::table('memo_user_rate_detail')->select(['user_id as id','name','image','rating','share_with'])->where('post_id','=',$memo_id)->whereRaw('((`share_with` = 3) OR (`share_with` = 1 AND `user_id` IN (SELECT `contact_user_id` from `mf_user_contact` where `user_id` = "'.$user_id.'")) OR (`share_with` IN (SELECT `group_id` FROM `mf_members_by_group` WHERE `created_by` = `mf_memo_user_rate_detail`.`user_id` AND `contact_user_id` =  "'.$user_id.'")) OR (`share_with` = 0 AND `user_id` = "'.$user_id.'") )')->take(100)->get(); 

        // Memo::select(['experiences.id','experiences.title','experiences.description','experiences.image','post_ratings.id as rating_id','post_ratings.updated_at'])->leftjoin('post_ratings','post_ratings.post_id','=','experiences.id')->whereRaw('DATE(`mf_post_ratings`.`updated_at`) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)')->orderBy('post_ratings.updated_at','DESC')->get();

        // DB::table('memo_rate_deatils')->select(DB::raw('mf_t.*'))
        // ->from(DB::raw('(select `post_id` as `id`, `title`, `description`, `image`, `id` as `rating_id`, `updated_at` from `mf_memo_rate_deatils` where DATE(`mf_memo_rate_deatils`.`updated_at`) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) order by `mf_memo_rate_deatils`.`updated_at` desc) mf_t'))
        // ->groupBy('id')
        // ->get();

        //DB::table('memo_rate_deatils')->select('post_id as id','title','description','image','id as rating_id', DB::raw('MAX(updated_at) AS updated_at'))->whereRaw('DATE(`mf_memo_rate_deatils`.`updated_at`) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)')->orderBy('memo_rate_deatils.updated_at','DESC')->groupby('id')->get();

        //$content = DB::table('memo_user_rate_detail as t')->selectRaw('user_id , COUNT(*) as total_memo_rate, COALESCE(ROUND(AVG(ABS(mf_t.`rating` - (SELECT rating from mf_memo_user_rate_detail where post_id = mf_t.post_id and user_id = 3) )),2),0) as diff_rate')->whereRaw('post_id IN (SELECT `post_id` FROM `mf_memo_user_rate_detail` WHERE `user_id` = 3)')->where('user_id' ,'!=','3')->where('rating' ,'!=','0.0')->groupBy('t.user_id')->get();

        Memo::select(['experiences.id', 'experiences.title', 'experiences.description', 'experiences.image'])->where('experiences.title', 'like', '%' . $search . '%')->orderBy('experiences.recomended', 'ASC')->get();
        dd(DB::getQueryLog());

        //var_dump($check);
    }
}
