import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'

const root = process.cwd()
const read = relativePath => fs.readFileSync(path.join(root, relativePath), 'utf8')

describe('launch-safe administrator navigation', () => {
  const dashboard = read('resources/js/pages/admin/AdminDashboard.vue')
  const sidebar = read('resources/js/layout/NavigationSidebar.vue')
  const router = read('resources/router/index.js')
  const exposed = `${dashboard}\n${sidebar}\n${router}`

  it('keeps authoritative administrator controls available', () => {
    expect(exposed).toContain('admin.users')
    expect(exposed).toContain('admin.teams')
    expect(exposed).toContain('admin.plans')
  })

  it.each([
    ['Role Management', 'admin.roles'],
    ['Security', 'admin.security'],
    ['Audit Logs', 'admin.auditlogs'],
    ['Reports', 'admin.reports'],
  ])('does not expose the unfinished %s control', (label, routeName) => {
    expect(dashboard).not.toContain(`label: '${label}'`)
    expect(exposed).not.toContain(routeName)
  })

  it('does not infer login activity from profile timestamps', () => {
    expect(dashboard).not.toContain('Active Coaches')
    expect(dashboard).not.toContain('updated_at || u.created_at')
  })
})
