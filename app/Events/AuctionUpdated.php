<?php

namespace App\Events;

use App\Models\Auction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuctionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $auction_id;
    public $status;
    public $end_time;
    public $has_overtime_started;
    public $current_price;

    /**
     * Create a new event instance.
     */
    public function __construct(Auction $auction)
    {
        $this->auction_id = $auction->id;
        $this->status = $auction->status;
        $this->end_time = \Carbon\Carbon::parse($auction->end_time)->toIso8601String();
        $this->has_overtime_started = (bool) $auction->has_overtime_started;
        $this->current_price = number_format($auction->current_price, 2);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('auction.' . $this->auction_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'auction.updated';
    }
}
