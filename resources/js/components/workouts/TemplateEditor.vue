<script setup>
import { ref } from "vue";
import { BUCKETS } from "@/features/planner/lib/plannerBuckets";
const props = defineProps({ template: Object, busy: Boolean });
const emit = defineEmits(["save", "cancel"]);
const draft = ref(JSON.parse(JSON.stringify(props.template)));
// PHP serializes empty associative arrays as []; normalize before editing media/tracking keys.
for (const section of draft.value.sections) for (const exercise of section.exercises) {
  if (!exercise.metadata || Array.isArray(exercise.metadata)) exercise.metadata = {};
}
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
  <form class="workout-editor" @submit.prevent="normalize">
    <header>
      <h2>Edit Workout Copy</h2>
      <button type="button" @click="emit('cancel')">Cancel</button
      ><button class="primary" :disabled="busy">
        {{ busy ? "Saving…" : "Save workout" }}
      </button>
    </header>
    <div class="form-grid">
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
    <label
      >Description / coach instructions<textarea
        v-model="draft.description"
        rows="3"
      ></textarea>
    </label>
    <section
      v-for="(section, si) in draft.sections"
      :key="si"
      class="workout-panel"
    >
      <div class="toolbar">
        <input
          v-model="section.name"
          required
          aria-label="Section name"
        /><select v-model="section.section_type" aria-label="Section type">
          <option
            v-for="b in BUCKETS.filter((b) => b.kind !== 'survey')"
            :key="b.type"
            :value="b.type"
          >
            {{ b.title }}
          </option></select
        ><button
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
              <option value="session">Existing session</option>
            </select></label
          ><label v-if="e.metadata.tracking_type"
            >Session type<select v-model="e.metadata.session_type">
              <option value="weighted_ball">Weighted ball</option>
              <option value="bullpen">Bullpen</option>
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
      </article>
      <button type="button" @click="addExercise(section)">
        ＋ Add exercise
      </button>
    </section>
    <button
      type="button"
      @click="
        draft.sections.push({
          name: 'New section',
          section_type: 'recovery',
          instructions: '',
          exercises: [],
        })
      "
    >
      ＋ Add section
    </button>
  </form>
</template>
