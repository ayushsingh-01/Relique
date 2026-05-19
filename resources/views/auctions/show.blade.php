@extends('layouts.app')

@section('content')
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; margin-top: 2rem;">
    
    <!-- Image Section -->
    <div>
        @if($auction->image_path)
            <img src="{{ str_starts_with($auction->image_path, 'http') ? $auction->image_path : Storage::url($auction->image_path) }}" alt="{{ $auction->title }}" style="width: 100%; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg);">
        @else
            <div style="width: 100%; height: 400px; background: var(--bg-panel); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                No Image Available
            </div>
        @endif
    </div>

    <!-- Details Section -->
    <div style="background: var(--bg-panel); padding: 2rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <h1>{{ $auction->title }}</h1>
            <span class="badge {{ $auction->status === 'active' ? 'badge-active' : 'badge-ended' }}">
                {{ ucfirst($auction->status) }}
            </span>
        </div>
        
        @php
            $avgRating = $auction->seller->reviewsReceived()->avg('rating') ?? 0;
        @endphp
        <p style="color: var(--text-muted); margin-bottom: 2rem;">Sold by: 
            <a href="{{ route('profile.show', $auction->seller) }}" style="color: var(--accent-primary); font-weight: 600; text-decoration: none;">
                {{ $auction->seller->name ?? 'Unknown' }}
            </a>
            @if($avgRating > 0)
                <span style="color: #fbbf24; margin-left: 0.5rem;">⭐️ {{ number_format($avgRating, 1) }}/5</span>
            @endif
        </p>
        
        <div style="background: var(--bg-dark); padding: 1.5rem; border-radius: var(--radius-md); margin-bottom: 2rem; text-align: center;">
            <p style="color: var(--text-muted); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 1px;">Current Bid</p>
            <div style="font-size: 3rem; font-weight: 800; color: var(--accent-primary); line-height: 1.2;">
                ${{ number_format($auction->current_price, 2) }}
            </div>
            
            <p style="color: var(--accent-alert); font-weight: 600; margin-top: 0.5rem;" id="countdown" data-end-time="{{ \Carbon\Carbon::parse($auction->end_time)->toIso8601String() }}" data-overtime="{{ $auction->has_overtime_started ? '1' : '0' }}">
                Ends: {{ \Carbon\Carbon::parse($auction->end_time)->format('M d, Y H:i:s') }}
            </p>
        </div>

        @if($auction->status === 'active' && ($auction->has_overtime_started ? now()->lessThan($auction->end_time) : now()->lessThan($auction->end_time->copy()->addSeconds(60))))
            @auth
                @if($auction->seller_id !== Auth::id())
                    <form action="{{ route('bids.store', $auction) }}" method="POST" style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                        @csrf
                        <div style="flex: 1;">
                            <input type="number" name="amount" class="form-control" step="0.01" min="{{ $auction->current_price + 0.01 }}" placeholder="Enter bid amount" required>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 150px;">Place Bid</button>
                    </form>
                    
                    @if($auction->buy_it_now_price)
                        <form action="{{ route('bids.buyItNow', $auction) }}" method="POST" style="margin-bottom: 1rem;">
                            @csrf
                            <button type="submit" class="btn btn-primary" style="width: 100%; background-color: var(--accent-purple); border-color: var(--accent-purple);">
                                Buy It Now for ${{ number_format($auction->buy_it_now_price, 2) }}
                            </button>
                        </form>
                    @endif
                @else
                    <div class="alert alert-error" style="text-align: center;">You cannot bid on your own auction.</div>
                @endif
            @else
                <div style="text-align: center;">
                    <a href="{{ route('login') }}" class="btn btn-primary" style="width: 100%;">Login to Bid</a>
                </div>
            @endauth
        @else
            <div class="alert alert-error" style="text-align: center; margin-bottom: 1rem;">This auction has ended.</div>
            @auth
                @php
                    $highestBid = $auction->bids()->orderBy('amount', 'desc')->first();
                    $isWinner = $highestBid && $highestBid->buyer_id === Auth::id();
                @endphp
                @if($isWinner)
                    <div style="text-align: center; margin-top: 1rem;">
                        <h3 style="color: var(--accent-success); margin-bottom: 1rem;">Congratulations! You won this auction. Please check your email to complete the payment.</h3>
                    </div>
                @endif
            @endauth
        @endif

        <h3 style="margin-top: 3rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Description</h3>
        <p style="margin-top: 1rem; color: var(--text-muted);">{{ $auction->description }}</p>

        <h3 style="margin-top: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Bid History</h3>
        <div style="margin-top: 1rem; max-height: 200px; overflow-y: auto;">
            @forelse($auction->bids->sortByDesc('created_at') as $bid)
                <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border-color);">
                    <span style="color: var(--text-muted);">{{ $bid->buyer->name }}</span>
                    <span style="font-weight: 600; color: var(--accent-primary);">${{ number_format($bid->amount, 2) }}</span>
                </div>
            @empty
                <p style="color: var(--text-muted);">No bids yet. Be the first!</p>
            @endforelse
        </div>
    </div>
