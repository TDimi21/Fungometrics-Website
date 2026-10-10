<script setup>
import { computed, ref, watch, onBeforeUnmount } from "vue";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import Layout from "@/layout/Layout.vue";
import { useTeamStore } from "@/store/team";
import { useAxiosAuth } from "@/composables/axios-auth";
import { localDateKey } from "@/features/planner/lib/calendar";
import {
  plannerLink,
  validPlannerDate,
} from "@/features/planner/lib/plannerLinks";
import TemplateEditor from "@/components/workouts/TemplateEditor.vue";
import ProgramBuilder from "@/components/workouts/ProgramBuilder.vue";
const props=defineProps({embedded:Boolean,initialTab:{type:String,default:'library'}})
const libraryView=ref('premade')
const { axiosGet, axiosPost, axiosPut } = useAxiosAuth(),
  router = useRouter(),
  route = useRoute();
const { team } = storeToRefs(useTeamStore()),
  teamId = computed(() => team.value?.id_team ?? team.value?.id ?? "");
const templates = ref([]),
  players = ref([]),
  groups = ref([]),
  loading = ref(false),
  busy = ref(false),
  error = ref(""),
  search = ref(""),
  category = ref(""),
  programFilter = ref(""),
  intensity = ref(""),
  ownership = ref(""),
  selected = ref(null),
  editing = ref(null),
  tab = ref(props.initialTab),
  programTemplate = ref("");
const date = ref(
    validPlannerDate(route.query.date) ? route.query.date : localDateKey()
  ),
  selectedPlayers = ref([]),
  selectedGroups = ref([]),
  wholeTeam = ref(false);
let generation = 0,
  useRequest = null;
const choices = (key) => [
  ...new Set(templates.value.map((t) => t[key]).filter(Boolean)),
];
const filtered = computed(() =>
  templates.value.filter(
    (t) =>
      (libraryView.value==='premade' ? t.is_premade : libraryView.value==='mine' ? !t.is_premade : !!t.last_used_at) &&
      (t.name + " " + (t.description || ""))
        .toLowerCase()
        .includes(search.value.toLowerCase()) &&
      (!category.value || t.category === category.value) &&
      (!programFilter.value || t.program_type === programFilter.value) &&
      (!intensity.value || t.intensity_label === intensity.value) &&
      (!ownership.value ||
        (ownership.value === "premade" ? t.is_premade : !t.is_premade))
  )
);
const message = (e) =>
  Object.values(e?.response?.data?.errors || {})
    .flat()
    .join(" ") ||
  e?.response?.data?.message ||
  "Unable to complete this action.";
