import { computeBallFlight } from './ballFlight'
import { battedBallOutcome, simulateOutcome } from './battedBallOutcome'
export function validGameField(field) {
  return !!field && typeof field.levelId === 'string' && typeof field.levelLabel === 'string' && Number(field.basePathFt)>=30 && Number(field.basePathFt)<=100 && Number(field.pitchingFt)>=20 && Number(field.pitchingFt)<=70 && ['lineL','gapL','center','gapR','lineR'].every(key=>Number(field.fences?.[key])>=50 && Number(field.fences?.[key])<=600)
}
export function fieldPayload(field) {
  return {levelId:field.levelId,levelLabel:field.levelLabel,basePathFt:Number(field.basePathFt),pitchingFt:Number(field.pitchingFt),fences:Object.fromEntries(['lineL','gapL','center','gapR','lineR'].map(key=>[key,Number(field.fences[key])]))}
}
export function savedGameHit(row, field) {
  const swing={ev:Number(row.launch_angle_velocity),launchAngle:Number(row.launch_angle),sprayAngle:Number(row.spray_angle),distanceFt:Number(row.distance_travel)}
  const flight=computeBallFlight(swing.ev,swing.launchAngle,swing.sprayAngle,{distanceFt:swing.distanceFt})
  const outcome=battedBallOutcome(flight,field)
  // Use a stable per-result draw, so opening stats or replaying never rerolls a hit.
  let seed=2166136261
  for(const char of String(row.id)) seed=Math.imul(seed^char.charCodeAt(0),16777619)>>>0
  seed ^= seed>>>16; seed=Math.imul(seed,0x7feb352d)>>>0; seed^=seed>>>15
  const result=outcome.foul?'Foul Ball':simulateOutcome(outcome.probabilities,()=> (seed>>>0)/4294967296)
  return {...swing,flight,outcome,result,id:row.id}
}
