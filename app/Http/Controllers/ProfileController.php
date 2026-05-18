<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(User $user)
    {
        $user->load(['reviewsReceived.buyer', 'auctions' => function ($query) {
            $query->where('status', 'active');
        }]);
        
        return view('profile.show', compact('user'));
    }

    public function edit()
    {
        $user = Auth::user();
        return view('profile.edit', compact('user'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'bio' => 'nullable|string|max:1000',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::delete($user->avatar_path);
            }
            $user->avatar_path = $request->file('avatar')->store('avatars');
        }

        $user->bio = $request->bio;
        $user->save();

        return redirect()->route('profile.show', $user)->with('success', 'Profile updated successfully!');
    }
}
