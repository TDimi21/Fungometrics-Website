<script setup>
import { computed, ref, watch } from "vue";
import { useRouter } from "vue-router";
import Layout from "@/layout/Layout.vue";
import LiveABLineup from "@/components/practice/LiveABLineup.vue";
import { useTeamStore } from "@/store/team";
import { useTrainingStore } from "@/store/training";
import { useAxiosAuth } from "@/composables/axios-auth";
const router = useRouter(),
  teamsStore = useTeamStore(),
  training = useTrainingStore();
const { axiosGet, axiosPost } = useAxiosAuth();
const teams = computed(() => [
  ...new Map(
    [teamsStore.team, ...teamsStore.teams]
      .filter((t) => t?.id)
      .map((t) => [t.id, t]),
  ).values(),
]);
const mode = ref("game"),
  gameName = ref("Live AB Game"),
  gameDate = ref(
    new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
      .toISOString()
      .slice(0, 10),
  ),
  note = ref(""),
  innings = ref(9),
  unlimited = ref(false),
  continuous = ref(false);
const away = ref({
    team_id: teamsStore.team?.id || "",
    hitters: [],
    pitchers: [],
  }),
  home = ref({ team_id: teamsStore.team?.id || "", hitters: [], pitchers: [] });
const rosters = ref({ away: [], home: [] }),
  loading = ref({ away: false, home: false }),
  error = ref(""),
  busy = ref(false),
  uncertain = ref(false);
