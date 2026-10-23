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
      EnvironmentPlugin(['API_ENDPOINT'], { defineOn: 'import.meta.env' })
    ],
  server: {
    host: '127.0.0.1',
    // Development server is local-only; production serves compiled assets.
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