async function load() {
  const request = ++generation;
  loading.value = true;
  error.value = "";
  selected.value = null;
  editing.value = null;
  selectedPlayers.value = [];
  selectedGroups.value = [];
  wholeTeam.value = false;
  players.value = [];
  groups.value = [];
  templates.value = [];
  try {
    const [library, roster, groupList] = await Promise.all([
      axiosGet("coach/workout-templates"),
      teamId.value
        ? axiosGet(`coach/teams/${teamId.value}`)
        : Promise.resolve({ data: { data: [] } }),
      axiosGet("coach/player-groups"),
    ]);
    if (request !== generation) return;
    templates.value = library.data.data;
    players.value = (roster.data.data || []).map((p) => ({
      id: String(p.id ?? p.user_id),
      name:
        p.name?.full ||
        [p.profile?.first_name, p.profile?.last_name]
          .filter(Boolean)
          .join(" ") ||
        "Player",
    }));
    groups.value = (groupList.data.data || []).filter(
      (g) => g.team_id === teamId.value
    );
  } catch (e) {
    if (request === generation) error.value = message(e);
  } finally {
    if (request === generation) loading.value = false;
  }
}
watch(teamId, load, { immediate: true });
onBeforeUnmount(() => generation++);
function preview(t) {
  selected.value = t;
  useRequest = null;
}
async function duplicate(t) {
  busy.value = true;
  error.value = "";
  try {
    const { data } = await axiosPost(
      `coach/workout-templates/${t.id}/duplicate`,
      {}
    );
    templates.value.push(data.data);
    editing.value = data.data;
  } catch (e) {
    error.value = message(e);
  } finally {
    busy.value = false;
  }
}
async function saveTemplate(data) {
  busy.value = true;
  error.value = "";
  try {
    const response = data.id
      ? await axiosPut(`coach/workout-templates/${data.id}`, data)
      : await axiosPost("coach/workout-templates", data);
    const t = response.data.data;
    templates.value = [...templates.value.filter((x) => x.id !== t.id), t];
    editing.value = null;
    selected.value = t;
  } catch (e) {
    error.value = message(e);
  } finally {
    busy.value = false;
  }
}
async function useTemplate() {
  if (!selected.value || !teamId.value) return;
  busy.value = true;
  error.value = "";
  const payload = {
    team_id: teamId.value,
    date: date.value,
    player_ids: selectedPlayers.value,
    group_ids: selectedGroups.value,
    whole_team: wholeTeam.value,
  };
  const key = JSON.stringify([selected.value.id, payload]);
  if (!useRequest || useRequest.key !== key)
    useRequest = { key, id: crypto.randomUUID() };
  try {
    const { data } = await axiosPost(
      `coach/workout-templates/${selected.value.id}/use`,
      { id: useRequest.id, ...payload }
    );
    await router.push(plannerLink(date.value, "edit", data.data.id));
  } catch (e) {
    error.value = message(e);
  } finally {
    busy.value = false;
  }
}
function addProgram(t) {
  programTemplate.value = t.id;
  tab.value = "programs";
  selected.value = null;
}
function newTemplate() {
  editing.value = {
    name: "",
    sport: "baseball",
    category: "Pitching",
    program_type: "",
    description: "",
    intensity_label: "Coach-defined",
    sections: [
      {
        name: "Warm-Up",
        section_type: "movement_prep",
        instructions: "",
        exercises: [],
      },
    ],
  };
}
</script>
<template>
  <Layout
    ><main class="workout-library">
      <header>
        <div>
          <span class="eyebrow">FMTRX TRAINING</span>
          <h1>Workout Library</h1>
          <p>
            Reusable workouts. Coach-controlled programs. Every athlete's
            assignment keeps its own copy.
          </p>
        </div>
        <RouterLink :to="plannerLink(date)">Back to Daily Planner</RouterLink>
      </header>
      <nav class="toolbar">
        <button
          :class="{ primary: tab === 'library' }"
          @click="tab = 'library'"
        >
          Templates</button
        ><button
          :class="{ primary: tab === 'programs' }"
          @click="tab = 'programs'"
        >
          Program Builder</button
        ><button @click="newTemplate">＋ Custom template</button>
      </nav>
      <p v-if="error" role="alert" class="warning">
        {{ error }} <button @click="load">Reload</button>
      </p>
      <p v-if="loading" role="status">Loading workout library…</p>
      <TemplateEditor
        v-if="editing"
        :key="editing.id || 'new'"
        :template="editing"
        :busy="busy"
        @cancel="editing = null"
        @save="saveTemplate"
      /><template v-else-if="tab === 'library'"
        ><div class="toolbar">
          <input
            v-model="search"
            placeholder="Search workouts…"
            aria-label="Search templates"
          /><select v-model="category" aria-label="Category">
            <option value="">All categories</option>
            <option v-for="v in choices('category')" :key="v">
              {{ v }}
            </option></select
          ><select v-model="programFilter" aria-label="Program">
            <option value="">All programs</option>
            <option v-for="v in choices('program_type')" :key="v">
              {{ v }}
            </option></select
          ><select v-model="intensity" aria-label="Intensity">
            <option value="">All intensities</option>
            <option v-for="v in choices('intensity_label')" :key="v">
              {{ v }}
            </option></select
          ><select v-model="ownership" aria-label="Ownership">
            <option value="">All templates</option>
            <option value="premade">Premade</option>
            <option value="custom">Custom / shared</option>
          </select>
        </div>
        <section v-if="selected" class="workout-panel preview">
          <header>
            <div>
              <h2>{{ selected.name }}</h2>
              <p>{{ selected.description }}</p>
            </div>
            <button @click="selected = null">Close preview</button>
          </header>
          <section v-for="section in selected.sections" :key="section.id">
            <h3>{{ section.name }}</h3>
            <p>{{ section.instructions }}</p>
            <ol>
              <li v-for="exercise in section.exercises" :key="exercise.id">
                <strong>{{ exercise.exercise_name }}</strong>
                <p>
                  {{ exercise.sets_min
                  }}{{
                    exercise.sets_max !== exercise.sets_min
                      ? "–" + exercise.sets_max
                      : ""
                  }}
                  sets · {{ exercise.prescription_text }}
                </p>
                <small
                  >{{ exercise.equipment }} ·
                  {{ exercise.ball_weights?.join(", ") }}</small
                >
                <p v-if="exercise.instructions">{{ exercise.instructions }}</p>
                <a
                  v-if="/^https?:\/\//i.test(exercise.metadata?.video_url || '')"
                  :href="exercise.metadata.video_url"
                  target="_blank"
                  rel="noopener"
                  >View demonstration</a
                ><a v-else-if="exercise.exercise_name==='Jaeger Band Series'" href="/images/training/j-band-exercise-sheet.jpg" target="_blank" rel="noopener noreferrer">Open J-Band reference</a><small v-else>Demonstration not provided</small>
              </li>
            </ol>
          </section>
          <div class="assignment-box">
            <h3>Use on a Daily Plan</h3>
            <label>Workout date<input v-model="date" type="date" /></label
            ><label
              ><input type="checkbox" v-model="wholeTeam" />Assign whole
              team</label
            >
            <div v-if="!wholeTeam" class="athlete-options">
              <label v-for="p in players" :key="p.id"
                ><input
                  type="checkbox"
                  :value="p.id"
                  v-model="selectedPlayers"
                />{{ p.name }}</label
              ><label v-for="g in groups" :key="g.id"
                ><input
                  type="checkbox"
                  :value="g.id"
                  v-model="selectedGroups"
                />Group: {{ g.name }}</label
              >
            </div>
            <p>
              Creates a draft copy on this date. Review assignments and
              prescriptions before publishing.
            </p>
            <button
              class="primary"
              :disabled="busy || !teamId || !date"
              @click="useTemplate"
            >
              Use template / assign players</button
            ><button :disabled="busy" @click="duplicate(selected)">
              Duplicate & customize</button
            ><button @click="addProgram(selected)">Add to program</button>
          </div>
        </section>
        <nav class="toolbar" aria-label="Workout library"><button @click="libraryView='premade'">FMTRX WORKOUTS</button><button @click="libraryView='mine'">MY WORKOUTS</button><button @click="libraryView='recent'">RECENT</button></nav>
        <div class="template-grid">
          <article v-for="t in filtered" :key="t.id" class="workout-panel">
            <small
              >{{ t.is_premade ? "PREMADE" : "CUSTOM" }} · v{{
                t.version
              }}</small
            >
            <h2>{{ t.name }}</h2>
            <p>{{ t.category }}</p>
            <div class="tags">
              <span>{{ t.intensity_label }}</span
              ><span>{{
                t.estimated_duration_minutes
                  ? t.estimated_duration_minutes + " min"
                  : "Duration not configured"
              }}</span
              ><span>{{ t.sections.length }} sections</span
              ><span
                >{{
                  t.sections.reduce((n, s) => n + s.exercises.length, 0)
                }}
                exercises</span
              >
            </div>
            <p>{{ t.description }}</p>
            <div class="toolbar">
              <button @click="preview(t)">Use Today / Preview</button
              ><button :disabled="busy" @click="duplicate(t)">
                Duplicate & customize</button
              ><button v-if="!t.is_premade" @click="editing = t">
                Edit custom</button
              ><button @click="addProgram(t)">Add to program</button
              ><button @click="preview(t)">Assign players</button>
            </div>
          </article>
        </div>
        <p v-if="!loading && !filtered.length">
          No matching templates. Create a custom workout or load the premade
          library.
        </p></template
      ><ProgramBuilder @template-created="templates.push($event)"
        v-else-if="teamId && !loading"
        :key="teamId + programTemplate"
        :templates="templates"
        :players="players"
        :groups="groups"
        :team-id="String(teamId)"
        :initial-template="programTemplate"
      />
      <p v-else-if="!teamId">Select a team to build and assign a program.</p>
    </main></Layout
  >
