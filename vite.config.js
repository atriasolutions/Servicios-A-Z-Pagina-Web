import { existsSync, unlinkSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { defineConfig } from 'vite'

const hot = resolve('public/hot')

function borrarHot() {
  if (existsSync(hot)) {
    unlinkSync(hot)
  }
}

function pluginHot() {
  return {
    name: 'php-hot',
    configureServer(server) {
      server.httpServer?.once('listening', () => {
        const direccion = server.httpServer?.address()
        const puerto = typeof direccion === 'object' && direccion ? direccion.port : 5173
        writeFileSync(hot, `http://127.0.0.1:${puerto}`)
      })

      const limpiar = () => borrarHot()
      process.on('exit', limpiar)
      process.on('SIGINT', () => {
        limpiar()
        process.exit()
      })
      process.on('SIGTERM', () => {
        limpiar()
        process.exit()
      })
    },
    buildStart() {
      borrarHot()
    },
  }
}

export default defineConfig({
  appType: 'custom',
  publicDir: false,
  plugins: [pluginHot()],
  server: {
    host: '127.0.0.1',
    port: 5173,
    strictPort: true,
    origin: 'http://127.0.0.1:5173',
    cors: {
      origin: 'http://127.0.0.1:8765',
    },
  },
  build: {
    manifest: true,
    outDir: 'public/build',
    emptyOutDir: true,
    rollupOptions: {
      input: 'recursos/app.js',
    },
  },
})
