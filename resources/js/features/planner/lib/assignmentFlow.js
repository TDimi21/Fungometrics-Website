// Shared assignment contract: canonical planner records, never duplicate scoring records.
export const SCORED_SESSIONS = [
  {id: 'bullpen', name: 'Bullpen', bucket: 'pitching', description: 'Pitch locations, contact and velocity'},
  {id: 'weighted_ball', name: 'Weighted Balls', bucket: 'throwing', description: 'Throw velocity by ball weight'},
  {id: 'cage', name: 'Cage', bucket: 'hitting', description: 'Contact quality and hitting results'},
  {id: 'batting', name: 'Batting Practice', bucket: 'hitting', description: 'Pitch-by-pitch batting results'},
  {id: 'long_toss', name: 'Long Toss', bucket: 'throwing', description: 'Throw distance and progression'},
  {id: 'exit_velocity', name: 'Exit Velocity', bucket: 'hitting', description: 'Recorded exit velocities'},
];
export function assignmentStatus(row) {
  if (row.completion?.completed_at) return 'completed';
  return row.progress?.started_at || Object.keys(row.actual_results || {}).length || row.session_links?.length ? 'in_progress' : 'not_started';
}
export function assignmentSummary(rows) {
  const counts = {assigned: rows.length, completed: 0, in_progress: 0, not_started: 0};
  rows.forEach(row => counts[assignmentStatus(row)]++);
  return counts;
}
export function groupAssignments(rows) {
  const groups = new Map();
  rows.forEach(row => {
    if (!groups.has(row.id)) groups.set(row.id, {id: row.id, name: row.name, rows: []});
    groups.get(row.id).rows.push(row);
  });
  return [...groups.values()].map(group => ({...group, ...assignmentSummary(group.rows)}));
}
export function simpleAssignment({id, itemId, teamId, kind, sessionType, name, instructions, date, dueTime, players}) {
  const session = SCORED_SESSIONS.find(s => s.id === sessionType);
  if (!['quick', 'scored'].includes(kind) || (kind === 'scored' && !session)) throw new Error('Choose a supported assignment.');
  if (!teamId || !players?.length || !name?.trim() || !/^\d{4}-\d{2}-\d{2}$/.test(date)) throw new Error('Choose a title, team, players and date.');
  if (dueTime && !/^([01]\d|2[0-3]):[0-5]\d$/.test(dueTime)) throw new Error('Enter a due time as HH:MM, or leave it empty.');
  return {id, team_id: String(teamId), name: name.trim(), date, status: 'published',
    settings: {assignment_kind: kind, due_time: dueTime || null},
    assigned_player_ids: [...new Set(players.map(String))],
    buckets: [{type: kind === 'scored' ? session.bucket : 'education', items: [{
      id: itemId, name: name.trim(), required: true, note: instructions || '', prescription_text: instructions || '',
      metadata: {execution_type: kind === 'scored' ? 'LAUNCH_SESSION' : 'COMPLETE', ...(kind === 'scored' ? {session_type: session.id, tracking_type: 'session'} : {})},
    }]}]};
}

export const sessionReportType = type => ({weighted_ball:'weight_ball',bullpen:'bullpen',batting:'batting',cage:'cage',long_toss:'long_toss',exit_velocity:'exit_velocity'})[type] || null;
