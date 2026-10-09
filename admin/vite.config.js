import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
// If your fresh Laravel uses Tailwind 4, keep its "@tailwindcss/vite" plugin import/plugin line here too.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/melai.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
