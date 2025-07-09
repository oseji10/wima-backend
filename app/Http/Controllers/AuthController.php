<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\Doctors;
use App\Models\Applications;
use App\Models\ApplicationType;
use App\Mail\WelcomeEmail;
use Illuminate\Support\Str;
use Tymon\JWTAuth\JWTAuth;
// use Tymon\JWTAuth\Facades\JWTAuth; // Ensure the facade is imported
use App\Models\RefreshToken;    
use Carbon\Carbon;

use Tymon\JWTAuth\Exceptions\JWTException; // Uncomment if using JWTException




class AuthController extends Controller
{
   

    // use Illuminate\Http\Request;
    // use Illuminate\Support\Facades\Hash;
    // use Illuminate\Validation\ValidationException;
    // use App\Models\User;
    
    protected $jwt;

    public function __construct(JWTAuth $jwt)
    {
        $this->jwt = $jwt;
    }

    public function contacts()
    {
        $users = User::all();
        return response()->json($users);
       
    }

  public function login(Request $request)
{
    $request->validate([
        'username' => 'required',
        'password' => 'required',
    ]);

    // Find user by email or phone number with staff and role relationships
    $user = User::with(['application_type', 'user_role'])
                ->where('email', $request->username)
                ->orWhere('phoneNumber', $request->username)
                ->first();

    if (!$user) {
        throw ValidationException::withMessages([
            'username' => ['No account found with this email or phone number.'],
        ]);
    }

    // Attempt JWT authentication
    $credentials = [
        'email' => $user->email,
        'password' => $request->password,
    ];

    if (!$accessToken = auth('api')->attempt($credentials)) {
        return response()->json(['error' => 'Invalid credentials'], 401);
    }

    // Generate refresh token
    $refreshToken = Str::random(64);
    $user = auth('api')->user()->load(['application_type', 'user_role']); // Reload relationships

    // Store refresh token in database
    RefreshToken::create([
        'user_id' => $user->id,
        'token' => $refreshToken,
        'expires_at' => Carbon::now()->addDays(14),
    ]);

    // Hide sensitive data
    $user->makeHidden(['password']);

    // Return response with cookies
    return response()->json([
        'message' => 'Logged in',
        'firstName' => $user->firstName ?? '',
        'lastName' => $user->lastName ?? '',
        'email' => $user->email ?? '',
        'phoneNumber' => $user->phoneNumber ?? '',
        // 'role' => $user->role ? $user->role->roleName ?? '' : '', // Safe access
        'role' => $user->user_role->roleName ?? '',
        'applicationType' => $user->application_type  ? $user->application_type->typeName ?? '' : null, // Safe access
        // 'lga' => $user->staff && $user->staff->lga ? $user->staff->lga_info->lgaName ?? '' : null, // Safe access
        'access_token' => $accessToken,
    ])
        ->cookie('access_token', $accessToken, 15, null, null, true, true, false, 'strict')
        ->cookie('refresh_token', $refreshToken, 14 * 24 * 60, null, null, true, true, false, 'strict');
}

    public function refresh(Request $request)
    {
        $refreshToken = $request->cookie('refresh_token');

        if (!$refreshToken) {
            return response()->json(['error' => 'Refresh token missing'], 401);
        }

        // Verify refresh token
        $tokenRecord = RefreshToken::where('token', $refreshToken)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$tokenRecord) {
            return response()->json(['error' => 'Invalid or expired refresh token'], 401);
        }

        // Generate new access token
        $user = User::find($tokenRecord->user_id);
        // $newAccessToken = JWTAuth::fromUser($user);
        $newAccessToken = $this->jwt->fromUser($user);
        

        // Optionally, issue a new refresh token and invalidate the old one
        $newRefreshToken = Str::random(64);
        $tokenRecord->update([
            'token' => $newRefreshToken,
            'expires_at' => Carbon::now()->addDays(14),
        ]);

        return response()->json(['message' => 'Token refreshed'])
            ->cookie('access_token', $newAccessToken, 15, null, null, true, true, false, 'strict')
            ->cookie('refresh_token', $newRefreshToken, 14 * 24 * 60, null, null, true, true, false, 'strict');
    }
      

    // Logout
    // public function logout(Request $request)
    // {
    //     $request->user()->tokens()->delete();

    //     return response()->json(['message' => 'Logged out successfully']);
    // }

    public function logout(Request $request)
    {
        $refreshToken = $request->cookie('refresh_token');

        if ($refreshToken) {
            RefreshToken::where('token', $refreshToken)->delete();
        }

        return response()->json(['message' => 'Logged out'])
            ->cookie('access_token', '', -1)
            ->cookie('refresh_token', '', -1);
    }

    // Get authenticated user
    public function user(Request $request)
    {
        return response()->json($request->user());
    }



