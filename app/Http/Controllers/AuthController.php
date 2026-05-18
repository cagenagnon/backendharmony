<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|string|email|max:255|unique:users',
            // Password rules (must mirror frontend validators.js):
            //   - 8-128 characters
            //   - at least one uppercase letter
            //   - at least one lowercase letter
            //   - at least one digit
            //   - at least one allowed special char: @ $ ! % * # ? & _ - + =
            //   - exhaustive set: only [A-Za-z0-9@$!%*#?&_+=−] allowed (no other special chars)
            'password'  => [
                'required',
                'string',
                'min:8',
                'max:128',
                'regex:/[A-Z]/',           // at least one uppercase
                'regex:/[a-z]/',           // at least one lowercase
                'regex:/[0-9]/',           // at least one digit
                'regex:/[@$!%*#?&_\-+=]/', // at least one allowed special char
                'regex:/^[A-Za-z0-9@$!%*#?&_\-+=]+$/', // exhaustive: only allowed chars
            ],
            'telephone' => 'nullable|string|max:30',
        ], [
            'password.min'   => 'Password must be at least 8 characters long.',
            'password.max'   => 'Password must not exceed 128 characters.',
            'password.regex' => 'Password contains invalid characters. Only letters, numbers, and these special characters are allowed: @ $ ! % * # ? & _ - + =',
        ]);

        $phone = $request->input('telephone') ?? $request->input('phone');

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user', // Par défaut
            'phone' => $phone,
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        try {
            if (! $token = JWTAuth::attempt($credentials)) {
                return response()->json(['error' => 'Invalid credentials'], 401);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'Could not create token'], 500);
        }

        return response()->json([
            'user' => auth()->user(),
            'token' => $token,
        ]);
    }

    public function me()
    {
        return response()->json(auth()->user());
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => 'sometimes|string|email|max:255|unique:users,email,'.$user->id,
            'phone'    => 'sometimes|nullable|string|max:30',
            'avatar'   => 'sometimes|nullable|string',
            'password' => [
                'sometimes',
                'nullable',
                'string',
                'min:8',
                'max:128',
                'confirmed',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*#?&_\-+=]/',
                'regex:/^[A-Za-z0-9@$!%*#?&_\-+=]+$/',
            ],
        ], [
            'password.min'   => 'Password must be at least 8 characters long.',
            'password.max'   => 'Password must not exceed 128 characters.',
            'password.regex' => 'Password contains invalid characters. Only letters, numbers, and these special characters are allowed: @ $ ! % * # ? & _ - + =',
        ]);

        $data = $request->only(['name', 'email', 'phone', 'avatar']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json($user->fresh());
    }
}
