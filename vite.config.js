import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // host 0.0.0.0: agar Vite listen di semua interface (wajib di Docker)
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            // HMR WebSocket harus mengarah ke localhost
            // karena browser mengakses dari host machine, bukan dari dalam container
            host: 'localhost',
        },
        watch: {
            // Gunakan polling di Docker karena inotify tidak bekerja
            // pada bind-mounted volumes di beberapa OS (terutama macOS/Windows)
            usePolling: true,
            interval: 1000,
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
