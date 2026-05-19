<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="pusher-key" content="{{ env('PUSHER_APP_KEY') }}">
    <meta name="pusher-cluster" content="{{ env('PUSHER_APP_CLUSTER', 'mt1') }}">
    <title>Relique - Premium Auctions</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>
    <nav class="navbar">
        <a href="{{ route('home') }}" class="navbar-brand">Relique</a>
        <div class="nav-links">
            <a href="{{ route('home') }}">Auctions</a>
            
            @auth
                @if(Auth::user()->role === 'admin')
                    <a href="{{ route('dashboard.admin') }}">Admin</a>
                @else
                    <a href="{{ route('auctions.create') }}" class="btn btn-outline" style="padding: 0.5rem 1rem;">Create Auction</a>
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <a href="{{ route('profile.show', Auth::user()) }}">My Profile</a>
                @endif
                
                <form action="{{ route('logout') }}" method="POST" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-alert" style="padding: 0.5rem 1rem;">Logout</button>
                </form>
            @else
                <a href="{{ route('login') }}">Login</a>
                <a href="{{ route('register') }}" class="btn btn-primary" style="padding: 0.5rem 1rem;">Register</a>
            @endauth
        </div>
    </nav>

    <div class="container">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif
        
        @yield('content')
    </div>
</body>
</html>
