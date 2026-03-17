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
        host: '0.0.0.0', // Mengizinkan akses dari luar container
        port: 5173,
        strictPort: true, // Memaksa Vite tetap di port 5173
        hmr: {
            host: 'localhost', // Browser Windows/WSL mengakses via localhost
            port: 5173,        // Samakan dengan server.port agar konsisten
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
