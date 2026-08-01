import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(process.cwd())
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8')

describe('admin login and session activity', () => {
  it('links the admin home to the activity page', () => {
    expect(read('resources/js/pages/admin/AdminDashboard.vue')).toContain("key: 'admin.activity'")
    expect(read('resources/router/index.js')).toContain("name: 'admin.activity'")
  })

  it('uses the protected activity endpoint and real range filters', () => {
    const page = read('resources/js/pages/admin/AdminActivity.vue')

    expect(page).toContain('admin/activity?range=${range.value}&role=${role.value}')
    expect(page).toContain("{ key: 'day', label: 'Last Day' }")
    expect(page).toContain("{ key: 'week', label: 'Last Week' }")
    expect(page).toContain("{ key: 'month', label: 'Last Month' }")
    expect(page).toContain('user.sessions_recorded')
  })
})
