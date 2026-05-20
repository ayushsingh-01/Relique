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
            if ($user->avatar_path && !str_starts_with($user->avatar_path, 'http')) {
                Storage::delete($user->avatar_path);
            }

            if (env('CLOUDINARY_CLOUD_NAME') && env('CLOUDINARY_CLOUD_NAME') !== 'your_cloud_name') {
                if (env('CLOUDINARY_URL')) {
                    \Cloudinary\Configuration\Configuration::instance(env('CLOUDINARY_URL'));
                } else {
                    \Cloudinary\Configuration\Configuration::instance([
                        'cloud' => [
                            'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                            'api_key'    => env('CLOUDINARY_API_KEY'),
                            'api_secret' => env('CLOUDINARY_API_SECRET'),
                        ],
                    ]);
                }
                $upload = (new \Cloudinary\Api\Upload\UploadApi())->upload($request->file('avatar')->getRealPath());
                $user->avatar_path = $upload['secure_url'];
            } else {
                $user->avatar_path = $request->file('avatar')->store('avatars');
            }
        }

        $user->bio = $request->bio;
        $user->save();

        return redirect()->route('profile.show', $user)->with('success', 'Profile updated successfully!');
    }
}
