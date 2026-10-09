<script setup>
import { computed, ref, onMounted, watch } from "vue";
import { useAxiosAuth } from "@/composables/axios-auth";
import {
  localDateKey,
  shiftCalendarDate,
} from "@/features/planner/lib/calendar";
import { plannerLink } from "@/features/planner/lib/plannerLinks";
import {
  copyEntries,
  overlapWarnings,
} from "@/features/workouts/programSchedule";
import TemplateEditor from "./TemplateEditor.vue";
const props = defineProps({
  templates: Array,
  players: Array,
  groups: Array,
  teamId: String,
  initialTemplate: String,
});
const { axiosGet, axiosPost } = useAxiosAuth();
const uuid = () => crypto.randomUUID(),
  clone = (x) => JSON.parse(JSON.stringify(x));
const fresh = () => ({
  id: uuid(),
  version: 0,
  name: "FlameBangers Pitching Development",
  team_id: props.teamId,
  start_date: localDateKey(),
  weeks: 4,
  schedule: [],
});
const program = ref(fresh()),
  saved = ref([]),
  week = ref(0),
  templateId = ref(props.initialTemplate || props.templates[0]?.id || ""),
  selectedPlayers = ref([]),
  dayOffset = ref(0),
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
const safeWeeks = computed(() => Math.max(1, Math.min(52, Math.floor(Number(program.value.weeks) || 4))));
const totalDays = computed(() => safeWeeks.value * 7);
const locked = computed(() => program.value.status === "published");
const warnings = computed(() => overlapWarnings(program.value.schedule));
const startWeekday = computed(
  () => ((new Date(program.value.start_date + "T12:00:00").getDay() || 0) + 6) % 7
);
const calendarWeeks = computed(() =>
  Math.ceil((totalDays.value + startWeekday.value) / 7)
);
watch(calendarWeeks, count => { week.value = Math.max(0, Math.min(week.value, count - 1)); });
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
function add() {
  const t = props.templates.find((x) => x.id === templateId.value);
  if (t)
    program.value.schedule.push({
      id: uuid(),
      day_offset: Number(dayOffset.value),
      phase: phase.value,
      player_ids: [...selectedPlayers.value],
      template_id: t.id,
      snapshot: clone(t),
    });
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
  program.value = clone(p);
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
    delete e.daily_plan_id;
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
    const { data } = await axiosPost("coach/workout-programs", program.value);
    program.value = data.data;
    if (publish) {
      const r = await axiosPost(
        `coach/workout-programs/${program.value.id}/publish`,
        { version: program.value.version, workload_approved: approved.value }
      );
      program.value = r.data.data;
    }
    notice.value = publish
      ? "Program published. Assigned workouts are available to players."
      : "Program draft saved.";
    await load();
  } catch (e) {
    error.value = e?.response?.data?.message || "Program could not be saved.";
  } finally {
    busy.value = false;
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
      <h2>Pitching Program Builder</h2>
      <button @click="open(fresh())">New program</button
      ><button @click="duplicate">Copy program</button
      ><button :disabled="busy || locked" @click="save()">Save draft</button>
    </header>
    <p v-if="error" role="alert">{{ error }}</p>
    <p v-if="notice" role="status">{{ notice }}</p>
    <div class="toolbar">
      <label
        >Saved programs<select
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
            v-for="n in [4, 8, 10, 12]"
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
      <p>
        Calendar weeks run Monday–Sunday. No workouts are automatically
        scheduled.
      </p>
      <section class="workout-panel">
        <h3>Add workout to a day</h3>
        <div class="form-grid">
          <label
            >Template<select v-model="templateId">
              <option v-for="t in templates" :value="t.id" :key="t.id">
                {{ t.name }}
              </option>
            </select></label
          ><label
            >Day<select v-model.number="dayOffset">
              <option v-for="n in totalDays" :key="n" :value="n - 1">
                {{ shiftCalendarDate(program.start_date, n - 1) }}
              </option>
            </select></label
          ><label
            >Phase<select v-model="phase">
              <option v-for="p in phases" :key="p">{{ p }}</option>
            </select></label
          ><label
            >Add group<select @change="selectGroup($event.target.value)">
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
            ><input type="checkbox" v-model="selectedPlayers" :value="p.id" />{{
              p.name
            }}</label
          >
        </div>
        <button class="primary" @click="add">Add selected workout</button>
      </section>
    </fieldset>
    <div class="toolbar">
      <button :disabled="week === 0" @click="week--">‹</button
      ><strong>Week {{ week + 1 }} of {{ calendarWeeks }}</strong
      ><button :disabled="week >= calendarWeeks - 1" @click="week++">›</button>
    </div>
    <div class="program-scroll">
      <div class="program-week">
        <section v-for="day in days" :key="day.offset">
          <h3>
            {{
              new Date(day.date + "T12:00:00").toLocaleDateString(undefined, {
                weekday: "short",
              })
            }}
          </h3>
          <small>{{ day.date }}</small
          ><small v-if="day.offset < 0 || day.offset >= totalDays"
            >Outside program</small
          >
          <article
            v-for="entry in program.schedule.filter(
              (e) => e.day_offset === day.offset
            )"
            :key="entry.id"
          >
            <strong>{{ entry.snapshot.name }}</strong
            ><label v-if="!locked"
              >Phase<input v-model="entry.phase" maxlength="60"
            /></label>
            <p>{{ entry.phase }} · {{ entry.player_ids.length }} players</p>
            <template v-if="!locked"
              ><label
                >Move to day<select v-model.number="entry.day_offset">
                  <option
                    v-for="n in totalDays"
                    :key="n"
                    :value="n - 1"
                  >
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
                  <option v-for="t in templates" :key="t.id" :value="t.id">
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
              :to="plannerLink(day.date, 'edit', entry.daily_plan_id)"
              >Open assigned workout →</RouterLink
            >
          </article>
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
          ><button @click="copy(copyDayFrom - 1, copyDayTo - 1, 1)">Copy day</button>
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
          ><button @click="copy((copyWeekFrom - 1) * 7, (copyWeekTo - 1) * 7, 7)">
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
    <div v-if="editingEntry" class="editor-overlay">
      <TemplateEditor
        :template="editingEntry.snapshot"
        @cancel="editingEntry = null"
        @save="
          editingEntry.snapshot = $event;
          editingEntry = null;
          approved = false;
        "
      />
    </div>
  </div>
</template>
