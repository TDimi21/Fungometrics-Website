<script setup>
import { ref, computed, watch, defineAsyncComponent } from "vue";
import { storeToRefs } from "pinia";
import { useTeamStore } from "@/store/team";
import { useAxiosAuth } from "@/composables/axios-auth";
import {
  localDateKey,
  shiftCalendarDate,
} from "@/features/planner/lib/calendar";
const Library = defineAsyncComponent(() =>
  import("@/pages/workouts/WorkoutLibrary.vue")
);
const emit = defineEmits(["create", "edit", "advanced"]);
const { team } = storeToRefs(useTeamStore()),
  { axiosGet, axiosPost } = useAxiosAuth();
const teamId = computed(() =>
  String(team.value?.id_team ?? team.value?.id ?? "")
);
const date = ref(localDateKey()),
  tab = ref("today"),
  rows = ref([]),
  roster = ref([]),
  drafts = ref([]),
  error = ref(""),
  loading = ref(false),
  selected = ref(null),
  ledger = ref(null),
  reviewAction = ref("");
const editPrescription = ref(null),
  adjustmentReason = ref("");
let generation = 0;
const alerts = computed(() =>
  rows.value.filter(
    (p) =>
      (p.readiness.needs_attention || p.post_training.needs_attention) &&
      !p.alert_review
  )
);
const total = computed(() =>
  rows.value.reduce((n, p) => n + p.blocks.length, 0)
);
const complete = computed(
  () => rows.value.filter((p) => p.completion.completed_at).length
);
const players = computed(
  () => new Set(rows.value.map((p) => p.assignment.user_id)).size
);
const name = (id) =>
  roster.value.find((p) => String(p.id ?? p.user_id) === String(id))?.name
    ?.full || "Player";
