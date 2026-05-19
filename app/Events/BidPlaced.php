<?php

namespace App\Events;

use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BidPlaced implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $auction_id;
    public $amount;
    public $buyer_name;
    public $formatted_amount;
    public $new_end_time;

    public function __construct(Auction $auction, Bid $bid, $newEndTime = null)
    {
        $this->auction_id = $auction->id;
        $this->amount = $bid->amount;
        $this->buyer_name = $bid->buyer->name;
        $this->formatted_amount = number_format($bid->amount, 2);
        $this->new_end_time = $newEndTime ? \Carbon\Carbon::parse($newEndTime)->toIso8601String() : \Carbon\Carbon::parse($auction->end_time)->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('auction.' . $this->auction_id),
        ];
    }
    
    public function broadcastAs(): string
    {
        return 'bid.placed';
    }
}
