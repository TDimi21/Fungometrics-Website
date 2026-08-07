import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'

const root = path.resolve(__dirname, '../..')
const report = fs.readFileSync(path.join(root, 'resources/js/pages/training/SessionReport.vue'), 'utf8')

describe('web and app session report parity', () => {
  it('accepts every bullpen pitch field used by the app', () => {
    expect(report).toContain('p.type_of_throw_id')
    expect(report).toContain('p.type_of_throw_msg || p.type_throw || p.type_of_throw')
    expect(report).toContain('p.contact_trajectory')
    expect(report).toContain('isSwingMissPitch(p)')
    expect(report).toContain('isFoulPitch(p)')
  })

  it('renders the app bullpen report sections and grading language', () => {
    expect(report).toContain('Productive but Inconsistent')
    expect(report).toContain('Avg Velo by Pitch Type')
    expect(report).toContain('BPS Components')
    expect(report).toContain('Bullpen Performance Score')
    expect(report).toContain('Velocity Trend')
    expect(report).toContain('What This Means')
  })
})
