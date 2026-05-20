@extends('layouts.app')

@section('content')
<div style="max-width: 600px; margin: 2rem auto; background: var(--bg-panel); padding: 2rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
    <h2 style="margin-bottom: 1.5rem;">Edit Profile</h2>

    <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="form-group" style="text-align: center; margin-bottom: 2rem;">
            @if($user->avatar_path)
                <img src="{{ str_starts_with($user->avatar_path, 'http') ? $user->avatar_path : Storage::url($user->avatar_path) }}" alt="{{ $user->name }}" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin-bottom: 1rem; border: 3px solid var(--border-color);">
            @endif
            <div>
                <label class="form-label">Profile Picture</label>
                <input type="file" name="avatar" class="form-control" accept="image/*">
                @error('avatar')<span style="color: var(--accent-alert); font-size: 0.875rem;">{{ $message }}</span>@enderror
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Bio (Tell about yourself)</label>
            <textarea name="bio" class="form-control" rows="5" placeholder="I have been selling vintage electronics for 10 years...">{{ old('bio', $user->bio) }}</textarea>
            @error('bio')<span style="color: var(--accent-alert); font-size: 0.875rem;">{{ $message }}</span>@enderror
        </div>

        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
            <a href="{{ route('profile.show', $user) }}" class="btn btn-outline" style="flex: 1; text-align: center;">Cancel</a>
            <button type="submit" class="btn btn-primary" style="flex: 2;">Save Profile</button>
        </div>
    </form>
</div>
@endsection
