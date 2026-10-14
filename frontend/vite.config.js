import process from 'node:process'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    // Activé par docker/compose.yaml : sous Docker Desktop, les fichiers montés depuis
    // Windows ne déclenchent pas d'événements, il faut les surveiller par polling
    watch: { usePolling: process.env.VITE_USE_POLLING === 'true' },
  },
})
