<script setup>
import {computed} from 'vue';
import {BUCKET_BY_TYPE} from '@/features/planner/lib/plannerBuckets';
import {setSummary} from '@/features/planner/lib/strengthLoad';
const props = defineProps({name: String, date: String, sections: {type: Array, default: () => []}, minutes: Number, startTime: String, endTime: String});
const total = computed(() => props.sections.reduce((n, s) => n + (s.items || s.exercises || []).length, 0));
const range = (min, max) => min == null ? '' : max != null && max !== min ? `${min}–${max}` : String(min);
const prescription = item => item.prescription_text || [
  item.sets != null || item.sets_min != null ? `${range(item.sets ?? item.sets_min, item.sets_max)} sets` : '',
  item.reps != null || item.reps_min != null ? `${range(item.reps ?? item.reps_min, item.reps_max)} reps` : '',
  item.throws != null ? `${item.throws} throws` : '',
  item.durationSec || item.duration_seconds ? `${item.durationSec || item.duration_seconds} sec` : '',
  item.distance != null ? `${item.distance} distance (as prescribed)` : '',
  item.intensity || '',
].filter(Boolean).join(' · ');
</script>
<template>
  <section class="player-preview-wrap" aria-label="Player app workout preview">
    <div class="preview-caption"><h3>Player app preview</h3><p>Review the current workout before sending. Expand drills to inspect their instructions. Completion controls are disabled in this preview.</p></div>
    <div class="player-phone">
      <div class="player-brand">FMTRX · PLANNER</div>
      <div class="player-date">{{ date || 'Workout day' }}</div>
      <article class="player-plan">
        <small>Assigned workout</small>
        <h2>{{ name || 'Untitled workout' }}</h2>
        <p class="player-meta">{{ sections.length }} sections · {{ total }} drills<span v-if="minutes != null"> · {{ minutes }} min</span></p>
        <p v-if="startTime" class="player-meta">{{ startTime }} – {{ endTime }}</p>
        <p class="player-meta">Readiness: Not recorded / 100</p>
        <button type="button" disabled>How are you feeling?</button>
        <p v-if="!sections.length" class="player-meta">No sections added yet.</p>
        <section v-for="(section, i) in sections" :key="i" class="player-block">
          <h3>{{ section.title || section.name }}</h3>
          <p v-if="section.startTime" class="player-meta">{{ section.startTime }} – {{ section.endTime }}{{ section.endDayOffset ? ' (+1 day)' : '' }}</p>
          <p v-if="section.location" class="player-meta">{{ section.location }}</p>
          <p v-if="section.note || section.instructions" class="player-meta">{{ section.note || section.instructions }}</p>
          <p v-if="(section.kind || BUCKET_BY_TYPE[section.type]?.kind) === 'survey'" class="player-meta">{{ section.type === 'daily_readiness' ? 'Complete your readiness check before training.' : 'Reflect on how your workout felt after training.' }}</p>
          <p v-else-if="!(section.items || section.exercises || []).length && !(section.note || section.instructions)" class="player-meta">No drills added.</p>
          <details v-for="(item, j) in section.items || section.exercises || []" :key="j" open class="player-drill">
            <summary>{{ item.name || item.exercise_name || 'Unnamed drill' }}</summary>
            <div class="player-prescription">
              <p v-if="prescription(item)">{{ prescription(item) }}</p>
              <p v-for="(set, k) in item.setList || []" :key="k">Set {{ k + 1 }}: {{ setSummary(set) }}</p>
              <p v-if="item.plannedMinutes != null">{{ item.plannedMinutes }} min including rest</p>
              <p v-if="item.equipment">Equipment: {{ item.equipment }}</p>
              <p v-if="item.ball_weights?.length">Ball weights: {{ item.ball_weights.join(', ') }}</p>
              <p v-for="(instruction, k) in [...new Set([item.note, item.instructions, item.coachCue].filter(Boolean))]" :key="k">{{ instruction }}</p>
              <p v-if="item.is_optional || item.required === false">Optional exercise</p>
              <p v-if="item.metadata?.session_type">Session: {{ item.metadata.session_type.replaceAll('_', ' ') }}</p>
              <div class="player-complete"><span>Complete</span><input type="checkbox" disabled :aria-label="`Complete ${item.name || item.exercise_name}`" /></div>
              <button type="button" disabled>Save result</button>
            </div>
          </details>
        </section>
      </article>
    </div>
  </section>
</template>
<style scoped>
.player-preview-wrap{margin:24px 0}.preview-caption{max-width:680px;margin:0 auto 18px;color:#edf5ff}.preview-caption h3{font-size:19px;font-weight:800}.preview-caption p{font-size:14px;line-height:1.6;color:#9ebbd6;margin-top:6px}.player-phone{max-width:430px;margin:auto;padding:20px 16px;background:#05111e;border:1px solid #254157;border-radius:26px;color:#edf5ff;box-shadow:0 18px 50px #0004}.player-brand{color:#ff2942;font-size:12px;letter-spacing:2px;font-weight:800}.player-date{font-size:15px;margin:16px 0;color:#9ebbd6}.player-plan{background:#0a1928;border:1px solid #254157;border-radius:12px;padding:16px}.player-plan h2{font-size:29px;line-height:1.2;color:#f0f6ff;font-weight:800;margin:14px 0;overflow-wrap:anywhere}.player-plan small,.player-meta,.player-prescription p{color:#9ebbd6;font-size:13px;line-height:20px;margin:6px 0;white-space:pre-wrap}.player-block{margin-top:24px}.player-block h3{font-size:17px;color:#f0f6ff;font-weight:700;margin:8px 0}.player-drill{border:1px solid #254157;border-radius:12px;padding:12px;margin:10px 0}.player-drill summary{cursor:pointer;font-size:15px;font-weight:700;min-height:44px;align-content:center;color:#edf5ff;overflow-wrap:anywhere}.player-prescription{padding-top:8px}.player-phone button{width:100%;padding:14px;min-height:46px;border:1px solid #254157;border-radius:9px;margin:5px 0;background:#0a1928;color:#edf5ff;font-size:15px;font-weight:700;opacity:.65}.player-complete{display:flex;justify-content:space-between;align-items:center;margin-top:16px;font-size:15px}.player-complete input{width:20px;height:20px}@media(max-width:480px){.player-phone{padding:12px 8px}.player-plan{padding:12px}}
</style>
