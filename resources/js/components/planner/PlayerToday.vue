<script setup>
import { mergePendingPlannerDay } from "@/features/planner/lib/pendingDay";
import TemplateExerciseActuals from "@/components/workouts/TemplateExerciseActuals.vue";
import { ref, watch, onMounted, onBeforeUnmount } from "vue";
import { useAxiosAuth } from "@/composables/axios-auth";
import { useTrainingStore } from "@/store/training";
import { useUserStore } from "@/store/user";
import { useRouter } from "vue-router";
import {
  localDateKey,
  shiftCalendarDate,
} from "@/features/planner/lib/calendar";
const emit = defineEmits(["history"]);
const { axiosGet, axiosPost } = useAxiosAuth(),
  router = useRouter();
const date = ref(localDateKey()),
  plans = ref([]),
  ledger = ref(null),
  loading = ref(false),
  error = ref(""),
  busy = ref(false),
  sessions = ref({}),
  expanded = ref({});
const user = useUserStore();
const upcoming = ref([]);
let generation = 0,
  hydrated = false,
  displayedDate = date.value;
const normalize = (p) => ({
  ...p,
  progress: {
    ...p.progress,
    version: p.progress?.version || 0,
    items: { ...p.progress?.items },
    readiness: { ...p.progress?.readiness },
    post_training: { ...p.progress?.post_training },
  },
});
const cacheKey = (day) => `fmtrx-planner-v2:${user.userData.id}:${day}`;
function cached(day) {
  try {
    return JSON.parse(localStorage.getItem(cacheKey(day)) || "[]");
  } catch {
    return [];
  }
}
function persist() {
  if (!user.userData.id) return;
  try {
    localStorage.setItem(cacheKey(displayedDate), JSON.stringify(plans.value));
  } catch {
    error.value = "Device storage is full. Save your changes before leaving.";
  }
}
watch(
  plans,
  () => {
    if (hydrated) persist();
  },
  { deep: true }
);
async function load() {
  const seq = ++generation,
    requestedDate = date.value;
  if (hydrated) persist();
  loading.value = true;
  try {
    const r = await axiosGet(`player/planner/day?date=${requestedDate}`);
    if (seq !== generation) return;
    const pending = cached(requestedDate);
    hydrated = false;
    plans.value = r.data.data.map((p) =>
      mergePendingPlannerDay(
        normalize(p),
        pending.find((c) => c.id === p.id && c.pending)
      )
    );
    displayedDate = requestedDate;
    ledger.value = r.data.throw_ledger;
    upcoming.value = r.data.upcoming || [];
    error.value = plans.value.some((p) => p.pending)
      ? "Unsaved changes on this device are preserved. Save to sync."
      : "";
  } catch (e) {
    if (seq === generation) {
      plans.value = cached(requestedDate);
      displayedDate = requestedDate;
      error.value = "Offline. Cached work is shown. Save again when connected.";
    }
  } finally {
    if (seq === generation) {
      loading.value = false;
      hydrated = true;
    }
  }
}
function markPending(plan) {
  plan.pending = true;
  persist();
}
function discardPending(plan) {
  if (
    !confirm(
      "Discard this device’s unsaved changes and load the latest saved results?"
    )
  )
    return;
  plan.pending = false;
  persist();
  load();
}
watch(date, load);
onMounted(load);
const onFocus = () => {
  if (!busy.value) load();
};
onMounted(() => window.addEventListener("focus", onFocus));
onBeforeUnmount(() => {
  persist();
  generation++;
  window.removeEventListener("focus", onFocus);
});
function actual(plan, item) {
  return (plan.progress.items[item.id] ||= { done: false });
}
function sets(plan, item) {
  const a = actual(plan, item);
  return (a.sets ||= Array.from(
    { length: item.setList?.length || Number(item.sets_min || item.sets) || 1 },
    () => ({ weight: null, reps: null, rpe: null, done: false })
  ));
}
function quick(plan, item) {
  return (actual(plan, item).quick_throws ||= []);
}
function addThrows(plan, item) {
  markPending(plan);
  quick(plan, item).push({
    count: 0,
    category: "warmup_throws",
    ball_weight: "",
    intent: null,
    timestamp: new Date().toISOString(),
  });
}
async function save(plan, finish = false) {
  if (busy.value) return;
  busy.value = true;
  error.value = "";
  markPending(plan);
  const submitted = JSON.stringify(plan.progress),
    requestedDate = displayedDate;
  try {
    const payload = {
      assignment_version: plan.assignment?.prescription_override?.version || 0,
      version: plan.progress.version,
      items: plan.progress.items,
      readiness: plan.progress.readiness,
      post_training: plan.progress.post_training,
      ...(finish ? { completed_at: new Date().toISOString() } : {}),
    };
    const r = await axiosPost(
      `player/daily-plans/${plan.id}/progress`,
      payload
    );
    // Editing during a request must never lose the later edits.
    if (JSON.stringify(plan.progress) === submitted) {
      plan.progress = normalize({ progress: r.data.data }).progress;
      plan.pending = false;
      plan.actual_results = plan.progress.items;
      plan.completion = {
        ...plan.completion,
        completed_at: plan.progress.completed_at,
      };
    } else plan.progress.version = r.data.data.version;
    persist();
    const refreshed = await axiosGet(
      `player/planner/day?date=${requestedDate}`
    );
    if (displayedDate === requestedDate)
      ledger.value = refreshed.data.throw_ledger;
  } catch (e) {
    error.value =
      e?.response?.data?.message ||
      "Offline changes are saved on this device. Retry when connected.";
    persist();
  } finally {
    busy.value = false;
  }
}
async function available(plan) {
  try {
    const r = await axiosGet(`player/daily-plans/${plan.id}/sessions`);
    sessions.value[plan.id] = r.data.data;
  } catch (e) {
    error.value = "Unable to load sessions. Try again.";
  }
}
function matches(session, item) {
  const d = item.linked_session;
  return (
    d &&
    session.type === d.practice_type &&
    (!d.mode || session.modes === d.mode)
  );
}
async function launch(plan, item) {
  if (busy.value) return;
  busy.value = true;
  try {
    const d = item.linked_session;
    if (["cage", "live_ab"].includes(d.type)) {
      error.value =
        "This session needs its normal setup. Open Sessions, record your workout, then use Link completed session here.";
      return;
    }
    const r = await axiosPost("training", {
      team: plan.team_id,
      type: d.practice_type,
      ...(d.mode ? { modes: d.mode } : {}),
      note: plan.name,
      players: [{ id: user.userData.id, sort: 0 }],
      planner_plan_id: plan.id,
      planner_item_id: item.id,
    });
    const training = useTrainingStore();
    training.cleanListPlayer();
    training.setCountBallsTraining(0);
    training.setDataTraining(r.data.data);
    training.countThrowArray[user.userData.id] = { balls: 0, bxs: 0, set: 1 };
    sessionStorage.setItem(
      "fmtrx-planner-session-return",
      JSON.stringify({
        plan_id: plan.id,
        item_id: item.id,
        session_id: r.data.data.id,
        user_id: user.userData.id,
        return_to: location.pathname,
      })
    );
    const path = d.mode ? `/track/training-mode/${d.mode}` : "/track/bullpen";
    await router.push({
      path,
      query: { planner_plan: plan.id, planner_item: item.id },
    });
  } catch (e) {
    error.value =
      e?.response?.data?.message || "Unable to start this session. Try again.";
  } finally {
    busy.value = false;
  }
}
async function adjust(plan, behavior) {
  if (
    !confirm(
      behavior === "shift"
        ? "Move this missed workout and the remaining program schedule forward?"
        : "Replace today’s workout from this program with the missed workout?"
    )
  )
    return;
  try {
    await axiosPost(`player/daily-plans/${plan.id}/schedule-adjustment`, {
      date: localDateKey(),
      behavior,
      request_id: crypto.randomUUID(),
    });
    await load();
  } catch (e) {
    error.value =
      e?.response?.data?.message || "Schedule could not be changed.";
  }
}
</script>
<template>
  <section class="planner-studio player-today">
    <header class="studio-hero">
      <div>
        <small>FMTRX · TRAINING</small>
        <h1>Today's Plan</h1>
        <p>Complete your assigned work. Log each session and stay on track.</p>
      </div>
    </header>
    <div class="studio-toolbar">
      <button @click="date = shiftCalendarDate(date, -1)">‹</button
      ><input type="date" v-model="date" aria-label="Training date" /><button
        @click="date = shiftCalendarDate(date, 1)"
      >
        ›</button
      ><button @click="date = localDateKey()">Today</button
      ><button @click="load">Refresh</button
      ><button @click="emit('history')">Upcoming & history</button>
    </div>
    <p v-if="error" role="alert">{{ error }}</p>
    <p v-if="loading">Loading…</p>
    <p v-if="!loading && !plans.length">
      No published training scheduled for this day.
    </p>
    <aside class="studio-detail" v-if="upcoming.length">
      <h2>Next 7 days</h2>
      <button v-for="next in upcoming" :key="next.id" @click="date = next.date">
        {{ next.date }} · {{ next.name }}
      </button>
    </aside>
    <article
      v-for="plan in plans"
      :key="plan.id"
      class="studio-detail"
      @input="markPending(plan)"
      @change="markPending(plan)"
    >
      <small>{{ plan.source.name || "Assigned training" }}</small>
      <h2>{{ plan.name }}</h2>
      <p v-if="plan.pending">
        Changes pending ·
        <button @click="discardPending(plan)">Reload saved results</button>
      </p>
      <p>{{ plan.primary_goal }}</p>
      <p v-if="plan.update_status?.has_update">
        Coach updated this workout · {{ plan.update_status.update_message }}
      </p>
      <details>
        <summary>
          How are you feeling today? ·
          {{ plan.readiness.score ?? "Not recorded" }} / 100
        </summary>
        <div class="today-fields">
          <label
            v-for="key in [
              'sleep_hours',
              'sleep_quality',
              'energy',
              'overall_soreness',
              'arm_soreness',
              'shoulder_soreness',
              'elbow_soreness',
              'stress',
              'motivation',
            ]"
            :key="key"
            >{{ key.replaceAll("_", " ")
            }}<input
              type="number"
              v-model.number="plan.progress.readiness[key]"
              min="0"
              :max="
                key === 'sleep_hours'
                  ? 24
                  : [
                      'sleep_quality',
                      'energy',
                      'overall_soreness',
                      'stress',
                      'motivation',
                    ].includes(key)
                  ? 5
                  : 10
              " /></label
          ><label
            ><input
              type="checkbox"
              v-model="plan.progress.readiness.pain_flag"
            />Pain reported</label
          >
        </div>
        <button :disabled="busy" @click="save(plan)">Save readiness</button>
        <p>{{ plan.readiness.alerts.join(" · ") }}</p>
      </details>
      <section
        v-for="block in plan.blocks"
        :key="block.type"
        class="today-block"
      >
        <h3>{{ block.title }}</h3>
        <p>{{ block.note }}</p>
        <article v-for="item in block.items" :key="item.id">
          <button
            class="today-item"
            @click="expanded[item.id] = !expanded[item.id]"
          >
            <strong>{{ item.name }}</strong
            ><span
              >{{
                plan.actual_results[item.id]?.done
                  ? "✓ Completed"
                  : item.execution_type.replaceAll("_", " ")
              }}
              ▾</span
            >
          </button>
          <div v-if="expanded[item.id]" class="today-actual">
            <p>{{ item.prescription_text || item.coachCue }}</p>
            <p>{{ item.note }}</p>
            <details v-if="item.merged_prescriptions?.length">
              <summary>Combined prescriptions · record one session</summary>
              <p
                v-for="(part, index) in item.merged_prescriptions"
                :key="index"
              >
                {{ part.name }} · {{ part.prescription_text || part.coachCue }}
              </p>
            </details>
            <TemplateExerciseActuals
              v-if="
                item.metadata?.tracking_type === 'radar' ||
                item.name === 'Jaeger Band Series'
              "
              :item="item"
              :model-value="actual(plan, item)"
              @update:model-value="
                plan.progress.items[item.id] = $event;
                markPending(plan);
              "
            /><template
              v-else-if="item.metadata?.tracking_type === 'quick_throws'"
              ><div
                v-for="(entry, index) in quick(plan, item)"
                :key="index"
                class="today-fields"
              >
                <label
                  >Throw category<select v-model="entry.category">
                    <option value="warmup_throws">Warm-up</option>
                    <option value="catch_play_throws">Catch play</option>
                    <option value="flat_ground_throws">Flat ground</option>
                    <option value="other_throws">Other throws</option>
                  </select></label
                >
                <label
                  >Ball weight<input
                    v-model="entry.ball_weight"
                    placeholder="e.g. 1 kg" /></label
                ><label
                  >Throws<input
                    type="number"
                    v-model.number="entry.count"
                    min="0" /></label
                ><label
                  >Intent %<input
                    type="number"
                    v-model.number="entry.intent"
                    min="0"
                    max="100" /></label
                ><button
                  @click="
                    actual(plan, item).quick_throws.splice(index, 1);
                    markPending(plan);
                  "
                >
                  Remove
                </button>
              </div>
              <button @click="addThrows(plan, item)">
                ＋ Quick count
              </button></template
            >
            <template v-else-if="block.type.startsWith('strength')"
              ><div
                class="today-fields"
                v-for="(set, index) in sets(plan, item)"
                :key="index"
              >
                <strong>Set {{ index + 1 }}</strong
                ><label
                  >Weight (lb)<input
                    type="number"
                    v-model.number="set.weight"
                    min="0" /></label
                ><label
                  >Reps<input
                    type="number"
                    v-model.number="set.reps"
                    min="0" /></label
                ><label
                  >RPE<input
                    type="number"
                    v-model.number="set.rpe"
                    min="1"
                    max="10" /></label
                ><label
                  ><input type="checkbox" v-model="set.done" />Complete</label
                >
              </div>
              <p>
                Total volume:
                {{
                  sets(plan, item).reduce(
                    (n, s) =>
                      n +
                      (s.done
                        ? Number(s.weight || 0) * Number(s.reps || 0)
                        : 0),
                    0
                  )
                }}
                lb
              </p></template
            >
            <template v-else-if="item.execution_type === 'LAUNCH_SESSION'"
              ><button
                v-if="item.linked_session.web_path"
                class="primary"
                @click="launch(plan, item)"
              >
                Start {{ item.linked_session.label }}</button
              ><button @click="available(plan)">Link completed session</button
              ><select
                v-model="actual(plan, item).session_id"
                aria-label="Existing session"
              >
                <option :value="null">Choose session</option>
                <option
                  v-for="session in (sessions[plan.id] || []).filter((s) =>
                    matches(s, item)
                  )"
                  :key="session.id"
                  :value="session.id"
                >
                  {{ session.started }} ·
                  {{ session.is_completed ? "Completed" : "In progress" }}
                </option>
              </select></template
            >
            <template v-else-if="item.execution_type === 'LOG'"
              ><label
                >Actual reps<input
                  type="number"
                  v-model.number="actual(plan, item).actual_reps"
                  min="0" /></label
            ></template>
            <label
              >Notes<input v-model="actual(plan, item).player_note" /></label
            ><label
              ><input
                type="checkbox"
                v-model="actual(plan, item).done"
              />Complete</label
            ><button :disabled="busy" @click="save(plan)">Save result</button>
          </div>
        </article>
      </section>
      <details open>
        <summary>Post-training check-in</summary>
        <div class="today-fields">
          <label
            v-for="key in [
              'overall_effort',
              'overall_fatigue',
              'arm_fatigue',
              'arm_soreness',
            ]"
            :key="key"
            >{{ key.replaceAll("_", " ")
            }}<input
              type="number"
              v-model.number="plan.progress.post_training[key]"
              min="0"
              max="10" /></label
          ><label
            ><input
              type="checkbox"
              v-model="plan.progress.post_training.pain"
            />Pain</label
          ><label
            >Notes<textarea
              v-model="plan.progress.post_training.notes"
            ></textarea>
          </label>
        </div>
        <p>
          Arm fatigue or soreness of 7+, pain, or overall fatigue of 8+ flags
          your response for coach review.
        </p>
      </details>
      <button class="primary" :disabled="busy" @click="save(plan, true)">
        {{ busy ? "Saving…" : "Finish & Send to Coach →" }}
      </button>
      <div
        v-if="date < localDateKey() && !plan.completion.completed_at"
        class="studio-toolbar"
      >
        <strong>Missed workout?</strong
        ><button @click="adjust(plan, 'shift')">Shift schedule forward</button
        ><button @click="adjust(plan, 'replace')">Replace today</button>
      </div>
    </article>
    <aside v-if="ledger" class="studio-detail">
      <h2>Daily Throw Ledger</h2>
      <p>
        {{ ledger.total_throws }} total throws ·
        {{ ledger.warmup_throws }} warm-up · {{ ledger.long_toss_throws }} long
        toss · {{ ledger.weighted_ball_throws }} weighted balls ·
        {{ ledger.bullpen_pitches }} bullpen pitches
      </p>
      <small>Volume only. Different throw types have different demands.</small>
    </aside>
  </section>
</template>
<style scoped>
.today-fields {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  padding: 12px 0;
}
.today-fields label {
  display: flex;
  flex-direction: column;
  max-width: 160px;
  text-transform: capitalize;
}
.today-fields input {
  width: 100%;
}
.today-fields input[type="checkbox"] {
  width: 22px;
  height: 22px;
}
.today-block {
  margin: 18px 0;
  padding: 14px;
  border: 1px solid var(--studio-line);
  border-radius: 12px;
}
.today-item {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  width: 100%;
  text-align: left;
}
.today-actual {
  padding: 16px;
}
.today-actual > label {
  display: block;
  margin: 10px 0;
}
.player-today details {
  padding: 16px 0;
}
.player-today summary {
  cursor: pointer;
  font-weight: 700;
}
.player-today select,
.player-today textarea {
  background: var(--studio-card);
  color: var(--studio-text);
  border: 1px solid var(--studio-line);
  padding: 12px;
}
</style>
