import {describe, it, expect} from 'vitest';
import {readFileSync} from 'node:fs';
import {parse} from '@vue/compiler-sfc';
import {compile} from '@vue/compiler-dom';
import {renderToString} from '@vue/server-renderer';
import * as Vue from 'vue';
import {BUCKET_BY_TYPE} from '@/features/planner/lib/plannerBuckets';
import {setSummary} from '@/features/planner/lib/strengthLoad';
const template = parse(readFileSync('resources/js/components/workouts/PlayerWorkoutPreview.vue', 'utf8')).descriptor.template.content;
const render = new Function('Vue', compile(template, {mode: 'function', prefixIdentifiers: true}).code)(Vue);
const renderPreview = sections => renderToString(Vue.createSSRApp({render, setup: () => ({
  name: 'Hybrid A', date: '2026-10-10', minutes: 30, startTime: '15:00', endTime: '15:30',
  sections, total: sections.reduce((n,s) => n + (s.items || s.exercises || []).length, 0),
  BUCKET_BY_TYPE, setSummary, prescription: item => item.prescription_text || '',
})}));
describe('player workout preview', () => {
  it('shows ordered sections, prescriptions and unsaved instructions with disabled result controls', async () => {
    const html = await renderPreview([
      {type: 'daily_readiness', title: 'Daily Readiness'},
      {type: 'throwing', title: 'Throwing', note: 'Stop if uncomfortable', items: [{name: 'Pivot picks', prescription_text: '2 sets of 5 throws', note: 'Stay balanced', equipment: 'Baseball'}]},
    ]);
    expect(html).toContain('Hybrid A');
    expect(html.indexOf('Daily Readiness')).toBeLessThan(html.indexOf('Throwing'));
    for (const text of ['2 sets of 5 throws', 'Stay balanced', 'Stop if uncomfortable', 'Baseball', '15:00', '15:30']) expect(html).toContain(text);
    expect(html).toMatch(/<button[^>]*disabled[^>]*>Save result/);
    expect(html).toContain('Complete your readiness check before training.');
  });
  it('supports reusable-template exercises and safely renders coach-entered text', async () => {
    const html = await renderPreview([{name: 'Recovery', instructions: 'Easy effort', exercises: [{exercise_name: '<script>test</script>', prescription_text: '3 rounds', is_optional: true}]}]);
    expect(html).toContain('&lt;script&gt;test&lt;/script&gt;');
    expect(html).toContain('3 rounds');
    expect(html).toContain('Optional exercise');
  });
});
