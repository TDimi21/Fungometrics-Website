import fs from 'fs'
import path from 'path'
import { describe, expect, it } from 'vitest'
const component = fs.readFileSync(path.resolve(process.cwd(),'resources/js/components/blast/BlastSessionDevelopmentReport.vue'),'utf8')
const page = fs.readFileSync(path.resolve(process.cwd(),'resources/js/pages/data-hub/BlastSessionDevelopmentReportPage.vue'),'utf8')
const dashboard = fs.readFileSync(path.resolve(process.cwd(),'resources/js/pages/dashboard/Index.vue'),'utf8')
describe('Blast Session Development Report', () => {
  it('renders the four approved columns without the empty Session column', () => {
    expect(component).toContain('<span>Metric</span><span>Average Swing</span><span>Best Swing</span><span>Benchmark</span>')
    expect(component).not.toContain('<span>Session</span>')
  })
  it('renders all metric groups, responsive cards, and unavailable ball flight', () => {
    for (const label of ['Swing Quality Scores','Speed & Power','Swing Shape','Connection & Sequence','Paired Ball Flight']) expect(component).toContain(label)
    expect(component).toContain('No paired ball-flight data was captured')
    expect(component).toContain("'Not captured'")
    expect(component).toContain('@media(max-width:760px)')
  })
  it('shows benchmark status as accessible text and supports selection and states', () => {
    expect(component).toContain('metric.benchmark.label')
    expect(component).toContain('aria-label')
    expect(page).toContain('v-model="selected"')
    for (const state of ['Loading Blast','not authorized','could not be loaded','Select a Benchmark Level']) expect(page).toContain(state)
  })
  it('adds completed Blast report cards to Recent Sessions', () => {
    expect(dashboard).toContain("label: 'BLAST REPORT'")
    expect(dashboard).toContain('data-hub/imports?team_id=')
    expect(dashboard).toContain("name: 'data-hub.blast-report'")
    expect(dashboard).toContain("item.platform === 'Blast Motion' && item.status === 'completed'")
  })
})
