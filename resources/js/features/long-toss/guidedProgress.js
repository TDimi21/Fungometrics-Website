export function guidedProgress(guide) {
 if(!guide)return null;
 const stages=guide.snapshot?.stages||[],total=stages.reduce((sum,s)=>sum+Number(s.throws||0),0);
 const recorded=Math.max(0,Number(guide.recorded_throws||0)),index=Number(guide.stage||0);
 const current=stages[index]||null;
 let prior=0;
 const sets=stages.map((stage,i)=>{
  const completed=Math.min(Number(stage.throws),Math.max(0,recorded-prior));prior+=Number(stage.throws);
  return {...stage,number:i+1,completed,remaining:Number(stage.throws)-completed,current:i===index&&guide.status==='active'};
 });
 return {sets,total,recorded,remaining:Math.max(0,total-recorded),percent:total?Math.min(100,Math.round(recorded/total*100)):0,
  set:current?index+1:null,setCount:stages.length,current,stageRecorded:Math.min(Number(current?.throws||0),Number(guide.stage_throws||0)),
  stageRemaining:Math.max(0,Number(current?.throws||0)-Number(guide.stage_throws||0)),
  status:guide.status};
}
export function plannedEntry(guide,unit='ft') {
 const distance=Number(guide?.current_stage?.distance_ft);
 if(!guide?.can_record||!Number.isFinite(distance)||distance<=0||distance>500)return '';
 // Truncate to three yard decimals so 500 ft never becomes an over-limit 166.667 yd.
 return String(unit==='yd'?Math.floor(distance/3*1000)/1000:distance);
}
export const progressKey=guide=>guide?`${guide.player_id||guide.id}:${guide.stage}:${guide.recorded_throws}:${guide.status}`:'';