public function register(Request $request)
{
    // Set default password
    $default_password = strtoupper(Str::random(2)) . mt_rand(1000000000, 9999999999);

    // Create user
    $user = User::create([
        'firstName' => $request->firstName,
        'lastName' => $request->lastName,
        'phoneNumber' => $request->phoneNumber,
        'otherNames' => $request->otherNames,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'role' => $request->role,
        'jambId' => $request->jambId
    ]);

    Log::info('User created:', ['email' => $user->email]);

    // Send email
    try {
        Mail::to($user->email)->send(new WelcomeEmail($user->firstName, $user->lastName, $user->email, $default_password));
        Log::info('Email sent successfully to ' . $user->email);
    } catch (\Exception $e) {
        Log::error('Email sending failed: ' . $e->getMessage());
    }

    // Return response
    return response()->json([
        'message' => "User successfully created",
        'password' => $default_password,
    ]);
}

public function candidateRegister(Request $request)
    {
        try {
            // Validate request data
            $validated = $request->validate([
                'firstName' => 'required|string|max:255',
                'lastName' => 'required|string|max:255',
                'phoneNumber' => 'nullable|string|unique:users,phoneNumber|max:14|regex:/^\+?\d{10,15}$/',
                'otherNames' => 'nullable|string|max:255',
                'email' => 'required|email|unique:users,email|max:255',
                'password' => 'required|string|min:6',
                'applicationType' => 'required|string|exists:application_types,typeId',
                'jambId' => 'nullable|string|unique:users,jambId|max:255',
            ]);

            // Generate applicationId based on applicationType
            $prefix = match ($validated['applicationType']) {
                "1" => 'NDN25',
                "2" => 'BMW25',
                "3" => 'PBN25',
                default => throw new \Exception('Invalid application type'),
            };
            $randomDigits = str_pad(mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);
            $applicationId = $prefix . $randomDigits;

            // Create user
            $user = User::create([
                'firstName' => $validated['firstName'],
                'lastName' => $validated['lastName'],
                'phoneNumber' => $validated['phoneNumber'],
                'otherNames' => $validated['otherNames'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'applicationType' => $validated['applicationType'],
                'role' => 1, // Hardcoded role for candidate
                'jambId' => $validated['jambId'],
            ]);

            // Create application
            $application = Applications::create([
                'userId' => $user->id,
                'applicationId' => $applicationId,
                'applicationType' => $validated['applicationType'],
                'jambId' => $validated['jambId'],
            ]);

            Log::info('User created:', ['email' => $user->email]);

            // Send welcome email
            try {
                Mail::to($user->email)->send(new WelcomeEmail(
                    $user->firstName,
                    $user->lastName,
                    $user->email,
                    $request->password // Consider removing this for security
                ));
                Log::info('Email sent successfully to ' . $user->email);
            } catch (\Exception $e) {
                Log::error('Email sending failed: ' . $e->getMessage());
                // Note: Not failing the request due to email error
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Registration successful! Please check your email for a welcome message.',
                'applicationId' => $applicationId,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Registration failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed due to an unexpected error. Please try again later.',
            ], 500);
        }
    }

    public function changePassword(Request $request)
{
    // Validate input
    $request->validate([
        'currentPassword' => 'required',
        'newPassword' => 'required|min:6', // 'confirmed' ensures newPassword_confirmation is also sent
    ]);

    $user = Auth::user();

    // Check if the current password matches
    if (!Hash::check($request->currentPassword, $user->password)) {
        return response()->json(['message' => 'Current password is incorrect.'], 422);
    }

    // // Only update the fields if they are provided
    // if ($request->has('email')) {
    //     $user->email = $request->email;
    // }
    // if ($request->has('phoneNumber')) {
    //     $user->phoneNumber = $request->phoneNumber;
    // }
    // if ($request->has('firstName')) {
    //     $user->firstName = $request->firstName;
    // }
    // if ($request->has('lastName')) {
    //     $user->lastName = $request->lastName;
    // }

    // Update the user's password
    $user->password = Hash::make($request->newPassword);
    $user->save();

    return response()->json(['message' => 'Password changed successfully.']);
}



public function updateProfile(Request $request)
{
    // Find the patient by ID
    $user = User::where('email', $request->email)->first();

    
    if (!$user) {
        return response()->json([
            'error' => 'User not found',
        ], 404); // HTTP status code 404: Not Found
    }

    
    $data = $request->all();

    
    $user->update($data);

    
    return response()->json([
        'message' => 'User updated successfully',
        'data' => $user,
    ], 200); // HTTP status code 200: OK
}
    
}
