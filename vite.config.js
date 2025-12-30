import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js', 'resources/css/app.css'],
            refresh: true,
        }),
    ],
    server: {
        https: true, // Habilitar HTTPS para desarrollo
        host: '0.0.0.0', // Escuchar en todas las interfaces de red
        port: 5174, // Asegurarse de usar este puerto
        hmr: { // Simplificar configuración HMR
            host: 'fractal.distritec.cloud',
            protocol: 'wss',
        },
        // cors: {
        //     origin: 'https://beta.fractal.distritec.cloud',
        // },
        // origin: 'https://beta.fractal.distritec.cloud',
    },

      css: {
    postcss: 'postcss.config.cjs', // ó postcss.config.js si lo tienes en ESM
  },
    // 👇 Añade esta sección para exponer las variables de Pusher
   // vite.config.js
    
});