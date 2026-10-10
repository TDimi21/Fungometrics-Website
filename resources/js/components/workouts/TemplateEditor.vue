<script setup>
import { ref, computed, onMounted } from "vue";
import WorkoutGuide from './WorkoutGuide.vue';
import PlayerWorkoutPreview from './PlayerWorkoutPreview.vue';
import WorkoutPreview from './WorkoutPreview.vue';
import {searchDrills} from '@/features/planner/lib/plannerDrills';
import {useAxiosAuth} from '@/composables/axios-auth';
import { BUCKETS } from "@/features/planner/lib/plannerBuckets";
const props = defineProps({ template: Object, busy: Boolean });
const emit = defineEmits(["save", "cancel"]);
const draft = ref(JSON.parse(JSON.stringify(props.template)));
// PHP serializes empty associative arrays as []; normalize before editing media/tracking keys.
for (const section of draft.value.sections) for (const exercise of section.exercises) {
  if (!exercise.metadata || Array.isArray(exercise.metadata)) exercise.metadata = {};
}
const guideBuckets = BUCKETS.filter(b => b.kind !== 'survey');
const step = ref(0), preview = ref(false), drillSearch = ref(''), libraryOpen = ref(false), allBuckets = ref(false), customDrills = ref([]), libraryError = ref('');
const {axiosGet} = useAxiosAuth();
const currentSection = computed(() => draft.value.sections.find(s => s.section_type === guideBuckets[step.value - 1]?.type));
const drills = computed(() => searchDrills(drillSearch.value, allBuckets.value ? null : currentSection.value?.section_type, customDrills.value));
async function loadDrills() {
  libraryError.value = '';
  try { customDrills.value = (await axiosGet('coach/drills')).data.data || []; }
  catch { libraryError.value = 'Saved/shared drills could not load. Built-in drills remain available.'; }
}
onMounted(loadDrills);
function useDrill(drill) {
  currentSection.value.exercises.push({
    exercise_id: drill.id, exercise_name: drill.name,
    prescription_text: drill.description || [drill.defaultSets != null ? `${drill.defaultSets} sets` : '', drill.defaultReps != null ? `${drill.defaultReps} reps` : '', drill.defaultThrows != null ? `${drill.defaultThrows} throws` : '', drill.defaultDurationSec != null ? `${drill.defaultDurationSec} seconds` : ''].filter(Boolean).join(' · '),
    sets_min: drill.defaultSets ?? null, sets_max: drill.defaultSets ?? null,
    reps_min: drill.defaultReps ?? null, reps_max: drill.defaultReps ?? null,
    duration_seconds: drill.defaultDurationSec ?? null, equipment: drill.equipment || '',
    instructions: drill.coachCue || '', ball_weights: [], is_optional: false,
    metadata: drill.videoUrl ? {video_url: drill.videoUrl} : {},
  });
  libraryOpen.value = false;
}
const formError = ref('');
function addBlock(b){if(draft.value.sections.some(s=>s.section_type===b.type))return;draft.value.sections.push({name:b.title,section_type:b.type,instructions:'',exercises:[]})}

