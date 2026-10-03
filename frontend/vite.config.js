import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Build directly into Laravel public/app so Apache serves it at /opera/public/app/
// HashRouter is used in src/ => no server rewrite needed for SPA routes.
export default defineConfig({
  plugins: [react()],
  base: './',
  build: {
    outDir: '../public/app',
    emptyOutDir: true,
  },
  server: {
    port: 5174,
    proxy: {
      '/api': 'http://localhost/opera/public',
    },
  },
});