async function load() {
  const seq = ++generation;
  loading.value = true;
  error.value = "";
  selected.value = null;
  if (!teamId.value) {
    loading.value = false;
    return;
  }
  try {
    const [day, people, plans] = await Promise.all([
      axiosGet(`coach/planner/day?team_id=${teamId.value}&date=${date.value}`),
      axiosGet(`coach/teams/${teamId.value}`),
      axiosGet(`coach/daily-plans?team_id=${teamId.value}&date=${date.value}`),
    ]);
    if (seq !== generation) return;
    rows.value = day.data.data;
    roster.value = people.data.data || [];
    drafts.value = plans.data.data.filter((p) => p.status === "draft");
  } catch (e) {
    if (seq === generation)
      error.value = e?.response?.data?.message || "Could not load the day.";
  } finally {
    if (seq === generation) loading.value = false;
  }
}
watch([teamId, date], load, { immediate: true });
async function detail(p) {
  selected.value = p;
  ledger.value = null;
  try {
    const r = await axiosGet(
      `coach/planner/day?team_id=${teamId.value}&date=${date.value}&player_id=${p.assignment.user_id}`
    );
    if (selected.value === p) ledger.value = r.data.throw_ledger;
  } catch (e) {
    error.value = "Could not load throw ledger.";
  }
}
async function review() {
  try {
    await axiosPost(`coach/daily-plans/${selected.value.id}/alert-review`, {
      player_id: selected.value.assignment.user_id,
      version: selected.value.progress.version,
      action: reviewAction.value,
    });
    reviewAction.value = "";
    await load();
  } catch (e) {
    error.value = e?.response?.data?.message || "Review could not be saved.";
  }
}
function editRemaining() {
  editPrescription.value = JSON.parse(JSON.stringify(selected.value.blocks));
  adjustmentReason.value = "";
}
function protectedItem(id) {
  return (
    !!selected.value.actual_results[id] ||
    selected.value.session_links.some((l) => l.item_id === id)
  );
}
async function saveAdjustment() {
  try {
    const r = await axiosPost(
      `coach/daily-plans/${selected.value.id}/prescription-adjustment`,
      {
        player_id: selected.value.assignment.user_id,
        version: selected.value.assignment.prescription_override?.version || 0,
        reason: adjustmentReason.value,
        buckets: editPrescription.value,
      }
    );
    selected.value = r.data.data;
    editPrescription.value = null;
  } catch (e) {
    error.value =
      e?.response?.data?.message || "Adjustment could not be saved.";
  }
}
</script>
<template>
  <section class="planner-studio">
    <header class="studio-hero">
      <div>
        <small>FMTRX · PLANNER</small>
        <h1>Daily Planner</h1>
        <p>Design workouts, build programs, and keep your team on track.</p>
      </div>
      <div class="studio-motto">BUILD<br />BETTER<br />ATHLETES</div>
    </header>
    <nav class="studio-tabs" aria-label="Planner sections">
      <button
        v-for="section in ['today', 'workouts', 'programs', 'assignments']"
        :key="section"
        :class="{ active: tab === section }"
        @click="tab = section"
      >
        {{ section }}</button
      ><button @click="emit('advanced')">More tools</button>
    </nav>
    <Library
      v-if="tab === 'workouts' || tab === 'programs'"
      :key="tab"
      embedded
      :initial-tab="tab === 'programs' ? 'programs' : 'library'"
    />
    <template v-else>
      <div class="studio-toolbar">
        <button
          @click="date = shiftCalendarDate(date, -1)"
          aria-label="Previous day"
        >
          ‹</button
        ><input type="date" v-model="date" aria-label="Training date" /><button
          @click="date = shiftCalendarDate(date, 1)"
          aria-label="Next day"
        >
          ›</button
        ><button @click="date = localDateKey()">Today</button
        ><button @click="load">Refresh</button
        ><button class="primary" @click="tab = 'workouts'">
          ＋ Quick Workout
        </button>
      </div>
      <p v-if="error" role="alert">{{ error }}</p>
      <p v-if="loading" role="status">Loading your day…</p>
      <div class="studio-stats">
        <article>
          <small>TODAY'S PLAYERS</small><strong>{{ players }}</strong>
        </article>
        <article>
          <small>PLAN COMPLETION</small
          ><strong
            >{{
              rows.length ? Math.round((complete / rows.length) * 100) : 0
            }}%</strong
          >
        </article>
        <article>
          <small>READINESS ALERTS</small><strong>{{ alerts.length }}</strong>
        </article>
        <article>
          <small>TRAINING BLOCKS</small><strong>{{ total }}</strong>
        </article>
      </div>
      <div class="studio-actions">
        <article>
          <small>CREATE A SINGLE WORKOUT</small>
          <h2>Design a Workout</h2>
          <p>Build reusable training sessions with modular blocks.</p>
          <button class="primary" @click="tab = 'workouts'">
            Create Workout →</button
          ><button @click="emit('create', date)">Quick blank plan</button>
        </article>
        <article>
          <small>BUILD A TRAINING PLAN</small>
          <h2>Build a Multi-Week Program</h2>
          <p>Prescribe each day, then publish when you're ready.</p>
          <button class="primary" @click="tab = 'programs'">
            Create Program →
          </button>
        </article>
      </div>
      <div class="studio-content">
        <section>
          <h2>
            {{
              tab === "assignments" ? "Assigned Players" : "Today's Training"
            }}
          </h2>
          <p v-if="!loading && !rows.length">
            No training scheduled. Choose Quick Workout to add a session.
          </p>
          <button
            class="studio-work"
            v-for="p in rows"
            :key="p.id + ':' + p.assignment.user_id"
            @click="detail(p)"
          >
            <div>
              <strong>{{ name(p.assignment.user_id) }}</strong
              ><small>{{ p.source.name || "Quick workout" }}</small>
            </div>
            <div>
              <strong>{{ p.name }}</strong
              ><small
                >{{ p.blocks.length }} blocks ·
                {{ p.estimated_minutes ?? "—" }} min</small
              >
            </div>
            <span :class="{ success: p.completion.completed_at }">{{
              p.completion.completed_at
                ? "Completed"
                : p.progress
                ? "In progress"
                : "Not started"
            }}</span
            ><span>›</span>
          </button>
          <h2 v-if="drafts.length">Drafts</h2>
          <button
            class="studio-work"
            v-for="p in drafts"
            :key="p.id"
            @click="emit('edit', p)"
          >
            <strong>{{ p.name || "Untitled draft" }}</strong
            ><span>Continue editing →</span>
          </button>
        </section>
        <aside>
          <h2>Needs Attention</h2>
          <p v-if="!alerts.length">
            No readiness or post-training alerts for this day.
          </p>
          <button
            v-for="p in alerts"
            :key="p.id + ':' + p.assignment.user_id"
            class="studio-work warning"
            @click="detail(p)"
          >
            <div>
              <strong>{{ name(p.assignment.user_id) }}</strong
              ><small>{{
                [...p.readiness.alerts, ...p.post_training.alerts].join(" · ")
              }}</small>
            </div>
          </button>
        </aside>
      </div>
      <section
        v-if="selected"
        class="studio-detail"
        aria-label="Player daily detail"
      >
        <button @click="selected = null">Close details</button>
        <h2>{{ name(selected.assignment.user_id) }} · {{ selected.name }}</h2>
        <p>{{ selected.source.name }}</p>
        <article v-for="block in selected.blocks" :key="block.type">
          <h3>{{ block.title }}</h3>
          <p>{{ block.note }}</p>
          <p v-for="item in block.items" :key="item.id">
            {{ item.name }} · {{ item.prescription_text }} ·
            {{ selected.actual_results[item.id]?.done ? "Completed" : "Pending"
            }}<small v-if="selected.actual_results[item.id]">{{
              selected.actual_results[item.id]
            }}</small>
          </p>
        </article>
        <button v-if="!selected.completion.completed_at" @click="editRemaining">
          Adjust remaining work for this player
        </button>
        <form v-if="editPrescription" @submit.prevent="saveAdjustment">
          <p>
            Recorded exercises stay unchanged. This adjusts only this player's
            day.
          </p>
          <section v-for="block in editPrescription" :key="block.type">
            <h3>{{ block.title }}</h3>
            <label v-for="item in block.items" :key="item.id"
              >{{ item.name
              }}<input
                v-model="item.prescription_text"
                :disabled="protectedItem(item.id)"
                placeholder="Prescription"
            /></label>
          </section>
          <label>Reason<input v-model="adjustmentReason" required /></label
          ><button class="primary">Save player adjustment</button
          ><button type="button" @click="editPrescription = null">
            Cancel
          </button>
        </form>
        <h3>Daily Throw Ledger</h3>
        <p v-if="ledger">
          {{ ledger.total_throws }} total · {{ ledger.warmup_throws }} warm-up ·
          {{ ledger.long_toss_throws }} long toss ·
          {{ ledger.weighted_ball_throws }} weighted ball ·
          {{ ledger.bullpen_pitches }} bullpen pitches
        </p>
        <p>Throw volume, not a medical workload score.</p>
        <h3>Post-training check-in</h3>
        <p>{{ selected.post_training.answers }}</p>
        <p v-if="selected.alert_review">
          Reviewed {{ selected.alert_review.reviewed_at }} ·
          {{ selected.alert_review.action }}
        </p>
        <form v-if="selected.progress" @submit.prevent="review">
          <label
            >Coach action<input
              v-model="reviewAction"
              required
              placeholder="Describe the action taken" /></label
          ><button class="primary">Mark reviewed</button>
        </form>
      </section>
    </template>
  </section>
</template>
<style src="../../../css/planner-studio.css"></style>
