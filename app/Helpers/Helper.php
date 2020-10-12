<?php 

namespace App\Helpers;

use Mailgun\Mailgun;

use Hash, Exception, Auth, Mail, File, Log, Storage, Setting, DB, Validator;

use App\Admin, App\User, App\ContentCreator, App\StaticPage;

class Helper {

    public static function clean($string) {

        $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.

        return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
    }

    public static function generate_token() {
        
        return Helper::clean(Hash::make(rand() . time() . rand()));
    }

    public static function generate_token_expiry() {

        $token_expiry_hour = Setting::get('token_expiry_hour') ? Setting::get('token_expiry_hour') : 1;
        
        return time() + $token_expiry_hour*3600;  // 1 Hour
    }

    // Note: $error is passed by reference
    
    public static function is_token_valid($entity, $id, $token, &$error) {

        if (
            ( $entity== USER && ($row = User::where('id', '=', $id)->where('token', '=', $token)->first()) )
        ) {

            if ($row->token_expiry > time()) {
                // Token is valid
                $error = NULL;
                return true;
            } else {
                $error = ['success' => false, 'error' => api_error(1003), 'error_code' => 1003];
                return FALSE;
            }
        }

        $error = ['success' => false, 'error' => api_error(1004), 'error_code' => 1004];
        return FALSE;
   
    }

    public static function generate_email_code($value = "") {

        return uniqid($value);
    }

    public static function generate_email_expiry() {

        $token_expiry = Setting::get('token_expiry_hour') ?: 1;
            
        return time() + $token_expiry*3600;  // 1 Hour

    }

    // Check whether email verification code and expiry

    public static function check_email_verification($verification_code , $user_id , &$error) {

        if(!$user_id) {

            $error = tr('user_id_empty');

            return FALSE;

        } else {

            $user_details = User::find($user_id);
        }

        // Check the data exists

        if($user_details) {

            // Check whether verification code is empty or not

            if($verification_code) {

                // Log::info("Verification Code".$verification_code);

                // Log::info("Verification Code".$user_details->verification_code);

                if ($verification_code ===  $user_details->verification_code ) {

                    // Token is valid

                    $error = NULL;

                    // Log::info("Verification CODE MATCHED");

                    return true;

                } else {

                    $error = tr('verification_code_mismatched');

                    // Log::info(print_r($error,true));

                    return FALSE;
                }

            }
                
            // Check whether verification code expiry 

            if ($user_details->verification_code_expiry > time()) {

                // Token is valid

                $error = NULL;

                Log::info(tr('token_expiry'));

                return true;

            } else if($user_details->verification_code_expiry < time() || (!$user_details->verification_code || !$user_details->verification_code_expiry) ) {

                $user_details->verification_code = Helper::generate_email_code();
                
                $user_details->verification_code_expiry = Helper::generate_email_expiry();
                
                $user_details->save();

                // If code expired means send mail to that user

                $subject = tr('verification_code_title');
                $email_data = $user_details;
                $page = "emails.welcome";
                $email = $user_details->email;
                $result = Helper::send_email($page,$subject,$email,$email_data);

                $error = tr('verification_code_expired');

                Log::info(print_r($error,true));

                return FALSE;
            }
       
        }

    }
    
    public static function generate_password() {

        $new_password = time();
        $new_password .= rand();
        $new_password = sha1($new_password);
        $new_password = substr($new_password,0,8);
        return $new_password;
    }

    public static function file_name() {

        $file_name = time();
        $file_name .= rand();
        $file_name = sha1($file_name);

        return $file_name;    
    }

    public static function upload_file($picture , $folder_path = COMMON_FILE_PATH) {

        $file_path_url = "";

        $file_name = Helper::file_name();

        $ext = $picture->getClientOriginalExtension();

        $local_url = $file_name . "." . $ext;

        $inputFile = base_path('public'.$folder_path.$local_url);

        $picture->move(public_path().$folder_path, $local_url);

        $file_path_url = Helper::web_url().$folder_path.$local_url;

        return $file_path_url;
    
    }

    public static function web_url() 
    {
        return url('/');
    }

    public static function delete_file($picture, $path = COMMON_FILE_PATH) {

        if ( file_exists( public_path() . $path . basename($picture))) {

            File::delete( public_path() . $path . basename($picture));
      
        } else {

            return false;
        }  

        return true;    
    }
 
