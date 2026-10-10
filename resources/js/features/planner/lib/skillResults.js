export const skillFields = bucket => bucket === 'hitting'
  ? [['swings','Swings'],['contacts','Contacts'],['hard_contacts','Hard contacts'],['exit_velocity_mph','Best measured exit velocity (mph)']]
  : ['pitching','throwing'].includes(bucket)
    ? [['throws','Total throws (including pitches)'],['pitches','Pitches'],['strikes','Strikes'],['velocity_mph','Best measured velocity (mph)']] : [];
export const skillRates = p => [['contacts','swings','Contact / swings'],['hard_contacts','contacts','Hard contact / contacts'],['strikes','pitches','Strikes / pitches']]
  .filter(([a,b]) => p?.[a] != null && p?.[b] > 0)
  .map(([a,b,label]) => `${label}: ${Math.round(1000*p[a]/p[b])/10}% (${p[a]}/${p[b]})`);
export const matchesSkillSession = (s,bucket) => bucket === 'hitting'
  ? s.type === 'C' || s.type === 'L' || s.type === 'T' && s.modes === 'EV'
  : s.type === 'P' || s.type === 'T' && ['LT','WB'].includes(s.modes);
