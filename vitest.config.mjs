import { defineConfig } from 'vitest/config';
import path from 'path';
import { fileURLToPath } from 'url';

const dir = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
  resolve: {
    // Match the app's "@" → resources/js so tests import libs the same way.
    alias: { '@': path.resolve(dir, 'resources/js') },
  },
  test: {
    environment: 'node',
    include: ['tests/frontend/**/*.spec.js'],
  },
});
