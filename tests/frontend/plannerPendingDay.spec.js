import {it, expect} from 'vitest'
import {mergePendingPlannerDay} from '../../resources/js/features/planner/lib/pendingDay'
it('accepts canonical session completion without losing pending notes or advancing their base version',()=>{
  const pending={pending:true,progress:{version:4,items:{warmup:{done:true},bullpen:{player_note:'Keep note',done:false}}}}
  const result=mergePendingPlannerDay({actual_results:{bullpen:{session_id:'session',session_status:'completed',done:true}},session_links:[]},pending)
  expect(result.progress.version).toBe(4)
  expect(result.progress.items.bullpen).toEqual({player_note:'Keep note',session_id:'session',session_status:'completed',done:true})
  expect(result.progress.items.warmup.done).toBe(true)
  expect(pending.progress.items.bullpen.done).toBe(false)
})