const optionalNumberFields = [
  "sets_min",
  "sets_max",
  "reps_min",
  "reps_max",
  "duration_seconds",
  "distance_yards",
  "intensity_min",
  "intensity_max",
];
function addExercise(section) {
  section.exercises.push({
    exercise_name: "",
    prescription_text: "",
    sets_min: null,
    sets_max: null,
    reps_min: null,
    reps_max: null,
    equipment: "",
    ball_weights: [],
    instructions: "",
    is_optional: false,
    metadata: {},
  });
}
function move(list, index, step) {
  const to = index + step;
  if (to >= 0 && to < list.length)
    [list[index], list[to]] = [list[to], list[index]];
}
function normalize() {
  formError.value = '';
  if (!draft.value.name?.trim() || !draft.value.sport?.trim() || !draft.value.category?.trim()) { step.value = 0; formError.value = 'Enter a name, sport, and category.'; return; }
  if (!draft.value.sections.length) { formError.value = 'Use at least one section before saving.'; return; }
  const incomplete = draft.value.sections.find(s => !s.name?.trim() || s.exercises.some(e => !e.exercise_name?.trim() || !e.prescription_text?.trim()));
  if (incomplete) { step.value = guideBuckets.findIndex(b => b.type === incomplete.section_type) + 1; formError.value = 'Give each drill a name and written prescription before saving.'; return; }
  const copy = JSON.parse(JSON.stringify(draft.value));
  for (const s of copy.sections)
    for (const e of s.exercises) {
      for (const key of optionalNumberFields) if (e[key] === "") e[key] = null;
    }
  for (const k of [
    "target_rpe_min",
    "target_rpe_max",
    "estimated_duration_minutes",
  ])
    if (copy[k] === "") copy[k] = null;
  emit("save", copy);
}
</script>
<template>
  <form class="workout-editor" novalidate @submit.prevent="normalize">
    <header>
      <h2>Workout Builder</h2>
      <button type="button" @click="emit('cancel')">Cancel</button
      ><button class="primary" :disabled="busy">
        {{ busy ? "Saving…" : "Save workout" }}
      </button>
    </header>
    <p v-if="formError" role="alert">{{ formError }}</p>
    <WorkoutGuide v-model:step="step" :buckets="guideBuckets" :included="draft.sections.map(s => s.section_type)" @use="addBlock" @preview="preview = true; libraryOpen = false" />
    <div v-if="step === guideBuckets.length + 1"><h3>Ready to save</h3><p>{{ draft.sections.length }} sections · {{ draft.sections.reduce((n,s) => n + s.exercises.length,0) }} drills. Preview your workout, then save it to your library.</p></div>
    <PlayerWorkoutPreview v-if="step === guideBuckets.length + 1" :name="draft.name" :sections="draft.sections" :minutes="draft.estimated_duration_minutes" />
    <div v-show="step === 0" class="form-grid workout-settings">
      <label>Name<input v-model="draft.name" required maxlength="200" /></label
      ><label>Sport<input v-model="draft.sport" required /></label
      ><label>Category<input v-model="draft.category" required /></label
      ><label>Program<input v-model="draft.program_type" /></label
      ><label>Intensity label<input v-model="draft.intensity_label" /></label
      ><label
        >Estimated minutes (optional)<input
          v-model.number="draft.estimated_duration_minutes"
          type="number"
          min="1"
          max="1440" /></label
      ><label
        >Target effort minimum (%)<input
          v-model.number="draft.target_rpe_min"
          type="number"
          min="0"
          max="100" /></label
      ><label
        >Target effort maximum (%)<input
          v-model.number="draft.target_rpe_max"
          type="number"
          min="0"
          max="100"
      /></label>
    </div>
    <label v-show="step === 0"
      >Description / coach instructions<textarea
        v-model="draft.description"
        rows="3"
      ></textarea>
    </label>
    <section
      v-for="(section, si) in draft.sections"
      :key="si"
      class="workout-panel" v-show="section.section_type === guideBuckets[step - 1]?.type"
    >
      <div class="toolbar">
        <input
          v-model="section.name"
          required
          aria-label="Section name"
        />        <button
          type="button"
          :disabled="si === 0"
          @click="move(draft.sections, si, -1)"
        >
          ↑</button
        ><button
          type="button"
          :disabled="si === draft.sections.length - 1"
          @click="move(draft.sections, si, 1)"
        >
          ↓</button
        ><button type="button" @click="draft.sections.splice(si, 1)">
          Remove section
        </button>
      </div>
      <label
        >Section instructions<textarea
          v-model="section.instructions"
        ></textarea>
      </label>
      <button type="button" class="primary" @click="libraryOpen = !libraryOpen; drillSearch = ''; allBuckets = false">＋ Add drills from library</button>
      <div v-if="libraryOpen" class="drill-browser">
        <input v-model="drillSearch" aria-label="Search drill library" placeholder="Search drills, equipment, or coaching cues…" />
        <label><input v-model="allBuckets" type="checkbox" /> Search all buckets</label>
        <p v-if="libraryError" role="alert">{{ libraryError }} <button type="button" @click="loadDrills">Retry</button></p>
        <div class="drill-results"><button v-for="d in drills" :key="d.id" type="button" @click="useDrill(d)">{{ d.name }} ＋</button></div>
        <p v-if="!drills.length">No matching drills. Change the search or add a custom exercise below.</p>
      </div>
      <article
        v-for="(e, ei) in section.exercises"
        :key="ei"
        class="exercise-edit"
      >
        <div class="toolbar">
          <input
            v-model="e.exercise_name"
            required
            aria-label="Exercise name"
          /><button
            type="button"
            :disabled="ei === 0"
            @click="move(section.exercises, ei, -1)"
          >
            ↑</button
          ><button
            type="button"
            :disabled="ei === section.exercises.length - 1"
            @click="move(section.exercises, ei, 1)"
          >
            ↓</button
          ><button type="button" @click="section.exercises.splice(ei, 1)">
            Remove
          </button>
        </div>
        <label
          >Written prescription (preserved in the assigned workout)<textarea
            v-model="e.prescription_text"
            required
            rows="2"
          ></textarea>
        </label>
        <details><summary>Sets, reps, equipment & tracking</summary>
        <div class="form-grid">
          <label v-for="key in optionalNumberFields" :key="key"
            >{{ key.replaceAll("_", " ")
            }}<input
              v-model.number="e[key]"
              type="number"
              min="0"
              step="any" /></label
          ><label>Equipment<input v-model="e.equipment" /></label
          ><label
            >Ball weights / colors (comma separated)<input
              :value="e.ball_weights?.join(', ')"
              @change="
                e.ball_weights = $event.target.value
                  .split(',')
                  .map((v) => v.trim())
                  .filter(Boolean)
              "
          /></label>
        </div>
        <label
          >Instructions / coaching cues<textarea
            v-model="e.instructions"
          ></textarea></label
        ><label
          ><input v-model="e.is_optional" type="checkbox" /> Optional
          exercise</label
        >
        <div v-if="e.metadata" class="form-grid">
          <label
            >Tracking<select v-model="e.metadata.tracking_type">
              <option :value="null">Completion / actuals</option>
              <option value="radar">Weighted-ball radar</option>
              <option value="session">Existing session</option><option value="quick_throws">Quick throw count</option>
            </select></label
          ><label v-if="e.metadata.tracking_type"
            >Session type<select v-model="e.metadata.session_type">
              <option value="weighted_ball">Weighted ball</option>
              <option value="bullpen">Bullpen</option><option v-for="type in ['long_toss','exit_velocity','cage','live_ab','strength','assessment']" :key="type" :value="type">{{type.replaceAll('_',' ')}}</option>
            </select></label
          ><label
            >Demonstration image URL<input
              v-model="e.metadata.image_url"
              type="url"
              placeholder="Optional existing media" /></label
          ><label
            >Demonstration video URL<input
              v-model="e.metadata.video_url"
              type="url"
              placeholder="Optional existing media" /></label
          ><template v-if="e.metadata.session_type === 'bullpen'"
            ><label>Bullpen focus<input v-model="e.metadata.focus" /></label
            ><label
              >Planned pitch count<input
                v-model.number="e.metadata.planned_pitch_count"
                type="number"
                min="0" /></label
            ><label
              >Pitch types<input
                :value="e.metadata.pitch_types?.join(', ')"
                @change="
                  e.metadata.pitch_types = $event.target.value
                    .split(',')
                    .map((v) => v.trim())
                    .filter(Boolean)
                " /></label
            ><label
              >Intended targets<input
                :value="e.metadata.targets?.join(', ')"
                @change="
                  e.metadata.targets = $event.target.value
                    .split(',')
                    .map((v) => v.trim())
                    .filter(Boolean)
                " /></label
          ></template>
        </div>
        </details>
      </article>
      <button type="button" @click="addExercise(section)">
        ＋ Add exercise
      </button>
    </section>
    <footer class="toolbar"><button type="button" :disabled="step === 0" @click="step--">← Back</button><button type="button" @click="preview = true">Preview workout</button><button v-if="step < guideBuckets.length + 1" type="button" class="primary" @click="step++">Next step →</button></footer>
    <WorkoutPreview v-if="preview" :name="draft.name" :sections="draft.sections" :minutes="draft.estimated_duration_minutes" @close="preview = false" />
  </form>
</template>

<style scoped>
.workout-editor{display:block;max-width:1000px;margin:auto}.workout-editor input,.workout-editor textarea,.workout-editor select{max-width:100%}.workout-editor .workout-settings{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.drill-browser{padding:16px;border:1px solid #49617e;border-radius:12px;margin:16px 0}.drill-results{display:flex;flex-direction:column;max-height:300px;overflow:auto;gap:8px}.drill-results button{text-align:left;min-height:44px}@media(max-width:650px){.workout-editor .workout-settings{grid-template-columns:1fr}}
</style>
