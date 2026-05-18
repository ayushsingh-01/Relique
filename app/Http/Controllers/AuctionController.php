<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Category;
use App\Models\User;
use App\Notifications\NewAuctionCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class AuctionController extends Controller
{
    public function index(Request $request)
    {
        // Lazy-run the command to close expired auctions automatically for local testing
        \Illuminate\Support\Facades\Artisan::call('auctions:close');

        $query = Auction::with('category')->where('status', 'active');

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', $searchTerm)
                  ->orWhere('description', 'like', $searchTerm);
            });
        }

        if ($request->filled('min_price')) {
            $query->where('current_price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('current_price', '<=', $request->max_price);
        }

        if ($request->filled('ending_soon')) {
            $query->orderBy('end_time', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $auctions = $query->paginate(12);
        $categories = Category::all();

        return view('auctions.index', compact('auctions', 'categories'));
    }

    public function show(Auction $auction)
    {
        // Lazy-run the command to close expired auctions automatically for local testing
        \Illuminate\Support\Facades\Artisan::call('auctions:close');
        $auction->refresh(); // Refresh in case this specific auction was closed

        $auction->load(['category', 'seller', 'bids.buyer']);
        return view('auctions.show', compact('auction'));
    }

    public function create()
    {
        
        if (Category::count() === 0) {
            $cats = ['Electronics', 'Art & Collectibles', 'Vehicles', 'Fashion', 'Real Estate', 'Jewelry'];
            foreach ($cats as $cat) {
                Category::firstOrCreate(
                    ['slug' => \Illuminate\Support\Str::slug($cat)],
                    ['name' => $cat]
                );
            }
        }
        
        $categories = Category::all();
        return view('auctions.create', compact('categories'));
    }

    public function store(Request $request)
    {

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'starting_price' => 'required|numeric|min:0.01|max:99999999',
            'buy_it_now_price' => 'nullable|numeric|min:' . ($request->starting_price + 0.01) . '|max:99999999',
            'end_time' => 'required|date|after:now|before:2038-01-01',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('auctions');
        }

        $auction = Auction::create([
            'seller_id' => Auth::id(),
            'category_id' => $request->category_id,
            'title' => $request->title,
            'description' => $request->description,
            'starting_price' => $request->starting_price,
            'buy_it_now_price' => $request->buy_it_now_price,
            'current_price' => $request->starting_price,
            'end_time' => $request->end_time,
            'image_path' => $imagePath,
            'status' => 'active'
        ]);

        // Notify all registered users
        $users = User::all();
        Notification::send($users, new NewAuctionCreated($auction));

        return redirect()->route('auctions.show', $auction)->with('success', 'Auction created successfully!');
    }
}
