import {BUCKET_BY_TYPE} from './plannerBuckets';

export function quickWorkoutKind(template) {
  if (!template.is_premade) return null;
  const category = `${template.category || ''} ${template.program_type || ''}`.toLowerCase();
  if (category.includes('hitting')) return 'hitting';
  if (category.includes('pitching')) return 'pitching';
  return null;
}

// Load prescriptions into the current day without saving or publishing it.
export function preloadQuickWorkout(plan, template, makeId = () => crypto.randomUUID()) {
  const buckets = JSON.parse(JSON.stringify(plan.buckets || []));
  const source = {id: template.id, version: template.version, name: template.name, description: template.description};
  for (const section of template.sections || []) {
    let bucket = buckets.find(b => b.type === section.section_type);
    if (!bucket) {
      bucket = {type: section.section_type, title: section.name, kind: BUCKET_BY_TYPE[section.section_type]?.kind || 'content', items: [], note: ''};
      const recovery = buckets.findIndex(b => b.type === 'recovery');
      buckets.splice(recovery < 0 ? buckets.length : recovery, 0, bucket);
    }
    bucket.items ||= [];
    bucket.template_source ||= source;
    if (section.instructions && !bucket.note?.includes(section.instructions)) {
      bucket.note = [bucket.note, section.instructions].filter(Boolean).join('\n\n');
    }
    for (const e of section.exercises || []) {
      if (bucket.items.some(i => i.template_id === template.id && i.template_exercise_id === e.id)) continue;
      bucket.items.push({
        id: makeId(), name: e.exercise_name, exerciseId: e.exercise_id,
        sets: e.sets_min, reps: e.reps_min, sets_min: e.sets_min, sets_max: e.sets_max,
        reps_min: e.reps_min, reps_max: e.reps_max, prescription_text: e.prescription_text,
        equipment: e.equipment, ball_weights: e.ball_weights || [], durationSec: e.duration_seconds,
        distance: e.distance_yards, intensity_min: e.intensity_min, intensity_max: e.intensity_max,
        required: !e.is_optional, note: e.instructions, metadata: {...e.metadata},
        template_id: template.id, template_version: template.version, template_exercise_id: e.id,
      });
    }
  }
  const survey = type => buckets.find(b => b.type === type) || {type, title: BUCKET_BY_TYPE[type].title, kind: 'survey', items: [], note: ''};
  return {
    ...plan,
    name: plan.name || template.name,
    primaryGoal: plan.primaryGoal || (template.description || '').slice(0, 200),
    buckets: [survey('daily_readiness'), ...buckets.filter(b => !['daily_readiness', 'player_reflection'].includes(b.type)), survey('player_reflection')],
  };
}
