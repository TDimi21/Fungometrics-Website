import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'

const appSource = readFileSync(
  new URL('../../resources/js/app.js', import.meta.url),
  'utf8',
)

describe('CSP-safe Vue startup', () => {
  it('renders RouterView without compiling an in-DOM template at runtime', () => {
    expect(appSource).toContain("import { createApp, h } from 'vue'")
    expect(appSource).toContain("import { RouterView } from 'vue-router'")
    expect(appSource).toContain('render: () => h(RouterView)')
    expect(appSource).not.toContain('createApp();')
  })
})
