import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'

const root = path.resolve(__dirname, '../..')
const report = fs.readFileSync(path.join(root, 'resources/js/pages/training/SessionReport.vue'), 'utf8')
const battingReport = fs.readFileSync(path.join(root, 'resources/js/components/statistics/BattingReportCard.vue'), 'utf8')
const viteConfig = fs.readFileSync(path.join(root, 'vite.config.js'), 'utf8')

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

  it('precompiles session-report components without CSP unsafe-eval', () => {
    expect(report).not.toContain('template: `')
    expect(battingReport).not.toContain('template: `')
    expect(viteConfig).toContain('vue.runtime.esm-bundler.js')
  })
})
