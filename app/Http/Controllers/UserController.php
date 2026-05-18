<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (! $user || $user->role !== 'admin') {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        return User::all();
    }
}