</template>
<style>
.workout-library {
  padding: 25px;
  max-width: 1550px;
  margin: auto;
  color: #e7eef9;
  background: #091323;
  min-height: 90vh;
}
.workout-library header,
.workout-library .toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 18px;
}
.workout-library header > div:first-child {
  flex: 1;
}
.workout-library h1 {
  font-size: 28px;
  font-weight: 800;
}
.workout-library h2 {
  font-size: 21px;
  font-weight: 750;
}
.workout-library h3 {
  font-size: 16px;
  font-weight: 700;
  margin: 12px 0;
}
.workout-library p {
  font-size: 13px;
  color: #a1b4d0;
  line-height: 1.65;
  margin: 9px 0;
  white-space: pre-wrap;
}
.workout-library small {
  display: block;
  font-size: 11px;
  color: #97aecb;
}
.workout-library .eyebrow {
  font-size: 10px;
  color: #ff5661;
  letter-spacing: 2px;
}
.workout-library :is(button, input, select, textarea) {
  border: 1px solid #344b68;
  border-radius: 6px;
  padding: 9px 12px;
  color: #dce8f9;
  background: #17263d;
  font-size: 12px;
  max-width: 100%;
}
.workout-library button {
  min-height: 38px;
}
.workout-library :is(button, input, select, textarea):focus-visible {
  outline: 2px solid #7db9fb;
  outline-offset: 2px;
}
.workout-library button:disabled {
  opacity: 0.45;
}
.workout-library input[type="checkbox"] {
  width: auto;
  margin-right: 7px;
}
.workout-library .primary {
  background: #cc2434;
  border-color: #ec4654;
  color: white;
}
.workout-library a {
  color: #8bc1ff;
  font-size: 12px;
}
.workout-library .workout-panel {
  padding: 20px;
  border: 1px solid #2c415f;
  border-radius: 10px;
  background: #0f1d30;
  margin-bottom: 18px;
}
.workout-library .template-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}
.workout-library .tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.workout-library .tags span {
  font-size: 10px;
  padding: 5px 8px;
  background: #26364d;
  border-radius: 5px;
}
.workout-library .form-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin: 12px 0;
}
.workout-library label {
  display: block;
  font-size: 12px;
  color: #a8bed9;
  margin: 8px 0;
}
.workout-library label :is(input:not([type="checkbox"]), select, textarea) {
  display: block;
  width: 100%;
  margin-top: 5px;
}
.workout-library textarea {
  min-height: 70px;
  width: 100%;
}
.workout-library .exercise-edit {
  padding: 16px;
  margin: 12px 0;
  background: #091425;
  border: 1px solid #263d5c;
  border-radius: 8px;
}
.workout-library .athlete-options {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
  max-height: 230px;
  overflow: auto;
  margin: 15px 0;
}
.workout-library .preview ol {
  list-style: decimal;
  padding-left: 25px;
}
.workout-library .preview li {
  padding: 13px;
  border-bottom: 1px solid #2b3d58;
}
.workout-library .assignment-box {
  border-top: 2px solid #b63242;
  margin-top: 20px;
  padding-top: 20px;
}
.workout-library .assignment-box button {
  margin: 5px;
}
.workout-library .program-scroll {
  overflow: auto;
}
.workout-library .program-week {
  display: grid;
  grid-template-columns: repeat(7, minmax(150px, 1fr));
  min-width: 1080px;
  gap: 8px;
}
.workout-library .program-week > section {
  border: 1px solid #2e4665;
  border-radius: 8px;
  padding: 10px;
}
.workout-library .program-week article {
  background: #1c2d45;
  padding: 10px;
  border-left: 3px solid #dc4252;
  border-radius: 5px;
  margin: 10px 0;
  font-size: 12px;
}
.workout-library .program-week button {
  font-size: 10px;
  width: 100%;
  margin-top: 6px;
}
.workout-library .editor-overlay {
  position: fixed;
  inset: 20px;
  z-index: 100;
  background: #0b1628;
  overflow: auto;
  padding: 25px;
  border: 2px solid #59799e;
  border-radius: 12px;
}
.workout-library .warning {
  padding: 13px;
  border: 1px solid #7b5634;
  background: #372516;
  color: #f1c58e;
}
@media (max-width: 1050px) {
  .workout-library .template-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 650px) {
  .workout-library {
    padding: 14px;
  }
  .workout-library .template-grid,
  .workout-library .form-grid {
    grid-template-columns: 1fr;
  }
  .workout-library .editor-overlay {
    inset: 6px;
    padding: 14px;
  }
}
</style>
