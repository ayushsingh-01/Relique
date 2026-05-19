import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const pusherKey = document.querySelector('meta[name="pusher-key"]')?.getAttribute('content') || import.meta.env.VITE_PUSHER_APP_KEY;
const pusherCluster = document.querySelector('meta[name="pusher-cluster"]')?.getAttribute('content') || import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1';

const echoConfig = {
    broadcaster: 'pusher',
    key: pusherKey,
    cluster: pusherCluster,
    forceTLS: true,
};

if (import.meta.env.VITE_PUSHER_HOST) {
    echoConfig.wsHost = import.meta.env.VITE_PUSHER_HOST;
    echoConfig.wsPort = import.meta.env.VITE_PUSHER_PORT ?? 80;
    echoConfig.wssPort = import.meta.env.VITE_PUSHER_PORT ?? 443;
}

window.Echo = new Echo(echoConfig);
