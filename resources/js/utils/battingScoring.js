import { getPitchInfoFromCoordinate } from './bullpenCoordinates'
import { getBattingFieldInfoFromCoordinate } from './battingCoordinates'
import { createFieldMapper, configFromLevel, fenceAt } from './ballFieldGeometry'

export function battingPayload({ session, batter, pitch, field, quality, trajectory, velocity }) {
  const miss = quality === 'M'
  const info = getPitchInfoFromCoordinate(pitch)
  const nothingSelected = !quality && !trajectory && !field && !Number(velocity)
  return { practice_id: session.id, team_id: session.team_id || null, batter_id: batter,
    pitch_mark: pitch || null, pitch_location: info.pitch_side, zone: info.zone,
    quality_of_contact: miss ? 'M' : quality || 'N',
    type_of_hit: miss ? 'SM' : nothingSelected ? 'TK' : trajectory || 'N',
    is_contact: miss ? false : quality ? !['M', 'N'].includes(quality) : Boolean(trajectory),
    field_mark: miss ? null : field || null,
    field_direction: miss ? null : getBattingFieldInfoFromCoordinate(field).direction || '',
    velocity: miss ? 0 : Number(velocity || 0) }
}
// Same vector-field inversion as mobile BattingFieldScoring.js.
export function battingFieldMark(x, y, width = 600, height = 400) {
  const cfg = configFromLevel('hs'), mapper = createFieldMapper(width, height, cfg)
  const xf = (x - mapper.home.px) / mapper.pixelsPerFoot
  const yf = (mapper.home.py - y) / mapper.pixelsPerFoot
  const spray = Math.atan2(xf, yf) * 180 / Math.PI
  const clamp = (n,a,b) => Math.max(a,Math.min(b,n))
  const u = clamp(spray / 90 + .5, 0, 1)
  const v = clamp(1 - Math.hypot(xf,yf) / fenceAt(clamp(spray,-45,45), cfg.fences),0,1)
  return clamp(Math.round(v * 80) || 1,1,80) + (clamp(Math.round(u * 80) || 1,1,80) - 1) * 80
}
