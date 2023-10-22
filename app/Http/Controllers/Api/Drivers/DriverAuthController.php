<?php

namespace App\Http\Controllers\Api\Drivers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Drivers\CaptionResources;
use App\Models\Captain;
use App\Models\CaptionActivity;
use App\Models\Traits\Api\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;


class DriverAuthController extends Controller
{
    use ApiResponseTrait;

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:captain-api', ['except' => ['refresh', 'checkPhone', 'login', 'register', 'login_phone', 'restPassword']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {

            return $this->errorResponse($validator->errors(), 422);
        }
        if (!$token = auth('captain-api')->attempt($validator->validated(), ['exp' => Carbon::now()->addDays(7300)->timestamp])) {
            return $this->errorResponse('Unauthorized', 422);
        }

        if (isset($request->fcm_token)) {
            $information = Captain::where('email', $request->email)->first();
            $information->update([
                'fcm_token' => $request->fcm_token,
            ]);
        }


        $information2 = Captain::where('email', $request->email)->first();
        $information2->update([
            'fcm_token' => $request->fcm_token,
        ]);

        DB::table('personal_access_tokens')->updateOrInsert([
            'tokenable_id' => $information2->id,
        ], [
            'tokenable_type' => 'App\Models\Captain',
            'tokenable_id' => $information2->id,
            'name' => $information2->name,
            'token' => $token,
            'expires_at' => auth('captain-api')->factory()->getTTL() * 60,
        ]);

        return $this->createNewToken($token);
    }

    public function login_phone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|numeric',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {

            return $this->errorResponse($validator->errors(), 422);
        }
        if (!$token = auth('captain-api')->attempt($validator->validated(), ['exp' => Carbon::now()->addDays(7300)->timestamp])) {
            return $this->errorResponse('Unauthorized', 422);
        }
        if (isset($request->fcm_token)) {
            $information = Captain::where('phone', $request->phone)->first();
            $information->update([
                'fcm_token' => $request->fcm_token,
            ]);
        }

        $information2 = Captain::where('phone', $request->phone)->first();
        DB::table('personal_access_tokens')->updateOrInsert([
            'tokenable_id' => $information2->id,
        ], [
            'tokenable_type' => 'App\Models\Captain',
            'tokenable_id' => $information2->id,
            'name' => $information2->name,
            'token' => $token,
            'expires_at' => auth('captain-api')->factory()->getTTL() * 60,
        ]);


        return $this->createNewToken($token);
    }

    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:captains',
            'phone' => 'required|numeric|unique:captains',
            'gender' => 'required|in:male,female',
            'country_id' => 'required|exists:countries,id',
            'password' => 'required|string|min:6',

        ]);
        if ($validator->fails()) {
            return $this->errorResponse($validator->errors(), 400);
        }
        $user = Captain::create(array_merge(
            $validator->validated(),
            [
                'password' => bcrypt($request->password),
                'admin_id' => 1,
            ]
        ));

        return $this->login_phone($request);

//        return $this->successResponse(new CaptionResources($user), 'data created Successfully', 200);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse 
     */
    public function logout()
    {
        Captain::findorfail(auth('captain-api')->id())->update([
            'fcm_token' => null,
        ]);
        CaptionActivity::where('captain_id', auth('captain-api')->id())->update([
            'status_captain' => 'inactive'
        ]);
        auth('captain-api')->logout();
        return response()->json(['message' => 'User successfully signed out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        $oldToken = auth('captain-api')->getToken();
        if ($oldToken) {
            $token = $oldToken->get();
            $tokens = DB::table('personal_access_tokens')->where('token', $token)->first();
            return $this->createNewToken($tokens);
        }

    }


    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function userProfile()
    {
        return $this->successResponse(new CaptionResources(auth('captain-api')->user()), 'data return successfully');
    }

    /**
     * Get the token array structure.
     *
     * @param string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function createNewToken($token)
    {

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('captain-api')->factory()->getTTL() * 60,
            'user' => new CaptionResources(auth('captain-api')->user())
        ]);


    }


//    public function editProfile(Request $request)
//    {
//        $user = Captain::where('id', auth('captain-api')->id())->first();
//
//        if ($user) {
//
//            $user->update([
//                'name' => $request->name ?? null,
//                'email' => $request->email ?? null,
//                'gender' => $request->gender ?? null,
//            ]);
//
//            if ($request->hasFile('avatar')) {
//                $imageName = time() . '.' . $request->avatar->extension();
//                // Public Folder
//                $files = $request->avatar->move('users/' . $user->id, $imageName);
//            }
//
//            $UserProfile = UserProfile::where('user_id', auth('captain-api')->id())->first();
//
//            if ($UserProfile) {
//                $UserProfile->update([
//                    'bio' => $request->bio,
//                    'address' => $request->address,
//                    'user_id' => auth('captain-api')->id(),
//                    'avatar' => $files ?? null,
//                ]);
//            } else {
//                UserProfile::create([
//                    'bio' => $request->bio,
//                    'address' => $request->address,
//                    'user_id' => auth('captain-api')->id(),
//                    'avatar' => $files ?? null,
//                ]);
//            }
//
//
//            return $this->successResponse('', 'data Updated successfully');
//
//        } else {
//            return $this->errorResponse('Error', 400);
//        }
//    }


    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:6',

        ]);
        if ($validator->fails()) {
            return $this->errorResponse($validator->errors(), 400);
        }
        $user = Captain::where('id', auth('captain-api')->id())->first();


        if ($user) {
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            return $this->successResponse('', 'Change password success');

        }
        return $this->errorResponse('Error');
    }


    public function restPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|exists:captains,phone',
            'password' => 'required',

        ]);
        if ($validator->fails()) {
            return $this->errorResponse($validator->errors(), 400);
        }

        $checkUser = Captain::where('phone', $request->phone)->first();
        if (!$checkUser) {
            return $this->errorResponse('The Captain Not Find');
        }

        $checkUser->update([
            'password' => Hash::make($request->password),
        ]);

        return $this->successResponse('', 'Successfully Rest Password');
    }


    public function checkPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|numeric|exists:users,phone',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors(), 400);
        }

        $checkUser = Captain::where('phone', $request->phone)->first();

        if (!$checkUser) {
            return $this->errorResponse('The User Not Find');
        }

        return $this->successResponse(new CaptionResources($checkUser), 'User Already Expecting');
    }

//    public function editImages(Request $request)
//    {
//        $user = Captain::where('id', auth('captain-api')->id())->first();
//        $UserProfile = UserProfile::where('user_id', auth('captain-api')->id())->first();
//        if ($request->hasFile('avatar')) {
//            $imageName = time() . '.' . $request->avatar->extension();
//            // Public Folder
//            $files = $request->avatar->move('users/' . $user->id, $imageName);
//        }
//
//        if ($UserProfile) {
//            $UserProfile->update([
//                'avatar' => $files ?? null,
//            ]);
//
//            return $this->successResponse('', 'Successfully Upload Images');
//        }
//        return $this->errorResponse('The User Not Find');
//
//    }
}
