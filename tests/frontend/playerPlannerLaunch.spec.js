import {it, expect, vi} from 'vitest';
import {readFileSync} from 'node:fs';
const source=readFileSync('resources/js/components/planner/PlayerToday.vue','utf8');
const body=source.slice(source.indexOf('async function launch('),source.indexOf('async function adjust('));
function launch(){const router={push:vi.fn()},error={value:''};return {router,error,run:new Function('router','busy','error',body+'; return launch;')(router,{value:false},error)}}
it.each([['P',null,'bullpen'],['B',null,'batting'],['C',null,'cage'],['T','LT','LT'],['T','EV','EV'],['T','WB','WB']])('opens personal setup from a %s/%s workout',async(type,mode,target)=>{
 const x=launch();await x.run({id:'plan',team_id:'team'},{id:'item',linked_session:{practice_type:type,mode}});
 expect(x.router.push).toHaveBeenCalledWith({name:'player.session.create',params:{mode:target},query:{planner_plan:'plan',planner_item:'item',planner_team:'team'}});
});
it('opens the existing linked session instead of creating a duplicate',async()=>{
 const x=launch();await x.run({actual_results:{item:{session_id:'saved'}}},{id:'item',linked_session:{practice_type:'B'}});
 expect(x.router.push).toHaveBeenCalledWith({path:'/track/batting',query:{session:'saved'}});
});
it('does not offer a self-scored live AB game',async()=>{
 const x=launch();await x.run({id:'plan'},{id:'item',linked_session:{practice_type:'L'}});expect(x.router.push).not.toHaveBeenCalled();expect(x.error.value).toContain('coach');
});
