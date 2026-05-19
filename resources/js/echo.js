import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const pusherKey = document.querySelector('meta[name="pusher-key"]')?.getAttribute('content') || import.meta.env.VITE_PUSHER_APP_KEY;
const pusherCluster = document.querySelector('meta[name="pusher-cluster"]')?.getAttribute('content') || import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1';

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: pusherKey,
    cluster: pusherCluster,
    wsHost: `ws-${pusherCluster}.pusher.com`,
    wsPort: 80,
    wssPort: 443,
    forceTLS: true,
    enabledTransports: ['ws', 'wss'],
});
