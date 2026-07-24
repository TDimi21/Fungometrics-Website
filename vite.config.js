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
    watch: {
      usePolling: true,
    },
  },
    resolve: {
        alias: {
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) return undefined;
                    if (id.includes('/vue/') || id.includes('/vue-router/') || id.includes('/pinia/')) {
                        return 'vendor-vue';
                    }
                    if (id.includes('/apexcharts/') || id.includes('/vue3-apexcharts/')) {
                        return 'vendor-charts';
                    }
                    if (id.includes('/@formkit/') || id.includes('/vest/')) {
                        return 'vendor-forms';
                    }
                    if (id.includes('/sweetalert2/') || id.includes('/@headlessui/')) {
                        return 'vendor-ui';
                    }
                    if (id.includes('/axios/')) return 'vendor-http';
                    return 'vendor';
                },
            },
        },
    },
});