const sequence = { away: 0, home: 0 };
async function roster(side, teamId) {
  const n = ++sequence[side];
  const team = side === "away" ? away : home;
  team.value.hitters = [];
  team.value.pitchers = [];
  rosters.value[side] = [];
  loading.value[side] = true;
  error.value = "";
  try {
    if (teamId) {
      const data = (await axiosGet(`coach/teams/${teamId}`)).data.data;
      if (!Array.isArray(data)) throw Error();
      if (n === sequence[side]) rosters.value[side] = data;
    }
  } catch {
    if (n === sequence[side])
      error.value = "Unable to load roster. Reselect the team to retry.";
  } finally {
    if (n === sequence[side]) loading.value[side] = false;
  }
}
watch(
  () => away.value.team_id,
  (id) => roster("away", id),
  { immediate: true },
);
watch(
  () => home.value.team_id,
  (id) => roster("home", id),
  { immediate: true },
);
const valid = computed(
  () =>
    !busy.value &&
    !uncertain.value &&
    !loading.value.away &&
    !loading.value.home &&
    away.value.team_id &&
    home.value.team_id &&
    away.value.hitters.length &&
    home.value.pitchers.length &&
    (mode.value !== "game" ||
      (away.value.pitchers.length && home.value.hitters.length)) &&
    gameName.value.trim() &&
    Number.isInteger(Number(innings.value)) &&
    innings.value >= 1 &&
    innings.value <= 30,
);
const prepareSide = (value) => ({
  ...value,
  hitPositions: Object.fromEntries(
    value.hitters.map((id) => [id, value.hitPositions?.[id] || "DH"]),
  ),
  pitchPositions: Object.fromEntries(
    value.pitchers.map((id) => [id, value.pitchPositions?.[id] || "P"]),
  ),
});
async function create() {
  if (!valid.value) return;
  busy.value = true;
  error.value = "";
  try {
    const config = {
      mode: mode.value,
      name: gameName.value.trim(),
      date: gameDate.value,
      innings: Number(innings.value),
      unlimited: unlimited.value,
      continuous: continuous.value,
      away: prepareSide(away.value),
      home: prepareSide(home.value),
    };
    const data = (
      await axiosPost("coach/trainingab", {
        type: "L",
        note: note.value.trim() || gameName.value,
        teams: { a: away.value.team_id, b: home.value.team_id },
        players: {
          a: away.value.hitters.map((id, sort) => ({ id, sort })),
          b: home.value.pitchers.map((id, sort) => ({ id, sort })),
        },
        liveab_config: config,
      })
    ).data.data;
    training.setDataTraining(data);
    await router.push({ path: "/track/live", query: { session: data.id } });
  } catch (e) {
    uncertain.value = ![403, 422].includes(e.response?.status);
    error.value = uncertain.value
      ? "Creation could not be confirmed. Check Practice Sessions before creating another game."
      : "Check your team access and select hitters and pitchers for each side.";
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <Layout>
    <main class="live-setup">
      <header class="game-card">
        <h1>Create Live AB Game</h1>
        <label
          ><span class="sr-only">Game name</span
          ><input
            v-model="gameName"
            :disabled="busy"
            maxlength="100"
            placeholder="Live AB Game"
        /></label>
        <label
          ><span class="sr-only">Game date</span
          ><input v-model="gameDate" :disabled="busy" type="date"
        /></label>
      </header>
      <section
        v-for="side in ['home', 'away']"
        :key="side"
        class="team-section"
      >
        <h2>
          {{
            side === "home"
              ? "Home Team (Pitchers / Fielding)"
              : "Away Team (Batting Lineup)"
          }}
        </h2>
        <label class="team-choice"
          >{{ side === "home" ? "Home roster" : "Away roster" }}
          <select
            v-model="(side === 'home' ? home : away).team_id"
            :disabled="busy"
          >
            <option value="">Select team</option>
            <option v-for="team in teams" :key="team.id" :value="team.id">
              {{ team.name }}
            </option>
          </select>
        </label>
        <p v-if="loading[side]" role="status">Loading players…</p>
        <template
          v-for="role in side === 'home'
            ? ['pitchers', 'hitters']
            : ['hitters', 'pitchers']"
          :key="role"
        >
          <LiveABLineup
            v-if="
              mode === 'game' ||
              (side === 'home' ? role === 'pitchers' : role === 'hitters')
            "
            v-model="(side === 'home' ? home : away)[role]"
            :label="`${side} ${role === 'hitters' ? 'lineup' : 'pitchers'}`"
            :default-position="role === 'pitchers' ? 'P' : 'DH'"
            :players="rosters[side]"
            :positions="
              (side === 'home' ? home : away)[
                role === 'pitchers' ? 'pitchPositions' : 'hitPositions'
              ] || {}
            "
            @update:positions="
              (side === 'home' ? home : away)[
                role === 'pitchers' ? 'pitchPositions' : 'hitPositions'
              ] = $event
            "
            :disabled="
              busy || loading[side] || !(side === 'home' ? home : away).team_id
            "
          />
        </template>
      </section>
      <p class="requirements">
        {{
          mode === "game"
            ? "Each team needs: at least 1 lineup hitter + 1 pitcher (max 20 each list)."
            : "Select at least 1 away hitter and 1 home pitcher (max 20 each list)."
        }}
      </p>
      <details class="options">
        <summary>Session options and notes</summary>
        <fieldset :disabled="busy">
          <label
            >Session type<select v-model="mode">
              <option value="game">Game</option>
              <option value="practice">Practice (one hitting side)</option>
            </select></label
          >
          <div v-if="mode === 'game'" class="rules">
            <label
              >Innings<input
                v-model.number="innings"
                type="number"
                min="1"
                max="30"
            /></label>
            <label
              ><input v-model="unlimited" type="checkbox" /> Unlimited
              innings</label
            >
            <label
              ><input v-model="continuous" type="checkbox" /> Continuous inning
              (coach advances sides)</label
            >
          </div>
          <label>Notes<textarea v-model="note" maxlength="2000" /></label>
        </fieldset>
      </details>
      <p v-if="error" role="alert">{{ error }}</p>
      <footer>
        <button :disabled="busy" @click="router.push('/training/live-ab')">
          CANCEL
        </button>
        <button class="primary" :disabled="!valid" @click="create">
          {{ busy ? "CREATING…" : "CREATE" }}
        </button>
      </footer>
    </main>
  </Layout>
</template>
<style scoped>
.live-setup {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  max-width: 900px;
  margin: 30px auto;
  padding: 28px;
  border-radius: 24px;
  background: #0c1020;
  color: #fff;
}
.live-setup::before {
  content: "";
  position: absolute;
  z-index: -1;
  inset: 45% -15% 10%;
  background: #461027;
  opacity: 0.45;
  clip-path: polygon(
    0 0,
    100% 40%,
    90% 100%,
    0 65%,
    0 45%,
    78% 78%,
    83% 55%,
    0 20%
  );
  pointer-events: none;
}
.game-card {
  padding: 22px;
  border: 1px solid #43475b;
  border-radius: 22px;
  background: #0a1026;
}
h1 {
  font-size: 28px;
  font-weight: 800;
  margin-bottom: 16px;
}
h2 {
  font-size: 22px;
  font-weight: 700;
  margin: 24px 0 14px;
}
label {
  display: block;
  margin: 12px 0;
}
input:not([type="checkbox"]),
select,
textarea {
  display: block;
  width: 100%;
  margin-top: 8px;
  padding: 14px 18px;
  border: 1px solid #50566b;
  border-radius: 14px;
  background: #202438;
  color: #fff;
  color-scheme: dark;
}
.game-card input {
  font-size: 21px;
}
.team-choice {
  color: #bec4d3;
  font-size: 14px;
}
.team-choice select {
  padding: 10px 14px;
}
.requirements {
  color: #b7b5c0;
  font-size: 16px;
  margin: 10px 0;
}
.options {
  margin-top: 18px;
  color: #c5c8d6;
}
summary {
  cursor: pointer;
  padding: 8px 0;
}
.rules {
  display: flex;
  flex-wrap: wrap;
  gap: 18px;
  align-items: center;
}
textarea {
  min-height: 90px;
}
footer {
  display: flex;
  justify-content: space-between;
  gap: 24px;
  margin-top: 24px;
}
footer button {
  width: 44%;
  padding: 16px;
  border-radius: 20px;
  background: #343747;
  color: white;
  font-size: 24px;
}
footer .primary {
  background: #ff2750;
}
footer .primary:disabled {
  background: #e4e7e8;
  color: #959fa7;
  cursor: not-allowed;
}
button:focus-visible,
summary:focus-visible {
  outline: 3px solid #8de6ff;
  outline-offset: 3px;
}
[role="alert"] {
  padding: 15px;
  background: #641832;
  border-radius: 10px;
}
@media (max-width: 700px) {
  .live-setup {
    margin: 12px;
    padding: 16px;
  }
  .game-card {
    padding: 16px;
  }
  h1 {
    font-size: 24px;
  }
  h2 {
    font-size: 19px;
  }
  footer {
    gap: 12px;
  }
  footer button {
    width: 50%;
    font-size: 20px;
  }
}
</style>
