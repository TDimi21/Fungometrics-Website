// Pure builders for the Weighted / Exit Velocity / Long Toss training report
// cards, plus the SVG line-chart geometry they share. No axios, no stores.

import { CHART_COLORS } from './constants.js'

export const fmtReport = (value, suffix = '') => {
  const n = Number(value)
  if (!Number.isFinite(n)) return '—'
  return `${Number.isInteger(n) ? n : n.toFixed(1)}${suffix}`
}

// ── Report builders ─────────────────────────────────────────────────────

export const buildWeightedReport = (weighted) => {
  if (!weighted?.byWeight?.length) return null
  const five = weighted.byWeight.find((row) => Number(row.weight) === 5)
  const base = Number(five?.avgVelo || weighted.byWeight[0]?.avgVelo || 0)
  // Expected velocity relative to the 5 oz baseline for each ball weight.
  const multipliers = { 3: 1.04, 4: 1.02, 5: 1, 6: 0.97, 7: 0.94, 9: 0.90 }
  const max = Math.max(...weighted.byWeight.map((row) => Number(row.maxVelo || row.avgVelo || 0)), base * 1.06 || 1)
  const rows = weighted.byWeight.map((row) => {
    const expected = base && multipliers[row.weight] ? base * multipliers[row.weight] : null
    return {
      label: `${row.weight} oz avg`,
      shortLabel: `${row.weight} oz`,
      value: row.avgVelo,
      topValue: row.maxVelo,
      expected,
      color: Number(row.weight) < 5 ? CHART_COLORS.blue : Number(row.weight) === 5 ? CHART_COLORS.green : CHART_COLORS.yellow,
    }
  })
  const best = rows
    .map((row) => ({ ...row, delta: row.expected ? Number(row.value) - row.expected : 0 }))
    .sort((a, b) => b.delta - a.delta)[0]
  return {
    title: 'Weighted Ball Velocity Curve',
    subtitle: 'All weighted ball throws',
    max,
    suffix: ' mph',
    rows,
    lineSeries: [
      { key: 'value', label: 'Avg', color: CHART_COLORS.green },
      { key: 'topValue', label: 'Top', color: CHART_COLORS.blue },
      { key: 'expected', label: 'Expected', color: CHART_COLORS.white, dashed: true },
    ],
    tiles: [
      { label: 'Top Velo', value: fmtReport(weighted.maxVelo, ' mph'), sub: 'Best recorded' },
      { label: 'Best Weight', value: best?.label?.replace(' avg', '') || '—', sub: best ? fmtReport(best.value, ' mph avg') : '' },
      { label: '5 oz Avg', value: fmtReport(five?.avgVelo, ' mph'), sub: 'Regulation ball' },
      { label: 'Weights', value: String(weighted.byWeight.length), sub: 'Tracked groups' },
    ],
  }
}

export const buildExitVelocityReport = (exitVel) => {
  if (!exitVel?.swings) return null
  const rows = [
    { label: 'LD avg', shortLabel: 'LD', value: exitVel.ldAvgEV, color: CHART_COLORS.green },
    { label: 'GB avg', shortLabel: 'GB', value: exitVel.gbAvgEV, color: CHART_COLORS.blue },
    { label: 'FB avg', shortLabel: 'FB', value: exitVel.fbAvgEV, color: CHART_COLORS.yellow },
  ]
  return {
    title: 'Exit Velocity by Trajectory',
    subtitle: `${exitVel.trajTotal || 0} classified swings`,
    max: Math.max(Number(exitVel.maxEV || 0), 100),
    suffix: ' mph',
    rows,
    lineSeries: [
      { key: 'value', label: 'Avg EV', color: CHART_COLORS.accent },
    ],
    tiles: [
      { label: 'Avg EV', value: fmtReport(exitVel.avgEV, ' mph'), sub: 'All EV swings' },
      { label: 'Top EV', value: fmtReport(exitVel.maxEV, ' mph'), sub: 'Best recorded' },
      { label: 'Hard Hit %', value: fmtReport(exitVel.hardPct, '%'), sub: '90+ mph' },
      { label: 'Line Drives', value: fmtReport(exitVel.ldPct, '%'), sub: `${exitVel.ldCount || 0} swings` },
    ],
  }
}

