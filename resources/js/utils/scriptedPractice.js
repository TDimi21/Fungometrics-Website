import { ROUND_TYPES } from '@/data/battingRounds'
export function validScript(plan, type) {
  if (!Array.isArray(plan) || !plan.length || plan.length > 200) return false
  if (type === 'P') return plan.every(p => ['FB','CH','SL','CV','OTHER'].includes(p.pitchType) && Number.isInteger(p.intendedCoord) && p.intendedCoord >= 1 && p.intendedCoord <= 3600)
  if (type === 'B') return new Set(plan.map(p=>p.round_type)).size === plan.length && plan.every(p => ROUND_TYPES.some(r=>r.id===p.round_type) && Number.isInteger(p.swing_count) && p.swing_count >= 1 && p.swing_count <= 20)
  return plan.every(p => p.drillId && p.drillLabel && Number.isInteger(p.swing_count) && p.swing_count >= 1 && p.swing_count <= 50)
}
// Same progression as mobile: bullpen per pitcher, BP round -> batter -> swing,
// cage drills count swings across the current practice.
export function nextBattingStep(plan, players, swings) {
  for (let round = 0; round < plan.length; round++) {
    for (const player of players) {
      const taken = swings.filter(s => s.batter_id === player.id && s.round_type === plan[round].round_type).length
      if (taken < plan[round].swing_count) return { ...plan[round], round, playerId:player.id, taken, index:taken+1 }
    }
  }
  return null
}
export function cageStep(plan, count) {
  for (let index=0;index<plan.length;index++) {
    if (count < plan[index].swing_count) return {...plan[index], round:index+1, index:count+1}
    count -= plan[index].swing_count
  }
  return null
}
export const roundLabel = id => ROUND_TYPES.find(r=>r.id===id)?.label || id
export const roundGoal = id => ROUND_TYPES.find(r=>r.id===id)?.goal || ''
export function targetStyle(coord) {
  return {left:`${(((coord-1)%60)+.5)/60*100}%`,top:`${(Math.floor((coord-1)/60)+.5)/60*100}%`}
}
export function pitchAccuracy(intended, actual) {
  if (!intended || !actual) return null
  const x = n => Math.floor((n-1)/60), y = n => (n-1)%60
  const distance = Math.hypot(x(intended)-x(actual),y(intended)-y(actual))
  return {distance:Math.round(distance*10)/10, grade:distance<=3?'PERFECT':distance<=7?'GREAT':distance<=12?'GOOD':distance<=20?'OK':'MISS'}
}
