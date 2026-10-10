import {BUCKET_BY_TYPE} from './plannerBuckets';

export const WORKOUT_STARTERS = [
  {type: 'hitting', label: 'Hitting', sections: ['movement_prep', 'hitting', 'recovery']},
  {type: 'pitching', label: 'Pitching', sections: ['movement_prep', 'arm_care', 'throwing', 'pitching', 'recovery']},
  {type: 'defensive', label: 'Defensive', sections: ['movement_prep', 'throwing', 'defense', 'recovery']},
  {type: 'strength', label: 'Strength', sections: ['movement_prep', 'strength_primary', 'strength_secondary', 'strength_accessory', 'recovery']},
];

export function preloadWorkoutSections(buckets, type) {
  const starter = WORKOUT_STARTERS.find(s => s.type === type);
  if (!starter) return buckets;
  const existing = new Map(buckets.map(b => [b.type, b]));
  const make = type => existing.get(type) || {
    type, title: BUCKET_BY_TYPE[type].title, kind: BUCKET_BY_TYPE[type].kind, items: [], note: '',
  };
  const training = buckets.filter(b => !['daily_readiness', 'player_reflection'].includes(b.type));
  // Add missing starter sections before recovery, retaining all existing drills and notes.
  for (const section of starter.sections) {
    if (training.some(b => b.type === section)) continue;
    const recovery = training.findIndex(b => b.type === 'recovery');
    training.splice(recovery < 0 ? training.length : recovery, 0, make(section));
  }
  return [make('daily_readiness'), ...training, make('player_reflection')];
}
