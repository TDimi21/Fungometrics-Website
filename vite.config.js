import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import EnvironmentPlugin from 'vite-plugin-environment'

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
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
      EnvironmentPlugin(['API_ENDPOINT'])
    ],
  server: {
    host: '0.0.0.0',
    // Vite binds 0.0.0.0 (all interfaces, e.g. for LAN/device testing), but
    // laravel-vite-plugin's resolveDevServerUrl() writes that literal string
    // into public/hot (and from there into the <script src> tags) unless
    // server.hmr.host overrides it — server.origin does NOT affect this.
    // Browsers (Chrome especially, since the 2024 0.0.0.0-day fix) refuse to
    // load scripts FROM 0.0.0.0 as a destination, which is a blank page with
    // no console error.
    hmr: {
      host: '127.0.0.1',
    },
    watch: {
      usePolling: true,
    },
  },
    resolve: {
        alias: {
            vue: 'vue/dist/vue.runtime.esm-bundler.js',
        },
    },
});