export const buildLongTossReport = (longToss) => {
  if (!longToss?.throws) return null
  const rows = [
    { label: '0 hops avg', shortLabel: '0', value: longToss.hop0, color: CHART_COLORS.green },
    { label: '1 hop avg', shortLabel: '1', value: longToss.hop1, color: CHART_COLORS.blue },
    { label: '2 hops avg', shortLabel: '2', value: longToss.hop2, color: CHART_COLORS.yellow },
    { label: '3 hops avg', shortLabel: '3', value: longToss.hop3, color: CHART_COLORS.accent },
  ]
  const cleanPct = longToss.throws ? ((longToss.hop0Count + longToss.hop1Count) / longToss.throws) * 100 : null
  return {
    title: 'Long Toss Distance by Hops',
    subtitle: `${longToss.throws} throws`,
    max: Math.max(Number(longToss.maxDist || 0), 300),
    suffix: ' ft',
    rows,
    lineSeries: [
      { key: 'value', label: 'Avg Distance', color: CHART_COLORS.green },
    ],
    tiles: [
      { label: 'Top Distance', value: fmtReport(longToss.maxDist, ' ft'), sub: 'Best throw' },
      { label: 'Avg Distance', value: fmtReport(longToss.avgDist, ' ft'), sub: 'All throws' },
      { label: 'Clean Carry', value: fmtReport(cleanPct, '%'), sub: '0-1 hops' },
      { label: 'Full Carry', value: fmtReport(longToss.hop0Pct, '%'), sub: '0 hops' },
    ],
  }
}

// ── Line chart geometry (viewBox 0 0 320 150) ──────────────────────────

export const lineChartRows = (report) => {
  const series = report?.lineSeries || []
  return (report?.rows || []).filter((row) => series.some((item) => Number.isFinite(Number(row[item.key])) && Number(row[item.key]) > 0))
}

export const lineChartSeries = (report) => {
  const rows = lineChartRows(report)
  return (report?.lineSeries || []).filter((item) => rows.some((row) => Number.isFinite(Number(row[item.key])) && Number(row[item.key]) > 0))
}

const lineChartValues = (report) => {
  const rows = lineChartRows(report)
  return lineChartSeries(report)
    .flatMap((item) => rows.map((row) => Number(row[item.key])))
    .filter((value) => Number.isFinite(value) && value > 0)
}

const lineChartRange = (report) => {
  const values = lineChartValues(report)
  const max = Math.max(Number(report?.max || 0), ...values, 1)
  const rawMin = values.length ? Math.min(...values) : 0
  const min = Math.max(0, rawMin - Math.max(5, (max - rawMin) * 0.2))
  return { min, max, span: Math.max(1, max - min) }
}

export const lineChartX = (report, index) => {
  const rows = lineChartRows(report)
  const left = 32
  const right = 14
  const width = 320
  if (rows.length <= 1) return left
  return left + (index / (rows.length - 1)) * (width - left - right)
}

export const lineChartY = (report, value) => {
  const top = 14
  const bottom = 28
  const height = 150
  const range = lineChartRange(report)
  return top + (1 - ((Number(value) - range.min) / range.span)) * (height - top - bottom)
}

export const lineChartPath = (report, series) => lineChartRows(report)
  .map((row, index) => ({ x: lineChartX(report, index), y: lineChartY(report, row[series.key]), value: Number(row[series.key]) }))
  .filter((point) => Number.isFinite(point.value) && point.value > 0)
  .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(1)} ${point.y.toFixed(1)}`)
  .join(' ')