</div>

<!-- Scripts for Real-Time Bidding -->
@vite(['resources/js/app.js'])
<script type="module">
    if (window.Echo) {
        window.Echo.channel('auction.{{ $auction->id }}')
            .listen('.bid.placed', (e) => {
                // Update Current Price Display
                const priceDiv = document.querySelector('.card-price, div[style*="font-size: 3rem"]');
                if (priceDiv) {
                    priceDiv.innerHTML = '$' + e.formatted_amount;
                    priceDiv.style.color = '#10b981'; // flash green
                    setTimeout(() => priceDiv.style.color = 'var(--accent-primary)', 1000);
                }

                // Update Timer if new end time is provided
                if (e.new_end_time) {
                    const countdownEl = document.getElementById('countdown');
                    if (countdownEl) {
                        countdownEl.setAttribute('data-end-time', e.new_end_time);
                    }
                }

                // Update Bid History List
                const historyContainer = document.querySelector('div[style*="max-height: 200px"]');
                if (historyContainer) {
                    const noBidsMsg = historyContainer.querySelector('p');
                    if (noBidsMsg) noBidsMsg.remove();

                    // Check if this bid amount is already displayed to avoid duplicates
                    const existingBids = Array.from(historyContainer.querySelectorAll('span[style*="font-weight: 600"]'))
                        .map(span => span.textContent.replace('$', '').trim());
                    if (!existingBids.includes(parseFloat(e.amount).toFixed(2))) {
                        const newBidHtml = `
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border-color); background: rgba(16, 185, 129, 0.1); transition: background 1s;">
                                <span style="color: var(--text-muted);">${e.buyer_name}</span>
                                <span style="font-weight: 600; color: var(--accent-primary);">$${e.formatted_amount}</span>
                            </div>
                        `;
                        historyContainer.insertAdjacentHTML('afterbegin', newBidHtml);
                        
                        // Remove highlight after a second
                        setTimeout(() => {
                            if (historyContainer.firstElementChild) {
                                historyContainer.firstElementChild.style.background = 'transparent';
                            }
                        }, 1000);
                    }
                }
            })
            .listen('.auction.updated', (e) => {
                // Update status badge
                const badge = document.querySelector('.badge');
                if (badge) {
                    badge.className = `badge badge-${e.status}`;
                    badge.innerHTML = e.status.charAt(0).toUpperCase() + e.status.slice(1);
                }

                // Update end time and overtime attributes
                const countdownEl = document.getElementById('countdown');
                if (countdownEl) {
                    countdownEl.setAttribute('data-end-time', e.end_time);
                    countdownEl.setAttribute('data-overtime', e.has_overtime_started ? '1' : '0');
                }

                // Update Current Price Display
                const priceDiv = document.querySelector('.card-price, div[style*="font-size: 3rem"]');
                if (priceDiv) {
                    priceDiv.innerHTML = '$' + e.current_price;
                }

                // If auction ended, hide forms, display ended alert, and reload after a delay
                if (e.status === 'ended') {
                    const forms = document.querySelectorAll('form[action*="bids.store"], form[action*="bids.buyItNow"]');
                    forms.forEach(f => f.remove());

                    let alertDiv = document.querySelector('.alert-error');
                    if (!alertDiv) {
                        const alertHtml = `<div class="alert alert-error" style="text-align: center; margin-bottom: 1rem;">This auction has ended.</div>`;
                        const detailsPanel = document.querySelector('div[style*="background: var(--bg-panel)"]');
                        if (detailsPanel) {
                            const contentAnchor = document.querySelector('h3[style*="margin-top: 3rem"]');
                            if (contentAnchor) {
                                contentAnchor.insertAdjacentHTML('beforebegin', alertHtml);
                            }
                        }
                    }
                    setTimeout(() => window.location.reload(), 2000);
                }
            });
    }

    // AJAX Bidding Form Submission
    const bidForm = document.querySelector('form[action*="bids.store"]');
    if (bidForm) {
        bidForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = bidForm.querySelector('button[type="submit"]');
            const amountInput = bidForm.querySelector('input[name="amount"]');
            const originalBtnText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Placing...';

            const csrfTokenEl = document.querySelector('meta[name="csrf-token"]');
            const token = csrfTokenEl ? csrfTokenEl.getAttribute('content') : '';

            fetch(bidForm.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({
                    amount: amountInput.value
                })
            })
            .then(response => response.json().then(data => ({ status: response.status, data })))
            .then(({ status, data }) => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;

                if (status === 200) {
                    amountInput.value = '';
                } else {
                    if (data.errors && data.errors.amount) {
                        alert(data.errors.amount[0]);
                    } else {
                        alert(data.error || data.message || 'Failed to place bid.');
                    }
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                console.error(err);
                alert('An error occurred while placing the bid.');
            });
        });
    }

    // AJAX Buy It Now Form Submission
    const buyForm = document.querySelector('form[action*="bids.buyItNow"]');
    if (buyForm) {
        buyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to buy this item now at the buyout price? This will immediately end the auction.')) return;

            const submitBtn = buyForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Buying...';

            const csrfTokenEl = document.querySelector('meta[name="csrf-token"]');
            const token = csrfTokenEl ? csrfTokenEl.getAttribute('content') : '';

            fetch(buyForm.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                }
            })
            .then(response => response.json().then(data => ({ status: response.status, data })))
            .then(({ status, data }) => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;

                if (status === 200) {
                    alert(data.message || 'Purchased successfully!');
                    window.location.reload();
                } else {
                    alert(data.error || data.message || 'Failed to process buyout.');
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                console.error(err);
                alert('An error occurred during buyout.');
            });
        });
    }

    // Timer Logic
    function updateTimer() {
        const countdownEl = document.getElementById('countdown');
        if (!countdownEl) return;

        const endTimeStr = countdownEl.getAttribute('data-end-time');
        const overtimeVal = countdownEl.getAttribute('data-overtime');
        if (!endTimeStr) return;

        const endTime = new Date(endTimeStr).getTime();
        const now = new Date().getTime();
        const distance = endTime - now;
        const isOvertime = overtimeVal === '1';

        if (distance < 0) {
            if (!isOvertime) {
                const secondsPast = Math.floor(Math.abs(distance) / 1000);
                if (secondsPast < 60) {
                    const secondsLeft = 60 - secondsPast;
                    countdownEl.innerHTML = `Overtime: ${secondsLeft}s`;
                    countdownEl.style.color = '#ef4444';
                    countdownEl.style.fontSize = '1.25rem';
                    return;
                }
            }

            if (countdownEl.innerHTML !== "Auction Ended") {
                countdownEl.innerHTML = "Auction Ended";
                countdownEl.style.color = "var(--text-muted)";
                const forms = document.querySelectorAll('form[action*="bids.store"], form[action*="bids.buyItNow"]');
                forms.forEach(f => f.remove());
                setTimeout(() => window.location.reload(), 2000);
            }
            return;
        }

        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        if (isOvertime || distance <= 60000) {
            const secondsLeft = Math.floor(distance / 1000);
            countdownEl.innerHTML = `Ending in: ${secondsLeft}s`;
            countdownEl.style.color = '#ef4444';
            countdownEl.style.fontSize = '1.25rem';
        } else {
            countdownEl.innerHTML = `Ends in: ${days}d ${hours}h ${minutes}m ${seconds}s`;
            countdownEl.style.color = 'var(--accent-alert)';
            countdownEl.style.fontSize = '1rem';
        }
    }

    setInterval(updateTimer, 1000);
    updateTimer();
</script>
@endsection
