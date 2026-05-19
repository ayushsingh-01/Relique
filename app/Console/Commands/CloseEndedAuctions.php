<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Mail\AuctionWon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CloseEndedAuctions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auctions:close';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Close auctions that have passed their end time and notify the highest bidder';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to process expired auctions...');

        // Find all active auctions whose end_time has passed
        $expiredAuctions = Auction::where('status', 'active')
            ->where('end_time', '<=', now())
            ->get();

        if ($expiredAuctions->isEmpty()) {
            $this->info('No expired auctions found.');
            return;
        }

        foreach ($expiredAuctions as $auction) {
            DB::transaction(function () use ($auction) {
                if (!$auction->has_overtime_started) {
                    // Transition to overtime
                    $auction->update([
                        'has_overtime_started' => true,
                        'end_time' => now()->addSeconds(60),
                    ]);
                    $this->info("Auction ID {$auction->id} entered 60-second overtime.");
                    
                    // Broadcast updated auction details
                    broadcast(new \App\Events\AuctionUpdated($auction));
                } else {
                    // End the auction
                    $auction->update(['status' => 'ended']);

                    // Find the highest bid
                    $highestBid = $auction->bids()->orderBy('amount', 'desc')->first();

                    if ($highestBid) {
                        $winner = $highestBid->buyer;
                        $this->info("Auction ID {$auction->id} ended. Winner: {$winner->email}");

                        // Send email to the winner
                        try {
                            Mail::to($winner)->send(new AuctionWon($auction, $winner));
                        } catch (\Exception $e) {
                            Log::error("Failed sending auction winner email: " . $e->getMessage());
                        }
                    } else {
                        $this->info("Auction ID {$auction->id} ended with no bids.");
                    }

                    // Broadcast ended status to everyone
                    broadcast(new \App\Events\AuctionUpdated($auction));
                }
            });
        }

        $this->info('Finished processing expired auctions.');
    }
}