    public static function send_email($page,$subject,$email,$email_data) {

        // check the email notification

        if(Setting::get('is_email_notification') == YES) {

            // Don't check with envfile function. Because without configuration cache the email will not send

            if( config('mail.username') &&  config('mail.password')) {

                try {

                    $site_url=url('/');

                    $isValid = 1;

                    if(envfile('MAIL_MAILER') == 'mailgun' && Setting::get('MAILGUN_PUBLIC_KEY')) {

                        Log::info("isValid - STRAT");

                        # Instantiate the client.

                        $email_address = new Mailgun(Setting::get('MAILGUN_PUBLIC_KEY'));

                        $validateAddress = $email;

                        # Issue the call to the client.
                        $result = $email_address->get("address/validate", ['address' => $validateAddress]);

                        # is_valid is 0 or 1

                        $isValid = $result->http_response_body->is_valid;

                        Log::info("isValid FINAL STATUS - ".$isValid);

                    }

                    if($isValid) {

                        if (Mail::queue($page, ['email_data' => $email_data,'site_url' => $site_url], 
                                function ($message) use ($email, $subject) {

                                    $message->to($email)->subject($subject);
                                }
                        )) {

                            $message = api_success(102);

                            $response_array = ['success' => true , 'message' => $message];

                            return json_decode(json_encode($response_array));

                        } else {

                            throw new Exception(api_error(116) , 116);
                            
                        }

                    } else {

                        $error = api_error();

                        throw new Exception($error, 115);                  

                    }

                } catch(\Exception $e) {

                    $error = $e->getMessage();

                    $error_code = $e->getCode();

                    $response_array = ['success' => false , 'error' => $error , 'error_code' => $error_code];
                    
                    return json_decode(json_encode($response_array));

                }
            
            } else {

                $error = api_error(106);

                $response_array = ['success' => false , 'error' => $error , 'error_code' => 106];
                    
                return json_decode(json_encode($response_array));

            }
        
        } else {
            Log::info("email notification disabled by admin");
        }
    
    }

    public static function push_message($code) {

        switch ($code) {
            case 601:
                $string = tr('push_no_provider_available');
                break;
            default:
                $string = "";
        }

        return $string;

    }  

    // Convert all NULL values to empty strings
    public static function null_safe($input_array) {
 
        $new_array = [];

        foreach ($input_array as $key => $value) {

            $new_array[$key] = ($value == NULL) ? "" : $value;
        }

        return $new_array;
    }

    /**
     * Creating date collection between two dates
     *
     * <code>
     * <?php
     * # Example 1
     * generate_date_range("2014-01-01", "2014-01-20", "+1 day", "m/d/Y");
     *
     * # Example 2. you can use even time
     * generate_date_range("01:00:00", "23:00:00", "+1 hour", "H:i:s");
     * </code>
     *
     * @param string since any date, time or datetime format
     * @param string until any date, time or datetime format
     * @param string step
     * @param string date of output format
     * @return array
     */
    public static function generate_date_range($month = "", $year = "", $step = '+1 day', $output_format = 'd/m/Y', $loops = 2) {

        $month = $set_current_month = $month ?: date('F');

        $year = $set_current_year = $year ?: date('Y');

        $last_month = date('F', strtotime('+'.$loops.' months'));

        $dates = $response = [];

        // $response = new \stdClass;

        $response = [];

        $current_loop = 1;

        while ($current_loop <= $loops) {
        
            $month_response = new \stdClass;

            $timestamp = strtotime($set_current_month.' '.$set_current_year); // Get te timestamp from the given 

            $first_date_of_the_month = date('Y-m-01', $timestamp);

            $last_date_of_month  = date('Y-m-t', $timestamp); 

            $dates = [];

            $set_current_date = strtotime($first_date_of_the_month); // time convertions and set dates

            $last_date_of_month = strtotime($last_date_of_month);  // time convertions and set dates

            // Generate dates based first and last dates

            while( $set_current_date <= $last_date_of_month ) {

                $dates[] = date($output_format, $set_current_date);

                $set_current_date = strtotime($step, $set_current_date);
            }

            $month_response->month = $set_current_month;

            $month_response->total_days = count($dates);

            $month_response->dates = $dates;


            $set_current_month = date('F', strtotime("+".$current_loop." months", $last_date_of_month));

            $set_current_year = date('Y', strtotime("+".$current_loop." months", $last_date_of_month));


            $current_loop++;

            array_push($response, $month_response);

        }

        return $response;
    }

    /**
     *
     * @method get_months()
     *
     * @uses get months list or get month number
     *
     * @created vithya R
     *
     * @updated vithya R
     *
     * @param
     *
     * @return 
     */

    public static function get_months($get_month_name = "") {

        $months = ['01' => 'January', '02' => 'February','03' => 'March','04' => 'April','05' => 'May','06' => 'June','07' => 'July ','08' => 'August','09' => 'September','10' => 'October','11' => 'November','12' => 'December'];

        if($get_month_name) {

            return $months[$get_month_name];

        }

        return $months;
    }

    /**
    * @method generate_referral_code()
    *
    * @uses used to genarate referral code to the owner
    *
    * @created Akshata
    * 
    * @updated 
    *
    * @param $value
    *
    * @return boolean
    */
    public static function generate_referral_code($value = "") {

        $referral_name = strtolower(substr(str_replace(' ','',$value),0,3));
        
        $referral_random_number = rand(100,999);

        $referral_code = $referral_name.$referral_random_number;

        return $referral_code;
    }

