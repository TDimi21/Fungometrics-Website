import {describe,it,expect} from 'vitest'
import {SCORED_SESSIONS,simpleAssignment,assignmentSummary,groupAssignments,sessionReportType} from '../../resources/js/features/planner/lib/assignmentFlow'
const base={id:'plan',itemId:'item',teamId:'team',kind:'scored',sessionType:'weighted_ball',name:'Velocity',date:'2026-10-24',players:['one','one','two'],dueTime:'18:00',instructions:'Ten throws'}
describe('Simple assignment contract',()=>{
 it('publishes one authoritative launcher, deduplicates recipients and retains retry IDs',()=>{
  const p=simpleAssignment(base)
  expect(p.id).toBe('plan');expect(p.assigned_player_ids).toEqual(['one','two'])
  expect(p.buckets[0].items[0].metadata).toEqual({execution_type:'LAUNCH_SESSION',session_type:'weighted_ball',tracking_type:'session'})
  expect(p.settings.due_time).toBe('18:00');expect(p.buckets[0].items[0].prescription_text).toBe('Ten throws')
  expect(simpleAssignment(base)).toEqual(p)
 })
 it('excludes Live AB and rejects unsupported sessions',()=>{
  expect(SCORED_SESSIONS).toHaveLength(6)
  expect(()=>simpleAssignment({...base,sessionType:'live_ab'})).toThrow()
  expect(()=>simpleAssignment({...base,players:[]})).toThrow()
 })
 it('does not treat saved partial results as a submitted assignment',()=>{
  const rows=[{id:'a',actual_results:{x:{done:true}}},{id:'a',completion:{completed_at:'2026-10-24'}},{id:'b'}]
  expect(assignmentSummary(rows)).toEqual({assigned:3,completed:1,in_progress:1,not_started:1})
  expect(groupAssignments(rows)[0]).toMatchObject({assigned:2,completed:1,in_progress:1})
 })
 it('routes all session types to the matching report',()=>{
  expect(SCORED_SESSIONS.map(s=>sessionReportType(s.id))).toEqual(['bullpen','weight_ball','cage','batting','long_toss','exit_velocity'])
  expect(sessionReportType('live_ab')).toBeNull()
 })
})

it('counts finalized linked scored sessions but not partial or unlinked results',()=>{
 const plan={...simpleAssignment(base),actual_results:{item:{done:true,session_status:'completed',session_id:'saved'}},session_links:[{item_id:'item',practice_id:'saved'}]}
 expect(assignmentSummary([plan]).completed).toBe(1)
 expect(assignmentSummary([{...plan,session_links:[]}]).completed).toBe(0)
 expect(assignmentSummary([{...plan,actual_results:{item:{done:false,session_status:'in_progress',session_id:'saved'}}}]).completed).toBe(0)
 expect(assignmentSummary([{...plan,settings:{assignment_kind:'guided'}}]).completed).toBe(0)
})
