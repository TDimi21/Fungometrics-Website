import { afterEach, expect, it, vi } from 'vitest'
import { build } from 'vite'
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'

let directory

afterEach(() => {
  vi.unstubAllEnvs()
  if (directory) rmSync(directory, { recursive: true, force: true })
})

it('embeds the CI API endpoint in the browser bundle without a local env file', async () => {
  vi.stubEnv('API_ENDPOINT', 'https://app.fmtrx.com/api/')
  vi.stubEnv('VITE_API_ENDPOINT', undefined)
  directory = mkdtempSync(path.join(tmpdir(), 'fmtrx-api-build-'))
  const entry = path.join(directory, 'endpoint.js')
  writeFileSync(entry, `export const loginUrl = (import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || '') + 'login';`)
  const result = await build({
    configFile: path.resolve('vite.config.mjs'),
    envDir: directory,
    logLevel: 'silent',
    build: {
      write: false,
      minify: false,
      rollupOptions: { input: entry, preserveEntrySignatures: 'strict' },
    },
  })
  const chunk = result.output.find(output => output.type === 'chunk' && output.isEntry)
  expect(chunk).toBeDefined()
  const exports = await import(`data:text/javascript;base64,${Buffer.from(chunk.code).toString('base64')}`)
  expect(exports.loginUrl).toBe('https://app.fmtrx.com/api/login')
}, 30000)
