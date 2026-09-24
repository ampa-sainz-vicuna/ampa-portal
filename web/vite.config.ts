import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

export default defineConfig({
  plugins: [react()],
  server: {
    // En desarrollo el navegador solo habla con Vite, y Vite reenvía /api a
    // Symfony. Para el navegador todo es el mismo origen, así que no interviene
    // CORS. Vite corre dentro de Docker: "nginx" es el nombre del servicio en
    // docker-compose.yml, no localhost.
    proxy: {
      '/api': {
        target: process.env.API_PROXY_TARGET ?? 'http://nginx',
        changeOrigin: true,
      },
    },
    // Google pide esta cabecera cuando se prueba su botón desde http://localhost
    // (sin https): así el navegador le dice desde qué página se le llama.
    headers: {
      'Referrer-Policy': 'no-referrer-when-downgrade',
    },
    // El código está en el disco de Windows y Vite en un contenedor Linux: los
    // avisos de "fichero cambiado" no cruzan esa frontera. Sin sondeo, guardar
    // un fichero no recarga la página.
    watch: {
      usePolling: true,
    },
  },
  build: {
    // Vite avisa a partir de 500 kB. React y MUI ya suman unos 490, y el logo
    // del AMPA que trae @ampa/ui (25 kB) lo pasa. Trocear el código solo para
    // callar el aviso no ahorraría nada: todo eso hace falta para pintar la
    // primera pantalla. Lo que sí se descarga aparte, la configuración, ya va
    // en su propio trozo (ver App.tsx).
    chunkSizeWarningLimit: 600,
  },
  test: {
    environment: 'jsdom',
    // Los tests que rellenan formularios pasan de los 5 s por defecto cuando
    // corren todos a la vez en Docker sobre Windows.
    testTimeout: 20_000,
    setupFiles: ['./src/test/setup.ts'],
  },
})
