import {describe, it, expect} from 'vitest'
import {plannerSessionReturn} from '../../resources/js/features/planner/lib/sessionReturn'
describe('Planner session return', () => {
  it('returns to Today only for the same player and canonical session', () => {
    let value=JSON.stringify({session_id:'session-a',user_id:'player-a'})
    const storage={getItem:()=>value,removeItem:()=>{value=null}}
    expect(plannerSessionReturn(storage,'session-b','player-a','/dashboard')).toBe('/dashboard')
    expect(plannerSessionReturn(storage,'session-a','player-b','/dashboard')).toBe('/dashboard')
    expect(plannerSessionReturn(storage,'session-a','player-a','/dashboard')).toEqual({path:'/player-dashboard',query:{tab:'workout'}})
    expect(value).toBeNull()
  })
  it('does not interrupt finishing a session when device storage is unavailable', () => {
    expect(plannerSessionReturn({getItem:()=>{throw Error('unavailable')}},'s','p','/dashboard')).toBe('/dashboard')
  })
})
