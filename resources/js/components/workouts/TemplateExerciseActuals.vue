<script setup>
import { ref, computed } from "vue";
const props = defineProps({
  item: Object,
  modelValue: Object,
  sessions: { type: Array, default: () => [] },
});
const emit = defineEmits(["update:modelValue"]);
const patch = (value) =>
  emit("update:modelValue", { ...props.modelValue, ...value });
const numeric = (key, event) =>
  patch({
    [key]: event.target.value === "" ? null : Number(event.target.value),
  });
const weight = ref(""),
  velocity = ref(""),
  error = ref("");
const media = computed(() =>
  [props.item.metadata?.video_url, props.item.metadata?.image_url].filter(
    (url) => /^https?:\/\//i.test(url || "")
  )
);
const matching = computed(() =>
  props.sessions.filter((s) =>
    props.item.metadata?.session_type === "bullpen"
      ? s.type === "P"
      : s.type === "T" && s.modes === "WB"
  )
);
const addThrow = () => {
  if (
    !Number.isInteger(Number(weight.value)) ||
    !Number.isInteger(Number(velocity.value)) ||
    Number(weight.value) <= 0 ||
    Number(velocity.value) <= 0
  ) {
    error.value =
      "Enter whole-number ball weight (oz) and measured velocity (mph).";
    return;
  }
  const radar = props.modelValue?.radar || [];
  patch({
    radar: [
      ...radar,
      {
        id: crypto.randomUUID(),
        weight: Number(weight.value),
        velocity: Number(velocity.value),
        attempt: Math.max(0, ...radar.map((r) => r.attempt)) + 1,
        timestamp: new Date().toISOString(),
      },
    ],
  });
  velocity.value = "";
  error.value = "";
};
</script>
<template>
  <div class="template-actuals" @click.stop>
    <p>
      <strong
        >{{
          item.sets_min === item.sets_max
            ? item.sets_min
            : `${item.sets_min ?? "—"}–${item.sets_max ?? "—"}`
        }}
        sets</strong
      >
      · {{ item.prescription_text }}
    </p>
    <p v-if="item.reps_min != null">
      Reps: {{ item.reps_min
      }}{{
        item.reps_max != null && item.reps_max !== item.reps_min
          ? "–" + item.reps_max
          : ""
      }}
    </p>
    <p v-if="item.durationSec != null">
      Duration: {{ item.durationSec }} seconds
    </p>
    <p v-if="item.distance != null">Distance: {{ item.distance }} yards</p>
    <p v-if="item.intensity_min != null || item.intensity_max != null">
      Target effort: {{ item.intensity_min ?? "—" }}–{{
        item.intensity_max ?? "—"
      }}%
    </p>
    <p v-if="item.equipment">Equipment: {{ item.equipment }}</p>
    <p v-if="item.ball_weights?.length">
      Ball weights: {{ item.ball_weights.join(", ") }}
    </p>
    <p v-if="item.note">{{ item.note }}</p>
    <p v-if="item.metadata?.focus">Bullpen focus: {{ item.metadata.focus }}</p>
    <p v-if="item.metadata?.pitch_types?.length">
      Pitch types: {{ item.metadata.pitch_types.join(", ") }}
    </p>
    <p v-if="item.metadata?.targets?.length">
      Targets: {{ item.metadata.targets.join(", ") }}
    </p>
    <p v-if="item.metadata?.planned_pitch_count != null">
      Planned pitches: {{ item.metadata.planned_pitch_count }}
    </p>
    <a
      v-for="(url, index) in media"
      :key="url"
      :href="url"
      target="_blank"
      rel="noopener noreferrer"
      >Open demonstration {{ index + 1 }}</a
    >
    <a
      v-if="item.name === 'Jaeger Band Series'"
      href="/images/training/j-band-exercise-sheet.jpg"
      target="_blank"
      rel="noopener noreferrer"
      >Open J-Band movement reference</a
    >
    <small v-if="!media.length && item.name !== 'Jaeger Band Series'"
      >Demonstration not available yet.</small
    >
    <details>
      <summary>Record results (optional)</summary>
      <div class="actual-fields">
        <label
          >Actual sets<input
            type="number"
            min="0"
            step="1"
            :value="modelValue?.actual_sets"
            @input="numeric('actual_sets', $event)"
        /></label>
        <label
          >Actual reps<input
            type="number"
            min="0"
            step="1"
            :value="modelValue?.actual_reps"
            @input="numeric('actual_reps', $event)"
        /></label>
        <label
          >Distance (yards)<input
            type="number"
            min="0"
            step="any"
            :value="modelValue?.actual_distance_yards"
            @input="numeric('actual_distance_yards', $event)"
        /></label>
        <label
          >Actual RPE (1–10)<input
            type="number"
            min="1"
            max="10"
            :value="modelValue?.actual_rpe"
            @input="numeric('actual_rpe', $event)"
        /></label>
      </div>
      <label
        >Your notes<textarea
          maxlength="2000"
          :value="modelValue?.player_note"
          @input="patch({ player_note: $event.target.value })"
        />
      </label>
      <template v-if="item.metadata?.tracking_type === 'radar'">
        <p>
          Optional radar results — enter measured throws only. Saved throws
          remain in your weighted-ball session.
        </p>
        <div class="actual-fields">
          <label
            >Ball weight (oz)<input
              v-model="weight"
              type="number"
              min="1"
              step="1" /></label
          ><label
            >Measured velocity (mph)<input
              v-model="velocity"
              type="number"
              min="1"
              step="1"
          /></label>
        </div>
        <button type="button" @click="addThrow">Add measured throw</button>
        <p v-if="error" role="alert">{{ error }}</p>
        <div v-for="r in modelValue?.radar || []" :key="r.id">
          #{{ r.attempt }} · {{ r.weight }} oz · {{ r.velocity }} mph
        </div>
      </template>
      <label v-if="item.metadata?.session_type && !modelValue?.radar?.length"
        >Link an existing
        {{
          item.metadata.session_type === "bullpen" ? "bullpen" : "weighted-ball"
        }}
        session
        <select
          :value="modelValue?.session_id || ''"
          @change="patch({ session_id: $event.target.value || null })"
        >
          <option value="">No linked session</option>
          <option
            v-for="session in matching"
            :key="session.id"
            :value="session.id"
          >
            {{ session.started }} ·
            {{ session.is_completed ? "Completed" : "In progress" }}
          </option>
        </select>
      </label>
      <RouterLink
        v-if="modelValue?.session_id"
        :to="{
          name: 'session.report',
          params: {
            id: modelValue.session_id,
            type:
              item.metadata?.session_type === 'bullpen'
                ? 'bullpen'
                : 'weight_ball',
          },
        }"
        >View session results →</RouterLink
      >
      <p v-if="modelValue?.session_id">
        Session linked. Use your existing session recorder to view or correct
        pitches and measurements.
      </p>
    </details>
  </div>
</template>
<style scoped>
.template-actuals {
  margin-top: 10px;
  color: #cbd5e1;
  font-size: 13px;
}
.template-actuals p {
  margin: 6px 0;
}
.template-actuals small {
  color: #94a3b8;
}
.template-actuals details {
  margin-top: 12px;
}
.template-actuals summary {
  cursor: pointer;
  color: #7dd3fc;
}
.actual-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin: 12px 0;
}
.template-actuals label {
  display: block;
}
.template-actuals input,
.template-actuals select,
.template-actuals textarea {
  display: block;
  width: 100%;
  background: #0b1222;
  color: #fff;
  border: 1px solid #475569;
  border-radius: 6px;
  padding: 8px;
  margin-top: 4px;
}
.template-actuals button {
  border: 1px solid #475569;
  padding: 8px;
  border-radius: 6px;
  margin: 8px 0;
}
.template-actuals a {
  display: block;
  color: #7dd3fc;
}
</style>