    /**
    * @method referral_code_earnings_update()
    *
    * @uses used to update referral bonus to the owner
    *
    * @created vithya R
    * 
    * @updated vithya R
    *
    * @param string $referral_code
    *
    * @return boolean
    */

    public static function referral_code_earnings_update($referral_code) {

        $referrer_user = User::where('referral_code', $referral_code)->first();

        if(!$referrer_user) {

            throw new Exception(api_error(132), 132);
            
        }

        $referrer_bonus = Setting::get('referrer_bonus', 1) ?: 0;

        $referrer_user->referrer_bonus += $referrer_bonus;
        
        $referrer_user->save();

        Log::info("referral_code_earnings_update - ".$referrer_bonus);

        return true;

    }

    public static function get_times() {

        $times = ['flexible' => 'Flexible', '12 AM' => '12 AM(midnight)', '1 AM' => '1 AM', '2 AM' => '2 AM', '3 AM' => '3 AM', '4 AM' => '4 AM', '5 AM' => '5 AM', '6 AM' => '6 AM', '7 AM' => '7 AM', '8 AM' => '8 AM', '9 AM' => '9 AM', '10 AM' => '10 AM', '11 AM' => '11 AM', '12 PM' => '12 PM(Afternoon)', '1 PM' => '1 PM', '2 PM' => '2 PM', '3 PM' => '3 PM', '4 PM' => '4 PM', '5 PM' => '5 PM', '6 PM' => '6 PM', '7 PM' => '7 PM', '8 PM' => '8 PM', '9 PM' => '9 PM', '10 PM' => '10 PM', '11 PM' => '11 PM'];

        return $times;
    }

    public static function custom_validator($request, $request_inputs, $custom_errors = []) {

        $validator = Validator::make($request, $request_inputs, $custom_errors);

        if($validator->fails()) {

            $error = implode(',', $validator->messages()->all());

            throw new Exception($error, 101);
               
        }
    }

     /**
     * @method settings_generate_json()
     *
     * @uses used to update settings.json file with updated details.
     *
     * @created vidhya
     * 
     * @updated vidhya
     *
     * @param -
     *
     * @return boolean
     */
    
    public static function settings_generate_json() {

        $settings = \App\Settings::get();

        $sample_data = [];

        foreach ($settings as $key => $setting_details) {

            $sample_data[$setting_details->key] = $setting_details->value;
        }

        $footer_first_section = StaticPage::select('id as page_id', 'unique_id', 'type as page_type', 'title')->get();

        $footer_second_section = StaticPage::select('id as page_id', 'unique_id', 'type as page_type', 'title')->get();

        $footer_third_section = StaticPage::select('id as page_id', 'unique_id', 'type as page_type', 'title')->get();

        $footer_fourth_section = StaticPage::select('id as page_id', 'unique_id', 'type as page_type', 'title')->get();

        $sample_data['footer_first_section'] = $footer_first_section;

        $sample_data['footer_second_section'] = $footer_second_section;

        $sample_data['footer_third_section'] = $footer_third_section;

        $sample_data['footer_fourth_section'] = $footer_fourth_section;

        // Social logins

        $social_login_keys = ['FB_CLIENT_ID', 'FB_CLIENT_SECRET', 'FB_CALL_BACK' , 'TWITTER_CLIENT_ID', 'TWITTER_CLIENT_SECRET', 'TWITTER_CALL_BACK', 'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_CALL_BACK'];

        $social_logins = \App\Settings::whereIn('key', $social_login_keys)->get();

        $social_login_data = [];

        foreach ($social_logins as $key => $social_login_details) {

            $social_login_data[$social_login_details->key] = $social_login_details->value;
        }

        $sample_data['social_logins'] = $social_login_data;

        $data['data'] = $sample_data;

        $data = json_encode($data);

        $folder_path_name = 'default-json/settings.json';

        Storage::disk('public')->put($folder_path_name, $data);
    }

     /**
     * @method upload_file
     */
    
    public static function storage_upload_file($input_file, $folder_path = COMMON_FILE_PATH) {
       
        $name = Helper::file_name();

        $ext = $input_file->getClientOriginalExtension();

        $file_name = $name.".".$ext;

        $public_folder_path = "public/".$folder_path;

        Storage::putFileAs($public_folder_path, $input_file, $file_name);

        $storage_file_path = $folder_path.$file_name;

        $url = asset(Storage::url($storage_file_path));
    
        return $url;

    }

    /**
     * @method
     * 
     */
    public static function storage_delete_file($url, $folder_path = COMMON_FILE_PATH) {

        $file_name = basename($url);

        $storage_file_path = $folder_path.$file_name;

        Storage::delete($storage_file_path);
    }

    public static function is_you_following($logged_in_user_id, $other_user_id) {

        $check = \App\Follower::where('user_id', $other_user_id)->where('follower_id', $logged_in_user_id)->count();

        return $check ? YES : NO;
    }

}


