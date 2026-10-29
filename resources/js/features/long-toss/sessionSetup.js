export const setupFields = [
 ['maximum_ft','Measured long-toss maximum (ft)',30,500],
 ['out_sets','Sets going out (includes the 30-ft starting set)',2,20],
 ['in_sets','Sets coming in (ends at 30 ft)',1,20],
 ['out_throws','Throws per set going out',1,30],['in_throws','Throws per set coming in',1,30],
 ['rest_seconds','Rest between sets (seconds)',10,300],['max_throws','Do not exceed (optional throw limit)',3,120],
];
export const newSetup = () => ({readiness:7,pain:null,day_type:'moderate',season_phase:'inseason',throwing_history:''});
export const hasThrowLimit = data => data.max_throws!=='' && data.max_throws!=null;
export function setupPreview(data) {
 if(data.pain===true || Number(data.readiness)<=2)return {target:0,throws:0,stages:[],message:'Rest today — no throwing.'};
 const max=Number(data.maximum_ft),out=Number(data.out_sets),inside=Number(data.in_sets);
 if(!max||!Number.isInteger(out)||out<2||out>20||!Number.isInteger(inside)||inside<1||inside>20)return null;
 const factor=Number(data.readiness)<=4||data.day_type==='recovery'?.5:Number(data.readiness)>=8&&data.day_type==='high'?.97:.8;
 const target=Math.floor(max*factor),sets=out+inside,limit=hasThrowLimit(data)?Number(data.max_throws):null;
 const stages=Array.from({length:sets},(_,i)=>({
   direction:i<out?'Going out':'Coming in',set:i+1,
   distance:Math.round(i<out?30+(target-30)*i/(out-1):target-(target-30)*(i-out+1)/inside),
   throws:Number(i<out?data.out_throws||0:data.in_throws||0),
 }));
 const throws=stages.reduce((sum,stage)=>sum+stage.throws,0);
 return {target,throws,stages,limit,overLimit:limit!==null&&throws>limit,message:`30 ft → ${target} ft → 30 ft · ${sets} sets · ${throws} total throws${limit!==null?` / ${limit} maximum`: ''}`};
}
export function setupPayload(players,drafts) {
 return players.map(player=>{
   const draft={...drafts[player.id]},data={readiness:Number(draft.readiness),pain:draft.pain,day_type:draft.day_type,season_phase:draft.season_phase,throwing_history:draft.throwing_history};
   for(const [key,label,min,max] of setupFields){
     if(key==='max_throws'&&!hasThrowLimit(draft)){data[key]=null;continue;}
     const value=Number(draft[key]);if(!Number.isFinite(value)||!Number.isInteger(value)||value<min||value>max)throw new Error(`${player.name}: enter ${label.toLowerCase()} (${min}–${max}).`);data[key]=value;
   }
   if(![true,false].includes(data.pain)||!String(data.throwing_history||'').trim())throw new Error(`${player.name}: answer the pain question and add throwing history.`);
   const preview=setupPreview(data);
   if(preview?.overLimit)throw new Error(`${player.name}: planned throws (${preview.throws}) exceed the limit (${preview.limit}). Reduce sets or throws per set, or change the limit.`);
   if(!preview|| (preview.target>0&&(preview.target<30||preview.stages.some(stage=>stage.throws<1)||preview.throws>120)))throw new Error(`${player.name}: allow at least one throw per set, a target of at least 30 ft, and no more than 120 total throws.`);
   return {...data,player_id:String(player.id),timezone:Intl.DateTimeFormat().resolvedOptions().timeZone||'UTC'};
 });
}

export function applySharedPlan(players,drafts,shared) {
 const keys=['day_type','season_phase','out_sets','in_sets','out_throws','in_throws','rest_seconds','max_throws'];
 const workload=Object.fromEntries(keys.filter(key=>shared[key]!==undefined).map(key=>[key,shared[key]]));
 return {...drafts,...Object.fromEntries(players.map(player=>[player.id,{...newSetup(),...drafts[player.id],...workload}]))};
}
