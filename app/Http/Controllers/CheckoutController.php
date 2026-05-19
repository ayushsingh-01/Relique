<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
    public function session(Auction $auction)
    {
        if ($auction->status !== 'ended') {
            return back()->with('error', 'You cannot pay for an active auction.');
        }

        $highestBid = $auction->bids()->orderBy('amount', 'desc')->first();
        if (!$highestBid || $highestBid->buyer_id !== Auth::id()) {
            return back()->with('error', 'Only the winning bidder can checkout.');
        }

        if ($auction->payment_status === 'paid') {
            return back()->with('error', 'This auction has already been paid for.');
        }

        if ($auction->current_price > 999999.99) {
            return back()->with('error', 'Amounts of $1,000,000.00 or higher cannot be processed via Stripe. Please contact administration for bank wire instructions.');
        }

        if (!class_exists('\Stripe\Stripe')) {
             return back()->with('error', 'Stripe SDK is not installed. Please run "composer require stripe/stripe-php"');
        }

        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
        \Stripe\Stripe::setVerifySslCerts(false);

        $checkout_session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $auction->current_price * 100, // Stripe expects cents
                    'product_data' => [
                        'name' => 'Winning Bid: ' . $auction->title,
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => route('checkout.success', $auction) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('checkout.cancel', $auction),
        ]);

        return redirect()->away($checkout_session->url);
    }

    public function success(Request $request, Auction $auction)
    {
        $auction->update(['payment_status' => 'paid']);
        
        $buyer = Auth::user();
        \Illuminate\Support\Facades\Mail::to($auction->seller)->send(new \App\Mail\PaymentReceived($auction, $auction->seller, $buyer));
        
        return redirect()->route('dashboard')->with('success', 'Payment successful! The seller has been notified.');
    }

    public function cancel(Auction $auction)
    {
        return redirect()->route('dashboard')->with('error', 'Payment was cancelled.');
    }
}
