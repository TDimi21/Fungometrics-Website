<script setup>
import {computed, ref, onMounted, onBeforeUnmount} from 'vue';
import {useAxiosAuth} from '@/composables/axios-auth';
import {quickWorkoutKind} from '@/features/planner/lib/quickWorkout';
defineProps({date: String});
const emit = defineEmits(['load']);
const {axiosGet} = useAxiosAuth();
const templates = ref([]), loading = ref(false), error = ref(''), kind = ref('pitching');
const visible = computed(() => templates.value.filter(t => quickWorkoutKind(t) === kind.value));
let alive = true;
async function loadLibrary() {
  loading.value = true;
  error.value = '';
  try {
    const response = await axiosGet('coach/workout-templates');
    if (alive) templates.value = response.data.data || [];
  } catch {
    if (alive) error.value = 'Could not load premade workouts.';
  } finally {
    if (alive) loading.value = false;
  }
}
onMounted(loadLibrary);
onBeforeUnmount(() => {alive = false;});
</script>
<template>
  <section class="quick-workouts" aria-label="Quick load a premade workout">
    <h3>Quick load a workout for {{ date }}</h3>
    <p>Load all drills and prescriptions into this day. Review, assign players, then save or publish. Existing drills stay in place.</p>
    <div class="quick-types">
      <button v-for="option in ['pitching', 'hitting']" :key="option" type="button" :aria-pressed="kind === option" @click="kind = option">{{ option === 'pitching' ? 'Pitching plans' : 'Hitting plans' }}</button>
    </div>
    <p v-if="loading" role="status">Loading workouts…</p>
    <p v-else-if="error" role="alert">{{ error }} <button type="button" @click="loadLibrary">Retry</button></p>
    <div v-else-if="visible.length" class="quick-cards">
      <article v-for="template in visible" :key="template.id">
        <strong>{{ template.name }}</strong>
        <small>{{ template.intensity_label }} · {{ template.sections.reduce((n, s) => n + s.exercises.length, 0) }} drills</small>
        <button type="button" @click="emit('load', template)">Quick load {{ template.name }}</button>
      </article>
    </div>
    <p v-else>No {{ kind }} premade workouts are available yet.</p>
  </section>
</template>
<style scoped>
.quick-workouts{margin-top:20px;padding-top:18px;border-top:1px solid #344b68}.quick-workouts h3{font-weight:700}.quick-workouts p{font-size:13px;color:#b9c9df;margin:8px 0 12px}.quick-types{display:flex;gap:10px;margin-bottom:12px}.quick-workouts button{min-height:44px;padding:10px 14px;border:1px solid #49617e;border-radius:8px;background:#21334e;color:#fff;font-weight:600}.quick-types button[aria-pressed=true]{background:#cc2434;border-color:#ef334b}.quick-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px}.quick-cards article{display:flex;flex-direction:column;gap:10px;padding:14px;border:1px solid #344b68;border-radius:10px;background:#0b1726}.quick-cards small{color:#b9c9df}.quick-cards button{margin-top:auto}
</style>
