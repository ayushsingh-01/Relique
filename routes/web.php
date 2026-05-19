<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ReviewController;

Route::get('/', [AuctionController::class, 'index'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // Auction creation (Seller)
    Route::get('/auctions/create', [AuctionController::class, 'create'])->name('auctions.create');
    Route::post('/auctions', [AuctionController::class, 'store'])->name('auctions.store');
    
    // Bidding (Buyer)
    Route::post('/auctions/{auction}/bids', [BidController::class, 'store'])->name('bids.store');
    Route::post('/auctions/{auction}/buy-it-now', [BidController::class, 'buyItNow'])->name('bids.buyItNow');
    
    // Dashboards
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/admin/dashboard', [DashboardController::class, 'admin'])->name('dashboard.admin');

    // Checkout (Stripe)
    Route::get('/checkout/{auction}', [CheckoutController::class, 'session'])->name('checkout.session');
    Route::get('/checkout/{auction}/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/{auction}/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');

    // Reviews
    Route::post('/auctions/{auction}/reviews', [ReviewController::class, 'store'])->name('reviews.store');

    // Profile Edit
    Route::get('/profile/edit', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
});

// View Auction Details (Open to all)
Route::get('/auctions/{auction}', [AuctionController::class, 'show'])->name('auctions.show');

// Public User Profile
Route::get('/users/{user}', [\App\Http\Controllers\ProfileController::class, 'show'])->name('profile.show');

// Helper route to run migrations from the browser
Route::get('/setup-database', function () {
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    return 'Database tables created successfully! <a href="/">Go back to Home</a>';
});

// Helper route to create test users
Route::get('/create-test-accounts', function () {
    \App\Models\User::firstOrCreate(
        ['email' => 'buyer@gmail.com'],
        ['name' => 'Test Buyer', 'password' => \Illuminate\Support\Facades\Hash::make('password'), 'role' => 'user']
    );
    \App\Models\User::firstOrCreate(
        ['email' => 'seller@gmail.com'],
        ['name' => 'Test Seller', 'password' => \Illuminate\Support\Facades\Hash::make('password'), 'role' => 'user']
    );
    return '<h3>Accounts Created Successfully!</h3>
            <p><b>User 1:</b> buyer@gmail.com <br> <b>Password:</b> password</p>
            <p><b>User 2:</b> seller@gmail.com <br> <b>Password:</b> password</p>
            <br><a href="/login" style="padding: 10px; background: #10b981; color: white; text-decoration: none; border-radius: 5px;">Go to Login</a>';
});

// Helper route to create default categories
Route::get('/seed-categories', function () {
    $categories = ['Electronics', 'Art & Collectibles', 'Vehicles', 'Fashion', 'Real Estate', 'Jewelry'];
    foreach ($categories as $cat) {
        \App\Models\Category::firstOrCreate(
            ['slug' => \Illuminate\Support\Str::slug($cat)],
            ['name' => $cat]
        );
    }
    return '<h3>Categories Created Successfully!</h3>
            <p>The dropdown is now filled with options.</p>
            <br><a href="/auctions/create" style="padding: 10px; background: #10b981; color: white; text-decoration: none; border-radius: 5px;">Go back to Create Auction</a>';
});

// Helper route to serve images without needing storage:link
Route::get('/storage/{path}', function ($path) {
    $fullPath = storage_path('app/public/' . $path);
    if (!file_exists($fullPath)) {
        abort(404);
    }
    
    // Explicitly set headers to avoid caching issues and serve correctly
    return response()->file($fullPath, [
        'Content-Type' => mime_content_type($fullPath),
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]);
})->where('path', '.*');

// Ultimate fix for images: run storage:link programmatically
Route::get('/fix-images', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('storage:link');
        return '<h3>Images Fixed!</h3><p>The storage link was successfully created.</p><a href="/" style="padding: 10px; background: #10b981; color: white; text-decoration: none; border-radius: 5px;">Go back to Home</a>';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});

Route::get('/debug-db', function () {
    $auctions = \App\Models\Auction::with('bids.buyer')->get();
    return response()->json($auctions);
});

Route::get('/debug-broadcast', function () {
    return response()->json([
        'default_connection' => config('broadcasting.default'),
        'pusher_key' => config('broadcasting.connections.pusher.key'),
        'pusher_cluster' => config('broadcasting.connections.pusher.options.cluster'),
        'pusher_host' => config('broadcasting.connections.pusher.options.host'),
        'pusher_port' => config('broadcasting.connections.pusher.options.port'),
        'pusher_scheme' => config('broadcasting.connections.pusher.options.scheme'),
        'pusher_app_id' => config('broadcasting.connections.pusher.app_id'),
        'reverb_app_id' => config('broadcasting.connections.reverb.app_id'),
    ]);
});
