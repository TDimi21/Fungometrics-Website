import {it, expect, vi} from 'vitest';
import {readFileSync} from 'node:fs';
import {parse, compileScript} from '@vue/compiler-sfc';
import {ref, computed} from 'vue';
const source=compileScript(parse(readFileSync('resources/js/pages/training/AllSessions.vue','utf8')).descriptor,{id:'reports-audit'}).content.replace(/^import[\s\S]*?from ['"][^'"]+['"];?\s*$/gm,'').replace('export default','return');
function screen(){
 const mocks={ref,computed,onMounted:()=>{},storeToRefs:s=>s,useRoute:()=>({query:{}}),useRouter:()=>({push:vi.fn()}),useTeamStore:()=>({team:ref({})}),useUserStore:()=>({userData:{id:'self',type:'player'}}),useAxiosAuth:()=>({axiosGet:vi.fn(async()=>({data:{data:[]}}))}),playerSessionHistory:vi.fn(async(_,type)=>type==='LT'?Array.from({length:17},(_,i)=>({id:`s${i}`,type:'T',modes:'LT',created_at:'2026-01-01',is_completed:i===0,long_toss:[{distance:100}]})):[]),Layout:{}};
 const c=new Function(...Object.keys(mocks),source)(...Object.values(mocks));return {s:c.setup({},{expose:()=>{}}),mocks};
}
it('loads all player reports without requiring a scope query or dropping old sessions',async()=>{
 const {s}=screen();expect(s.playerScope.value).toBe(true);await s.loadPlayerSessions();expect(s.sessions.value).toHaveLength(17);expect(s.sessionTotals.value).toEqual({total:17,done:1});expect(s.sessions.value[0].total_balls).toBe(1);
});
it('counts each training mode using its canonical saved results',()=>{
 const {s}=screen();for(const [mode,key] of [['LT','long_toss'],['EV','exit_velocity'],['WB','weight_ball']])expect(s.mapPlayerSession({modes:mode,[key]:[{},{}],is_completed:false},'T')).toMatchObject({total_balls:2,is_completed:false});
});
