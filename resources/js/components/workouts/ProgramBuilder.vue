<script setup>
import { computed, ref, onMounted, watch, onBeforeUnmount } from "vue";
import {useUserStore} from "@/store/user";
import { useAxiosAuth } from "@/composables/axios-auth";
import {
  localDateKey,
  shiftCalendarDate,
} from "@/features/planner/lib/calendar";
import { plannerLink } from "@/features/planner/lib/plannerLinks";
import {
  trainingDayOffsets,
  copyEntries,
  overlapWarnings,
} from "@/features/workouts/programSchedule";
import { Dialog, DialogPanel, DialogTitle } from "@headlessui/vue";
import TemplateEditor from "./TemplateEditor.vue";
const props = defineProps({
  templates: Array,
  players: Array,
  groups: Array,
  teamId: String,
  initialTemplate: String,
  initialDate: String,
});
const emit = defineEmits(["template-created"]);
const activeDay = ref(null),
  customWorkout = ref(null);
const dayEntries = computed(() =>
  program.value.schedule.filter((e) => e.day_offset === activeDay.value)
);
const dayDate = computed(() =>
  shiftCalendarDate(program.value.start_date, activeDay.value ?? 0)
);
function openDay(offset) {
  if (busy.value || offset < 0 || offset >= totalDays.value) return;
  activeDay.value = offset;
  editingEntry.value = null;
  customWorkout.value = null;
}
function closeDay() {
  if (!busy.value) {
    activeDay.value = null;
    editingEntry.value = null;
    customWorkout.value = null;
  }
}
function buildCustom() {
  customWorkout.value = {
    name: "New workout",
    sport: "baseball",
    category: "Custom",
    program_type: program.value.name,
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
async function saveWorkout(snapshot) {
  if (editingEntry.value) {
    editingEntry.value.snapshot = snapshot;
    editingEntry.value = null;
    approved.value = false;
    return;
  }
  if (busy.value) return;
  busy.value = true;
  error.value = "";
  try {
    const { data } = await axiosPost("coach/workout-templates", snapshot);
    emit("template-created", data.data);
    templateId.value = data.data.id;
    insertWorkout(data.data);
    customWorkout.value = null;
  } catch (e) {
    error.value = e?.response?.data?.message || "Workout could not be saved.";
  } finally {
    busy.value = false;
  }
}
const { axiosGet, axiosPost } = useAxiosAuth();
const uuid = () => crypto.randomUUID(),
  clone = (x) => JSON.parse(JSON.stringify(x));
const fresh = () => ({
  id: uuid(),
  version: 0,
  name: "New training plan",
  team_id: props.teamId,
  start_date: props.initialDate || localDateKey(),
  weeks: 4,
  training_settings: null,
  schedule: [],
});
const program = ref(fresh()),
  saved = ref([]),
  week = ref(0),
  templateId = ref(props.initialTemplate || props.templates[0]?.id || ""),
  selectedPlayers = ref([]),
  phase = ref("Foundation"),
  busy = ref(false),
  error = ref(""),
  notice = ref(""),
  approved = ref(false),
  editingEntry = ref(null),
  copyDayFrom = ref(1),
  copyDayTo = ref(2),
  copyWeekFrom = ref(1),
  copyWeekTo = ref(2);
watch(
  () => props.templates,
  (templates) => {
    if (!templates.some((t) => t.id === templateId.value))
      templateId.value = templates[0]?.id || "";
  },
  { immediate: true, deep: true }
);
const sessionsPerWeek = ref(3), trainingWeekdays = ref([1, 3, 5]);
const weekdayChoices = [{id:1,label:'Monday'},{id:2,label:'Tuesday'},{id:3,label:'Wednesday'},{id:4,label:'Thursday'},{id:5,label:'Friday'},{id:6,label:'Saturday'},{id:0,label:'Sunday'}];
const workoutSearch = ref(''), workoutSource = ref('all');
const availableWorkouts = computed(() => props.templates.filter(t =>
  (workoutSource.value === 'all' || (workoutSource.value === 'premade' ? t.is_premade : !t.is_premade)) &&
  `${t.name} ${t.category || ''}`.toLowerCase().includes(workoutSearch.value.toLowerCase())
));
watch(availableWorkouts, list => { if (!list.some(t => t.id === templateId.value)) templateId.value = list[0]?.id || ''; });
const plannedDays = computed(() => trainingDayOffsets(program.value.start_date, Number(program.value.weeks), program.value.training_settings?.weekdays || []));
function generateCalendar() {
  if (trainingWeekdays.value.length !== Number(sessionsPerWeek.value)) {
    error.value = `Choose exactly ${sessionsPerWeek.value} training days.`;
    return;
  }
  program.value.training_settings = {sessions_per_week: Number(sessionsPerWeek.value), weekdays: [...trainingWeekdays.value]};
  week.value = 0;
  error.value = '';
  notice.value = `${plannedDays.value.length} training dates ready. Click a date to choose its workout. Existing workouts are kept.`;
}
watch(() => program.value.id, () => {
  sessionsPerWeek.value = program.value.training_settings?.sessions_per_week || 3;
  trainingWeekdays.value = [...(program.value.training_settings?.weekdays || [1, 3, 5])];
});
const safeWeeks = computed(() =>
  Math.max(1, Math.min(52, Math.floor(Number(program.value.weeks) || 4)))
);
const totalDays = computed(() => safeWeeks.value * 7);
const locked = computed(() => false);
const sessionConflicts=ref([]), resolutions=ref([]);
const publishEntries=ref([]), publishPlayers=ref([]), saveState=ref('Saved');
let autosaveTimer=null, lastSaved=JSON.stringify(program.value), autosaveConflict=false;
const draftKey=()=>`fmtrx-program-draft:${useUserStore().userData.id}:${props.teamId}`;
const pendingDraft=ref(null);
try{pendingDraft.value=JSON.parse(localStorage.getItem(draftKey())||'null')}catch{}
function persistDraft(){try{localStorage.setItem(draftKey(),JSON.stringify(program.value))}catch{error.value='Device storage is full. Save before leaving.'}}
function queueSave(){clearTimeout(autosaveTimer);if(!autosaveConflict&&JSON.stringify(program.value)!==lastSaved){saveState.value='Changes pending';autosaveTimer=setTimeout(()=>{if(program.value.name.trim())save()},1200)}}
watch(program,()=>{persistDraft();if(!busy.value)queueSave()},{deep:true});
onBeforeUnmount(()=>{clearTimeout(autosaveTimer);if(JSON.stringify(program.value)!==lastSaved)persistDraft()});
function restoreDraft(){if(!pendingDraft.value)return;program.value=clone(pendingDraft.value);pendingDraft.value=null;saveState.value='Restored — save to sync';queueSave()}
const warnings = computed(() => overlapWarnings(program.value.schedule));
const startWeekday = computed(
  () =>
    ((new Date(program.value.start_date + "T12:00:00").getDay() || 0) + 6) % 7
);
const calendarWeeks = computed(() =>
  Math.ceil((totalDays.value + startWeekday.value) / 7)
);
watch(calendarWeeks, (count) => {
  week.value = Math.max(0, Math.min(week.value, count - 1));
});
const days = computed(() =>
  Array.from({ length: 7 }, (_, i) => ({
    offset: week.value * 7 + i - startWeekday.value,
    date: shiftCalendarDate(
      program.value.start_date,
      week.value * 7 + i - startWeekday.value
    ),
  }))
);
const phases = [
  "Foundation",
  "Development",
  "Intensification",
  "Performance",
  "Recovery / Reassessment",
];
async function load() {
  try {
    const { data } = await axiosGet("coach/workout-programs");
    saved.value = data.data.filter((p) => p.team_id === props.teamId);
  } catch {
    error.value = "Programs could not be loaded.";
  }
}
onMounted(load);
function insertWorkout(template) {
  if (locked.value || activeDay.value == null) return;
  program.value.schedule.push({
    id: uuid(),
    day_offset: activeDay.value,
    phase: phase.value,
    player_ids: [...selectedPlayers.value],
    template_id: template.id,
    snapshot: clone(template),
  });
  approved.value = false;
}
function add() {
  const template = props.templates.find((t) => t.id === templateId.value);
  if (template) insertWorkout(template);
}
function copy(from, to, count) {
  program.value.schedule.push(
    ...copyEntries(
      program.value.schedule,
      Number(from),
      Number(to),
      count,
      totalDays.value,
      uuid
    )
  );
  approved.value = false;
}
function open(p) {
  closeDay();
  program.value = clone(p);
  lastSaved=JSON.stringify(program.value); autosaveConflict=false;
  week.value = 0;
  approved.value = false;
  notice.value = "";
  error.value = "";
}
function duplicate() {
  const p = clone(program.value);
  p.id = uuid();
  p.version = 0;
  p.status = "draft";
  p.name += " (copy)";
  p.schedule = p.schedule.map((e) => {
    delete e.daily_plan_id; delete e.daily_plan_ids; delete e.published_version; delete e.published_at;
    return { ...e, id: uuid() };
  });
  open(p);
}
async function save(publish = false) {
  if (busy.value) return;
  busy.value = true;
  error.value = "";
  notice.value = "";
  try {
    const submitted=JSON.stringify(program.value);
    const { data } = await axiosPost("coach/workout-programs", JSON.parse(submitted));
    if(JSON.stringify(program.value)!==submitted){program.value.version=data.data.version;lastSaved=JSON.stringify(data.data);persistDraft();notice.value='Latest edits are waiting to save.';return false}
    program.value = data.data;
    lastSaved=JSON.stringify(program.value);saveState.value='Saved ✓';
    localStorage.removeItem(draftKey());pendingDraft.value=null;
    if (publish) {
      const r = await axiosPost(
        `coach/workout-programs/${program.value.id}/publish`,
        { resolutions: resolutions.value, version: program.value.version, workload_approved: approved.value, ...(publishEntries.value.length?{entry_ids:publishEntries.value}:{}), ...(publishPlayers.value.length?{player_ids:publishPlayers.value}:{}) }
      );
      program.value = r.data.data;sessionConflicts.value=[];resolutions.value=[];
      lastSaved=JSON.stringify(program.value);
    }
    notice.value = publish
      ? "Program published. Assigned workouts are available to players."
      : "Program draft saved.";
    await load();
    return true;
  } catch (e) {
    sessionConflicts.value=e?.response?.data?.conflicts||[];
    for(const conflict of sessionConflicts.value)if(!resolutions.value.some(r=>r.plan_id===conflict.plan_id&&r.player_id===conflict.player_id))resolutions.value.push({...conflict,action:'',date:conflict.date});
    autosaveConflict=e?.response?.status===409;saveState.value=autosaveConflict?'Conflict — reload latest':'Offline changes pending';
    error.value = e?.response?.data?.message || 'Program could not be saved.';
  } finally {
    busy.value = false;
    if(!error.value)queueSave();
  }
}
function selectGroup(id) {
  const g = props.groups.find((x) => x.id === id);
  selectedPlayers.value = [
    ...new Set([...selectedPlayers.value, ...(g?.member_ids || [])]),
  ];
}
</script>
<template>
  <div>
    <header>
      <h2>Program Builder</h2><button v-if="pendingDraft" @click="restoreDraft">Restore device draft</button><span role="status">{{saveState}}</span>
      <button :disabled="busy" @click="open(fresh())">New program</button
      ><button :disabled="busy" @click="duplicate">Copy program</button
      ><button :disabled="busy || locked" @click="save()">Save draft</button>
    </header>
    <p v-if="error" role="alert">{{ error }}</p>
    <p v-if="notice" role="status">{{ notice }}</p>
    <section v-if="sessionConflicts.length" class="workout-panel" role="alert"><h3>Session conflict — choose what to keep</h3><p>No assignments have changed. Recorded work cannot be replaced or merged.</p><div v-for="choice in resolutions" :key="choice.plan_id+choice.player_id"><strong>{{players.find(p=>p.id===choice.player_id)?.name||'Player'}} · {{choice.name}}</strong><select v-model="choice.action"><option value="">Choose a resolution</option><option value="keep_existing">Keep existing workout</option><option value="replace">Use new workout instead</option><option value="merge">Merge prescriptions into one workout</option><option value="move">Move new workout to another day</option></select><input v-if="choice.action==='move'" type="date" v-model="choice.date"></div><button :disabled="busy||resolutions.some(r=>!r.action)" @click="save(true)">Apply decisions & publish</button></section>
    <div class="toolbar">
      <label
        >Saved programs<select :disabled="busy"
          @change="
            saved.find((p) => p.id === $event.target.value) &&
              open(saved.find((p) => p.id === $event.target.value))
          "
        >
          <option value="">Select a program</option>
          <option v-for="p in saved" :key="p.id" :value="p.id">
            {{ p.name }} · {{ p.status }}
          </option>
        </select></label
      ><span>{{ program.status || "draft" }}</span>
    </div>
    <details class="workout-panel"><summary>Publish / selective update review</summary><p>Choose days to publish. Select athletes only when updating an already published day. Unselected athletes keep their current prescriptions.</p><label v-for="entry in program.schedule" :key="entry.id"><input type="checkbox" :value="entry.id" v-model="publishEntries">{{shiftCalendarDate(program.start_date,entry.day_offset)}} · {{entry.snapshot.name}} · {{entry.published_at?'Published':'Draft'}}</label><h3>Apply updates to</h3><label v-for="player in players" :key="player.id"><input type="checkbox" v-model="publishPlayers" :value="player.id">{{player.name}}</label></details>
    <fieldset :disabled="locked || busy">
      <div class="form-grid">
        <label>Program name<input v-model="program.name" required /></label
        ><label
          >Start date<input v-model="program.start_date" type="date" /></label
        ><label
          >Length (weeks)<input
            v-model.number="program.weeks"
            type="number"
            min="1"
            max="52"
        /></label>
        <div class="toolbar">
          <button
            v-for="n in [2, 4, 6, 8, 10, 12]"
            :key="n"
            @click="
              program.weeks = n;
              week = 0;
            "
          >
            {{ n }} weeks
          </button>
        </div>
      </div>
      <section class="workout-panel">
        <h3>Set your weekly training schedule</h3>
        <label>How many times per week?<select v-model.number="sessionsPerWeek"><option v-for="n in 7" :key="n" :value="n">{{ n }}</option></select></label>
        <p>Which days will players train? Choose {{ sessionsPerWeek }}.</p>
        <div class="toolbar"><label v-for="day in weekdayChoices" :key="day.id"><input type="checkbox" v-model="trainingWeekdays" :value="day.id"> {{ day.label }}</label></div>
        <button type="button" class="primary" :disabled="trainingWeekdays.length !== sessionsPerWeek" @click="generateCalendar">{{ program.training_settings ? 'Update training calendar' : 'Preload training calendar' }}</button>
        <p v-if="program.training_settings">{{ plannedDays.length }} training dates · Click each date below to choose a premade or a saved library workout.</p>
      </section>
    </fieldset>
    <div class="toolbar">
      <button :disabled="week === 0" @click="week--">‹</button
      ><strong>Week {{ week + 1 }} of {{ calendarWeeks }}</strong
      ><button :disabled="week >= calendarWeeks - 1" @click="week++">›</button>
    </div>
    <div class="program-scroll">
      <div class="program-week">
        <section
          v-for="day in days"
          :key="day.offset"
          :class="{ 'program-day-selected': activeDay === day.offset, 'program-training-day': plannedDays.includes(day.offset) }"
        >
          <button
            class="program-day-button"
            type="button"
            :disabled="busy || day.offset < 0 || day.offset >= totalDays"
            :aria-label="`${locked ? 'View' : 'Build'} workouts for ${
              day.date
            }`"
            @click="openDay(day.offset)"
          >
            <strong>{{
              new Date(day.date + "T12:00:00").toLocaleDateString(undefined, {
                weekday: "short",
              })
            }}</strong>
            <small>{{ day.date }}</small>
            <small v-if="day.offset < 0 || day.offset >= totalDays"
              >Outside program</small
            >
            <template v-else
              ><small>{{ plannedDays.includes(day.offset) ? 'Training day' : 'Rest / optional day' }}</small><span
                v-for="entry in program.schedule.filter(
                  (e) => e.day_offset === day.offset
                )"
                :key="entry.id"
                class="program-day-workout"
                >{{ entry.snapshot.name
                }}<small>{{ entry.player_ids.length }} players</small></span
              ><span class="program-day-action">{{
                locked ? "View day →" : "＋ Choose workout"
              }}</span></template
            >
          </button>
        </section>
      </div>
    </div>
    <fieldset :disabled="locked || busy">
      <section class="workout-panel">
        <h3>Copy schedule</h3>
        <div class="toolbar">
          <label
            >From day<input
              v-model.number="copyDayFrom"
              type="number"
              min="1"
              :max="totalDays" /></label
          ><label
            >To day<input
              v-model.number="copyDayTo"
              type="number"
              min="1"
              :max="totalDays" /></label
          ><button @click="copy(copyDayFrom - 1, copyDayTo - 1, 1)">
            Copy day
          </button>
        </div>
        <small
          >Day 1 is the program start date. Copying adds workouts without
          removing existing entries.</small
        >
        <div class="toolbar">
          <label
            >From week<input
              v-model.number="copyWeekFrom"
              type="number"
              min="1"
              :max="safeWeeks" /></label
          ><label
            >To week<input
              v-model.number="copyWeekTo"
              type="number"
              min="1"
              :max="safeWeeks" /></label
          ><button
            @click="copy((copyWeekFrom - 1) * 7, (copyWeekTo - 1) * 7, 7)"
          >
            Copy week</button
          ><button
            @click="
              Array.from({ length: safeWeeks - 1 }, (_, i) =>
                copy(0, (i + 1) * 7, 7)
              )
            "
          >
            Repeat week 1 for remaining weeks
          </button>
        </div>
        <small
          >Week 1 is the first week. Review copies before publishing.</small
        >
      </section>
      <p v-for="w in warnings" :key="w" class="warning">{{ w }}</p>
      <label
        ><input v-model="approved" type="checkbox" />I reviewed athlete
        assignments, throwing workload, recovery spacing, and all
        progressions.</label
      ><button
        class="primary"
        :disabled="!approved || !program.schedule.length"
        @click="save(true)"
      >
        Save & publish assigned workouts
      </button>
    </fieldset>
    <Dialog
      :open="activeDay !== null"
      @close="closeDay"
      class="program-day-dialog workout-library"
    >
      <div class="program-day-backdrop" aria-hidden="true"></div>
      <div class="program-day-container">
        <DialogPanel class="program-day-panel">
          <header>
            <div>
              <DialogTitle>{{
                locked ? "Day’s workouts" : "Build day’s workouts"
              }}</DialogTitle>
              <p>{{ dayDate }} · {{ program.name }}</p>
            </div>
            <button :disabled="busy" @click="closeDay">Back to calendar</button>
          </header>
          <p v-if="error" role="alert">{{ error }}</p>
          <p v-if="notice" role="status">{{ notice }}</p>
          <TemplateEditor
            v-if="editingEntry || customWorkout"
            :key="editingEntry?.id || 'new'"
            :template="editingEntry?.snapshot || customWorkout"
            :busy="busy"
            @cancel="
              editingEntry = null;
              customWorkout = null;
            "
            @save="saveWorkout"
          />
          <template v-else>
            <fieldset v-if="!locked" :disabled="busy">
              <section class="workout-panel">
                <h3>Add a workout</h3>
                <p v-if="!templates.length">
                  No saved templates are available. Build a custom workout
                  below.
                </p>
                <div class="toolbar"><label>Workout library<select v-model="workoutSource"><option value="all">All workouts</option><option value="premade">FMTRX premade workouts</option><option value="saved">Saved library workouts</option></select></label><label>Search workouts<input v-model="workoutSearch" placeholder="Hitting, pitching, Hybrid…" /></label></div>
                <p v-if="!availableWorkouts.length">No workouts match this selection.</p>
                <div class="form-grid">
                  <label
                    >Choose workout<select v-model="templateId">
                      <option v-for="t in availableWorkouts" :value="t.id" :key="t.id">
                        {{ t.name }}
                      </option>
                    </select></label
                  ><label
                    >Phase<select v-model="phase">
                      <option v-for="p in phases" :key="p">{{ p }}</option>
                    </select></label
                  ><label
                    >Add group<select
                      @change="selectGroup($event.target.value)"
                    >
                      <option value="">Choose group</option>
                      <option v-for="g in groups" :key="g.id" :value="g.id">
                        {{ g.name }}
                      </option>
                    </select></label
                  >
                </div>
                <button @click="selectedPlayers = players.map((p) => p.id)">
                  Select whole team</button
                ><button @click="selectedPlayers = []">Clear athletes</button>
                <div class="athlete-options">
                  <label v-for="p in players" :key="p.id"
                    ><input
                      type="checkbox"
                      v-model="selectedPlayers"
                      :value="p.id"
                    />{{ p.name }}</label
                  >
                </div>
                <button
                  class="primary"
                  :disabled="!templates.some((t) => t.id === templateId)"
                  @click="add"
                >
                  Load workout onto this date</button
                ><button @click="buildCustom">＋ Build custom workout</button>
              </section>
            </fieldset>
            <section class="workout-panel">
              <h3>Workouts for {{ dayDate }}</h3>
              <p v-if="!dayEntries.length">
                No workouts yet. Add a template or build a custom workout for
                this day.
              </p>
              <fieldset :disabled="busy">
                <article v-for="entry in dayEntries" :key="entry.id">
                  <strong>{{ entry.snapshot.name }}</strong
                  ><label v-if="!locked"
                    >Phase<input v-model="entry.phase" maxlength="60"
                  /></label>
                  <p>
                    {{ entry.phase }} · {{ entry.player_ids.length }} players
                  </p>
                  <template v-if="!locked"
                    ><label
                      >Move to day<select v-model.number="entry.day_offset">
                        <option v-for="n in totalDays" :key="n" :value="n - 1">
                          {{ shiftCalendarDate(program.start_date, n - 1) }}
                        </option>
                      </select></label
                    ><label
                      >Swap template<select
                        :value="entry.template_id"
                        @change="
                          entry.template_id = $event.target.value;
                          entry.snapshot = clone(
                            templates.find((t) => t.id === entry.template_id)
                          );
                        "
                      >
                        <option
                          v-for="t in templates"
                          :key="t.id"
                          :value="t.id"
                        >
                          {{ t.name }}
                        </option>
                      </select></label
                    ><button @click="editingEntry = entry">
                      Edit this day's prescription
                    </button>
                    <details>
                      <summary>Adjust athletes</summary>
                      <label v-for="p in players" :key="p.id"
                        ><input
                          v-model="entry.player_ids"
                          type="checkbox"
                          :value="p.id"
                        />{{ p.name }}</label
                      >
                    </details>
                    <button
                      @click="
                        program.schedule = program.schedule.filter(
                          (e) => e.id !== entry.id
                        )
                      "
                    >
                      Remove
                    </button></template
                  ><RouterLink
                    v-else
                    :to="plannerLink(dayDate, 'edit', entry.daily_plan_id)"
                    >Open assigned workout →</RouterLink
                  >
                </article>
              </fieldset>
            </section>
            <footer class="toolbar">
              <button :disabled="busy" @click="closeDay">
                Back to calendar</button
              ><button
                v-if="!locked"
                class="primary"
                :disabled="busy"
                @click="
                  save().then((ok) => {
                    if (ok) closeDay();
                  })
                "
              >
                {{ busy ? "Saving…" : "Save day to program" }}
              </button>
            </footer>
            <p v-if="!locked">
              Saving keeps this day in the program draft. Publish the program
              when you are ready to assign it to players.
            </p>
          </template>
        </DialogPanel>
      </div>
    </Dialog>
  </div>
</template>

<style>
.workout-library .program-training-day{border:1px solid #ef334b;border-radius:8px;background:#15243b}

.workout-library .program-day-button {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  text-align: left;
  width: 100%;
  min-height: 150px;
  background: transparent;
  border: 0;
  gap: 8px;
  padding: 8px;
}
.workout-library .program-day-button:not(:disabled):hover {
  background: #20324b;
}
.program-day-selected {
  outline: 2px solid #ef4444;
}
.program-day-workout {
  display: block;
  padding: 8px;
  background: #22324a;
  border-left: 3px solid #e33d4d;
  border-radius: 4px;
}
.program-day-action {
  color: #8bc1ff;
  margin-top: auto;
  font-size: 12px;
}
.program-day-dialog.workout-library {
  position: relative;
  z-index: 100;
  padding: 0;
  min-height: 0;
}
.program-day-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
}
.program-day-container {
  position: fixed;
  inset: 0;
  overflow-y: auto;
  padding: 24px;
  display: flex;
  align-items: flex-start;
  justify-content: center;
}
.program-day-panel {
  width: 100%;
  max-width: 1080px;
  background: #0b1628;
  border: 1px solid #455b78;
  border-radius: 14px;
  padding: 24px;
}
.program-day-panel article {
  padding: 16px;
  border: 1px solid #344b68;
  border-radius: 8px;
  margin: 12px 0;
}
.program-day-panel footer {
  position: sticky;
  bottom: -24px;
  background: #0b1628;
  padding: 16px 0;
  margin: 0;
}
.program-day-panel h2 {
  font-size: 24px;
}
@media (max-width: 650px) {
  .program-day-container {
    padding: 8px;
  }
  .program-day-panel {
    padding: 14px;
  }
  .program-day-panel footer {
    bottom: -14px;
  }
}
</style>
