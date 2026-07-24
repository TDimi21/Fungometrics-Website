import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const source = fs.readFileSync(
  path.resolve(__dirname, '../../resources/js/pages/training/StadisticCage.vue'),
  'utf8',
)

describe('cage statistics runtime cleanup', () => {
  it('renders one shared field component for each analysis tab', () => {
    expect(source.match(/<CageFieldStats/g)).toHaveLength(3)
    expect(source).not.toContain('PrintSprayCage')
    expect(source).not.toContain('setContact()')
    expect(source).not.toContain('setVelocity()')
    expect(source).not.toContain('cage-panel--unified > :not')
  })
})
