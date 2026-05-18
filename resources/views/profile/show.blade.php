@extends('layouts.app')

@section('content')
<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 3rem; margin-top: 2rem;">
    <!-- Profile Sidebar -->
    <div style="background: var(--bg-panel); padding: 2rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); text-align: center;">
        @if($user->avatar_path)
            <img src="{{ Storage::url($user->avatar_path) }}" alt="{{ $user->name }}" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; margin-bottom: 1.5rem; border: 4px solid var(--border-color);">
        @else
            <div style="width: 150px; height: 150px; border-radius: 50%; background: var(--bg-dark); color: var(--text-muted); display: flex; align-items: center; justify-content: center; font-size: 3rem; margin: 0 auto 1.5rem auto; border: 4px solid var(--border-color);">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
        @endif

        <h2>{{ $user->name }}</h2>
        <p style="color: var(--text-muted); margin-bottom: 1rem;">Member since {{ $user->created_at->format('M Y') }}</p>

        @php
            $avgRating = $user->reviewsReceived()->avg('rating') ?? 0;
            $reviewCount = $user->reviewsReceived()->count();
        @endphp
        
        <div style="background: var(--bg-dark); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 2rem;">
            @if($reviewCount > 0)
                <div style="font-size: 1.5rem; color: #fbbf24; font-weight: 600;">
                    ⭐️ {{ number_format($avgRating, 1) }} / 5
                </div>
                <div style="color: var(--text-muted); font-size: 0.875rem;">Based on {{ $reviewCount }} reviews</div>
            @else
                <div style="color: var(--text-muted);">No reviews yet</div>
            @endif
        </div>

        @if($user->bio)
            <div style="text-align: left; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                <h4 style="margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.875rem; text-transform: uppercase;">About</h4>
                <p style="line-height: 1.6;">{{ $user->bio }}</p>
            </div>
        @endif

        @if(Auth::id() === $user->id)
            <div style="margin-top: 2rem;">
                <a href="{{ route('profile.edit') }}" class="btn btn-outline" style="width: 100%;">Edit Profile</a>
            </div>
        @endif
    </div>

    <!-- Main Content Area -->
    <div>
        <h2 style="margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Active Listings</h2>
        <div class="card-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); margin-bottom: 3rem;">
            @forelse($user->auctions as $auction)
                <div class="card">
                    @if($auction->image_path)
                        <img src="{{ str_starts_with($auction->image_path, 'http') ? $auction->image_path : Storage::url($auction->image_path) }}" alt="{{ $auction->title }}" class="card-img" style="height: 150px;">
                    @else
                        <div class="card-img" style="height: 150px; display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 0.875rem;">
                            No Image
                        </div>
                    @endif
                    <div class="card-body" style="padding: 1rem;">
                        <h4 style="margin-bottom: 0.5rem; font-size: 1rem;"><a href="{{ route('auctions.show', $auction) }}">{{ $auction->title }}</a></h4>
                        <div style="color: var(--accent-primary); font-weight: 600;">${{ number_format($auction->current_price, 2) }}</div>
                    </div>
                </div>
            @empty
                <p style="color: var(--text-muted); grid-column: 1 / -1;">This user has no active listings.</p>
            @endforelse
        </div>

        <h2 style="margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Recent Reviews</h2>
        <div style="display: grid; gap: 1rem;">
            @forelse($user->reviewsReceived()->latest()->take(5)->get() as $review)
                <div class="card" style="padding: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                        <span style="font-weight: 600;">{{ $review->buyer->name }}</span>
                        <span style="color: #fbbf24;">
                            @for($i=1; $i<=5; $i++)
                                {{ $i <= $review->rating ? '★' : '☆' }}
                            @endfor
                        </span>
                    </div>
                    @if($review->comment)
                        <p style="color: var(--text-muted); font-style: italic;">"{{ $review->comment }}"</p>
                    @endif
                    <div style="margin-top: 1rem; font-size: 0.875rem; color: var(--text-muted);">
                        For item: <a href="{{ route('auctions.show', $review->auction) }}" style="color: var(--accent-primary);">{{ $review->auction->title }}</a>
                    </div>
                </div>
            @empty
                <p style="color: var(--text-muted);">No reviews received yet.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
