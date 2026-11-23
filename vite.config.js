import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const port = Number(env.VITE_PORT) || 5173;

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.ts', 'resources/css/filament/admin/theme.css'],
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            tailwindcss(),
        ],
        server: {
            port,
            strictPort: true,
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
	    host: '0.0.0.0',
            // Sin VITE_HMR_HOST, public/hot apuntaba a 0.0.0.0 y el navegador bloquea esa dirección.
            hmr: { host: process.env.VITE_HMR_HOST || 'localhost' },
        },
    };
});