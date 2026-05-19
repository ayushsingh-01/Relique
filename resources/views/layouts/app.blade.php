<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="pusher-key" content="{{ config('broadcasting.connections.pusher.key') }}">
    <meta name="pusher-cluster" content="{{ config('broadcasting.connections.pusher.options.cluster') }}">
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
    
    <div id="debug-info" style="background: red; color: white; padding: 10px; font-weight: bold; position: fixed; bottom: 0; right: 0; z-index: 9999; max-width: 400px; max-height: 200px; overflow: auto; font-family: monospace; font-size: 12px; line-height: 1.4;">
        Pusher type: <span id="pusher-type">loading</span> | Echo status: <span id="echo-status">loading</span>
        <div id="debug-errors" style="margin-top: 5px; border-top: 1px solid rgba(255,255,255,0.3); padding-top: 5px; display: none;"></div>
    </div>
    <script>
        window.addEventListener('error', function(e) {
            var errContainer = document.getElementById('debug-errors');
            errContainer.style.display = 'block';
            var errDiv = document.createElement('div');
            errDiv.style.color = '#ffe066';
            errDiv.innerText = 'Err: ' + e.message + ' (' + e.filename.split('/').pop() + ':' + e.lineno + ')';
            errContainer.appendChild(errDiv);
        });
        window.addEventListener('unhandledrejection', function(e) {
            var errContainer = document.getElementById('debug-errors');
            errContainer.style.display = 'block';
            var errDiv = document.createElement('div');
            errDiv.style.color = '#ffb3b3';
            errDiv.innerText = 'Promise Err: ' + (e.reason ? e.reason.message || e.reason : 'Unknown rejection');
            errContainer.appendChild(errDiv);
        });
        window.addEventListener('load', function() {
            setTimeout(function() {
                var pusherVal = window.Pusher;
                var echoVal = window.Echo;
                document.getElementById('pusher-type').innerText = typeof pusherVal + (pusherVal ? ' (' + pusherVal.name + ')' : '');
                document.getElementById('echo-status').innerText = echoVal ? 'defined' : 'undefined';
            }, 800);
        });
    </script>
</body>
</html>
