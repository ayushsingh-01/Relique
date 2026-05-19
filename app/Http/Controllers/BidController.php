<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BidController extends Controller
{
    public function store(Request $request, Auction $auction)
    {
        if ($auction->seller_id === Auth::id()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'You cannot bid on your own auction.'], 403);
            }
            return back()->with('error', 'You cannot bid on your own auction.');
        }

        $now = now();
        $endTime = \Carbon\Carbon::parse($auction->end_time);

        // Determine if the auction has expired
        $isExpired = false;
        if ($auction->status !== 'active') {
            $isExpired = true;
        } else {
            if ($auction->has_overtime_started) {
                // If overtime already started, it expires exactly at the database end_time
                if ($now->greaterThan($endTime)) {
                    $isExpired = true;
                }
            } else {
                // If overtime hasn't started yet, it expires 60 seconds after the scheduled end_time
                if ($now->timestamp > $endTime->timestamp + 60) {
                    $isExpired = true;
                }
            }
        }

        if ($isExpired) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'This auction has ended.'], 422);
            }
            return back()->with('error', 'This auction has ended.');
        }

        $request->validate([
            'amount' => ['required', 'numeric', 'max:99999999', function ($attribute, $value, $fail) use ($auction) {
                if ($value <= $auction->current_price) {
                    $fail('Your bid must be higher than the current price ($' . $auction->current_price . ').');
                }
            }],
        ]);

        $bid = DB::transaction(function () use ($request, $auction, $now) {
            $bid = Bid::create([
                'auction_id' => $auction->id,
                'buyer_id' => Auth::id(),
                'amount' => $request->amount,
            ]);

            // Calculate the new end_time and overtime status
            $endTime = \Carbon\Carbon::parse($auction->end_time);
            $newEndTime = $auction->end_time;
            $hasOvertimeStarted = (bool) $auction->has_overtime_started;

            if (!$hasOvertimeStarted) {
                // Check if we are in the last minute of the main auction, or already past the main auction end time (within the 60s grace period)
                if ($endTime->timestamp - $now->timestamp <= 60) {
                    $hasOvertimeStarted = true;
                    $newEndTime = $now->copy()->addSeconds(60);
                }
            } else {
                // If overtime is already active, any bid resets the timer to 60s from now
                $newEndTime = $now->copy()->addSeconds(60);
            }

            // Update auction price, end_time, and overtime status
            DB::table('auctions')
                ->where('id', $auction->id)
                ->update([
                    'current_price' => $request->amount,
                    'end_time' => $newEndTime,
                    'has_overtime_started' => $hasOvertimeStarted ? 1 : 0,
                    'updated_at' => $now,
                ]);

            // Refresh model values
            $auction->current_price = $request->amount;
            $auction->end_time = $newEndTime;
            $auction->has_overtime_started = $hasOvertimeStarted;

            // Dispatch Event for WebSockets
            broadcast(new \App\Events\BidPlaced($auction, $bid, $newEndTime->toIso8601String()))->toOthers();
            broadcast(new \App\Events\AuctionUpdated($auction))->toOthers();

            return $bid;
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Bid placed successfully!',
                'amount' => number_format($bid->amount, 2),
                'buyer_name' => $bid->buyer->name
            ]);
        }

        return back()->with('success', 'Bid placed successfully!');
    }

    public function buyItNow(Request $request, Auction $auction)
    {
        if ($auction->seller_id === Auth::id()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'You cannot buy your own auction.'], 403);
            }
            return back()->with('error', 'You cannot buy your own auction.');
        }

        $now = now();
        $endTime = \Carbon\Carbon::parse($auction->end_time);

        // Determine if the auction has expired
        $isExpired = false;
        if ($auction->status !== 'active') {
            $isExpired = true;
        } else {
            if ($auction->has_overtime_started) {
                if ($now->greaterThan($endTime)) {
                    $isExpired = true;
                }
            } else {
                if ($now->timestamp > $endTime->timestamp + 60) {
                    $isExpired = true;
                }
            }
        }

        if ($isExpired) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'This auction has ended.'], 422);
            }
            return back()->with('error', 'This auction has ended.');
        }

        if (!$auction->buy_it_now_price) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'This auction does not have a Buy It Now price.'], 422);
            }
            return back()->with('error', 'This auction does not have a Buy It Now price.');
        }

        DB::transaction(function () use ($auction) {
            $bid = Bid::create([
                'auction_id' => $auction->id,
                'buyer_id' => Auth::id(),
                'amount' => $auction->buy_it_now_price,
            ]);

            $auction->update([
                'current_price' => $auction->buy_it_now_price,
                'end_time' => now(),
                'status' => 'ended'
            ]);

            // Broadcast the auction end to everyone instantly
            broadcast(new \App\Events\BidPlaced($auction, $bid, now()->toIso8601String()))->toOthers();
            broadcast(new \App\Events\AuctionUpdated($auction))->toOthers();
        });

        // Send email to winner (handled synchronously or queued)
        try {
            \Illuminate\Support\Facades\Mail::to(Auth::user())->send(new \App\Mail\AuctionWon($auction, Auth::user()));
        } catch (\Exception $e) {
            // Log if email sending fails but don't crash the purchase transaction
            \Illuminate\Support\Facades\Log::error("Failed sending buyout email: " . $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'You successfully bought the item!',
                'current_price' => number_format($auction->buy_it_now_price, 2)
            ]);
        }

        return back()->with('success', 'You successfully bought the item!');
    }
}
