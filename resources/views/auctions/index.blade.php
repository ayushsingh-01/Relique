@extends('layouts.app')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1>Live Auctions</h1>
    
    <form action="{{ route('home') }}" method="GET" style="display: flex; gap: 1rem; align-items: center;">
        <input type="text" name="search" placeholder="Search items..." class="form-control" style="width: 200px;" value="{{ request('search') }}">
        <select name="category" class="form-control" style="width: auto;">
            <option value="">All Categories</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        
        <input type="number" name="min_price" placeholder="Min $" class="form-control" style="width: 100px;" value="{{ request('min_price') }}">
        <input type="number" name="max_price" placeholder="Max $" class="form-control" style="width: 100px;" value="{{ request('max_price') }}">
        
        <label style="display: flex; align-items: center; gap: 0.5rem;">
            <input type="checkbox" name="ending_soon" value="1" {{ request('ending_soon') ? 'checked' : '' }}>
            Ending Soon
        </label>
        
        <button type="submit" class="btn btn-outline" style="padding: 0.5rem 1rem;">Filter</button>
    </form>
</div>

<div class="card-grid">
    @forelse($auctions as $auction)
        <div class="card">
            @if($auction->image_path)
                <img src="{{ str_starts_with($auction->image_path, 'http') ? $auction->image_path : Storage::url($auction->image_path) }}" alt="{{ $auction->title }}" class="card-img">
            @else
                <div class="card-img" style="display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                    No Image
                </div>
            @endif
            <div class="card-body">
                <span class="badge {{ $auction->status === 'active' ? 'badge-active' : 'badge-ended' }}" style="margin-bottom: 0.5rem;">
                    {{ ucfirst($auction->status) }}
                </span>
                <h3 class="card-title">{{ $auction->title }}</h3>
                <div style="display: flex; flex-direction: column; gap: 0.25rem; margin-bottom: 1rem;">
                    <span style="color: var(--text-muted); font-size: 0.875rem;">
                        Category: {{ $auction->category->name ?? 'None' }}
                    </span>
                    <span style="color: var(--text-muted); font-size: 0.875rem;">
                        Seller: <a href="{{ route('profile.show', $auction->seller) }}" style="color: var(--accent-primary); text-decoration: none;">{{ $auction->seller->name }}</a>
                    </span>
                </div>
                <div class="card-price">${{ number_format($auction->current_price, 2) }}</div>
                
                <p style="color: var(--accent-alert); font-weight: 600; margin-bottom: 1.5rem; font-size: 0.875rem;">
                    Ends: {{ \Carbon\Carbon::parse($auction->end_time)->diffForHumans() }}
                </p>
                
                <a href="{{ route('auctions.show', $auction) }}" class="btn btn-primary" style="width: 100%;">View Auction</a>
            </div>
        </div>
    @empty
        <div style="grid-column: 1 / -1; text-align: center; padding: 4rem; background: var(--bg-panel); border-radius: var(--radius-lg);">
            <h2 style="color: var(--text-muted);">No auctions found</h2>
            <p>Try adjusting your filters or check back later.</p>
        </div>
    @endforelse
</div>

<div style="margin-top: 2rem;">
    {{ $auctions->links() }}
</div>
@endsection
