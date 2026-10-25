<script setup>
import { computed, ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import Layout from "@/layout/Layout.vue";
import { useUserStore } from "@/store/user";
import { useTrainingStore } from "@/store/training";
import { useAxiosAuth } from "@/composables/axios-auth";
import { cageDimensions } from "@/utils/cageScoring";

const route = useRoute(),
  router = useRouter(),
  user = useUserStore(),
  training = useTrainingStore();
const { axiosPost, axiosGet } = useAxiosAuth();
const sharingTeam = ref(""),
  connectedTeams = ref([]),
  teamsLoading = ref(false),
  teamsError = ref("");
async function loadTeams() {
  teamsLoading.value = true;
  teamsError.value = "";
  try {
    connectedTeams.value = (await axiosGet("player/me/teams")).data.data;
  } catch {
    teamsError.value =
      "Could not load connected teams. Retry, or keep this session personal.";
  } finally {
    teamsLoading.value = false;
  }
}
onMounted(loadTeams);
const modes = {
  bullpen: { label: "Bullpen", type: "P", path: "/track/bullpen" },
  batting: { label: "Batting", type: "B", path: "/track/batting" },
  cage: { label: "Cage", type: "C", path: "/track/cage" },
  LT: { label: "Long Toss", type: "T", path: "/track/training-mode/LT" },
  EV: { label: "Exit Velocity", type: "T", path: "/track/training-mode/EV" },
  WB: { label: "Weighted Balls", type: "T", path: "/track/training-mode/WB" },
};
const selected = computed(() => modes[route.params.mode]);
const note = ref(""),
  busy = ref(false),
  uncertain = ref(false),
  error = ref("");
const cage = ref({
  height: { ft: 14, inch: 0 },
  width: { ft: 14, inch: 0 },
  length: { ft: 65, inch: 0 },
});
const destination = computed(
  () =>
    `/training-player/${selected.value?.type === "T" ? `training-mode?mode=${route.params.mode}` : route.params.mode}`,
);
const valid = computed(
  () =>
    user.userData.type === "player" &&
    !!user.userData.id &&
    !!selected.value &&
    !busy.value &&
    !uncertain.value &&
    (!sharingTeam.value ||
      connectedTeams.value.some((team) => team.id === sharingTeam.value)) &&
    (selected.value.type !== "C" || !!cageDimensions(cage.value)),
);
async function create() {
  if (!valid.value) return;
  busy.value = true;
  error.value = "";
  try {
    const mode = selected.value;
    const payload = {
      type: mode.type,
      note: note.value.trim() || `${mode.label} practice`,
      players: [{ id: user.userData.id, sort: 0 }],
    };
    if (sharingTeam.value) payload.share_with_team_id = sharingTeam.value;
    if (mode.type === "T") payload.modes = route.params.mode;
    if (mode.type === "C") payload.cage = cage.value;
    const data = (await axiosPost("training", payload)).data.data;
    if (!data?.id) throw Error("Missing session");
    training.setDataTraining(data);
    await router.push({ path: mode.path, query: { session: data.id } });
  } catch (e) {
    uncertain.value = ![401, 403, 422, 429].includes(e.response?.status);
    error.value = uncertain.value
      ? "Creation could not be confirmed. Check My Sessions before trying again."
      : e.response?.status === 403
        ? "Your current plan does not include this session type."
        : "Unable to start this session. Check your settings and try again.";
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <Layout
    ><main class="player-setup">
      <template v-if="selected">
        <p class="eyebrow">MY PRACTICE</p>
        <h1>New {{ selected.label }} Session</h1>
        <section class="player-card">
          <strong>{{ user.userData.name?.full || "My session" }}</strong>
          <p>
            Every result is recorded to your player profile. Only you are
            scored.
          </p>
          <label
            >Share this session with
            <select v-model="sharingTeam" :disabled="busy || teamsLoading">
              <option value="">Just myself — personal session</option>
              <option
                v-for="team in connectedTeams"
                :key="team.id"
                :value="team.id"
              >
                {{ team.name }}
              </option>
            </select>
          </label>
          <p>
            {{
              sharingTeam
                ? "Coaches of the selected team can view this session and its statistics. You remain the scorer."
                : "This session is not shared with a team."
            }}
          </p>
          <p v-if="teamsLoading" role="status">Loading your connected teams…</p>
          <p v-if="teamsError" role="alert">
            {{ teamsError }}
            <button type="button" @click="loadTeams">Retry</button>
          </p>
        </section>
        <fieldset v-if="selected.type === 'C'" :disabled="busy">
          <legend>Cage dimensions</legend>
          <div
            v-for="dimension in ['height', 'width', 'length']"
            :key="dimension"
            class="dimension"
          >
            <strong>{{ dimension }}</strong>
            <label
              >Feet<input
                v-model.number="cage[dimension].ft"
                type="number"
                min="1"
                step="1"
            /></label>
            <label
              >Inches<input
                v-model.number="cage[dimension].inch"
                type="number"
                min="0"
                max="11"
                step="1"
            /></label>
          </div>
        </fieldset>
        <label
          >Notes (optional)<textarea
            v-model="note"
            :disabled="busy"
            maxlength="2000"
          />
        </label>
        <p v-if="error" role="alert">{{ error }}</p>
        <footer>
          <button :disabled="busy" @click="router.push(destination)">
            {{ uncertain ? "My Sessions" : "Cancel" }}</button
          ><button class="primary" :disabled="!valid" @click="create">
            {{ busy ? "Starting…" : "Start Session" }}
          </button>
        </footer>
      </template>
      <template v-else
        ><h1>Session type unavailable</h1>
        <router-link to="/training-player/batting"
          >Return to My Sessions</router-link
        ></template
      >
    </main></Layout
  >
</template>
<style scoped>
.player-setup {
  max-width: 760px;
  margin: 30px auto;
  padding: 28px;
  border: 1px solid #53617b;
  border-radius: 22px;
  color: #fff;
  background: #0c1428;
}
.eyebrow {
  font-size: 12px;
  letter-spacing: 2px;
  color: #d4a2b5;
}
h1 {
  font-size: 28px;
  font-weight: 800;
  margin: 12px 0 24px;
}
.player-card {
  padding: 20px;
  border-radius: 18px;
  background: #252939;
  margin-bottom: 24px;
}
.player-card p {
  margin-top: 8px;
  color: #bdc8dc;
}
label {
  display: block;
  margin: 14px 0;
}
textarea,
select,
input {
  display: block;
  background: #1d283e;
  color: white;
  border: 1px solid #6b7993;
  border-radius: 10px;
  padding: 12px;
  width: 100%;
  margin-top: 8px;
}
textarea {
  min-height: 100px;
}
.dimension {
  display: flex;
  gap: 18px;
  align-items: center;
}
.dimension strong {
  text-transform: capitalize;
  min-width: 60px;
}
.dimension label {
  flex: 1;
  min-width: 0;
}
footer {
  display: flex;
  gap: 18px;
  margin-top: 24px;
}
button {
  flex: 1;
  min-height: 48px;
  padding: 12px;
  background: #343747;
  border-radius: 12px;
}
.primary {
  background: #e91f48;
}
button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
[role="alert"] {
  padding: 12px;
  background: #641832;
  border-radius: 10px;
}
button:focus-visible {
  outline: 3px solid #8de6ff;
}
@media (max-width: 650px) {
  .player-setup {
    margin: 12px;
    padding: 18px;
  }
}
</style>
