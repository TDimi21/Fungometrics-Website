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
const mode = ref("practice"),
  gameName = ref("Live AB"),
  gameDate = ref(
    new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
      .toISOString()
      .slice(0, 10),
  ),
  note = ref(""),
  notesOpen = ref(false),
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
const prepareSide = value => ({
  ...value,
  hitPositions: Object.fromEntries(value.hitters.map(id=>[id,value.hitPositions?.[id]||'DH'])),
  pitchPositions: Object.fromEntries(value.pitchers.map(id=>[id,value.pitchPositions?.[id]||'P'])),
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
  <Layout
    ><main class="live-setup">
      <p class="eyebrow">LIVE AB · NEW SESSION</p>
      <h1>Hitters meet pitchers.</h1>
      <p>Select your lineups, then score every pitch together.</p>
      <div class="mode">
        <button
          :class="{ active: mode === 'practice' }"
          @click="mode = 'practice'"
        >
          PRACTICE</button
        ><button :class="{ active: mode === 'game' }" @click="mode = 'game'">
          GAME MODE
        </button>
      </div>
      <label>Session name<input v-model="gameName" maxlength="100" /></label
      ><label>Game date<input v-model="gameDate" type="date" /></label>
      <div class="teams">
        <section v-for="side in ['away', 'home']" :key="side">
          <h2>
            {{
              mode === "game"
                ? side === "away"
                  ? "Away"
                  : "Home"
                : side === "away"
                  ? "Hitting team"
                  : "Pitching team"
            }}
          </h2>
          <label
            >Team<select
              v-model="(side === 'away' ? away : home).team_id"
              :disabled="busy"
            >
              <option value="">Select team</option>
              <option v-for="team in teams" :key="team.id" :value="team.id">
                {{ team.name }}
              </option>
            </select></label
          >
          <p v-if="loading[side]">Loading players…</p>
          <LiveABLineup
            v-if="side === 'away' || mode === 'game'"
            v-model="(side === 'away' ? away : home).hitters"
            :players="rosters[side]"
            label="Hitters"
            :positions="(side === 'away' ? away : home).hitPositions || {}"
            @update:positions="
              (side === 'away' ? away : home).hitPositions = $event
            "
            :disabled="busy || loading[side]"
          /><LiveABLineup
            v-if="side === 'home' || mode === 'game'"
            v-model="(side === 'away' ? away : home).pitchers"
            :players="rosters[side]"
            label="Pitchers"
            :positions="(side === 'away' ? away : home).pitchPositions || {}"
            @update:positions="
              (side === 'away' ? away : home).pitchPositions = $event
            "
            :disabled="busy || loading[side]"
          />
        </section>
      </div>
      <div v-if="mode === 'game'" class="rules">
        <label
          >Innings<input
            v-model.number="innings"
            type="number"
            min="1"
            max="30" /></label
        ><label
          ><input v-model="unlimited" type="checkbox" /> Unlimited
          innings</label
        ><label
          ><input v-model="continuous" type="checkbox" /> Continuous inning
          (coach advances sides)</label
        >
      </div>
      <button
        class="notes"
        :aria-expanded="notesOpen"
        @click="notesOpen = !notesOpen"
      >
        NOTES ＋</button
      ><textarea
        v-if="notesOpen"
        v-model="note"
        aria-label="Session notes"
        maxlength="2000"
      />
      <p v-if="error" role="alert">{{ error }}</p>
      <footer>
        <button :disabled="busy" @click="router.push('/training/live-ab')">
          CANCEL</button
        ><button class="primary" :disabled="!valid" @click="create">
          {{ busy ? "CREATING…" : "CREATE" }}
        </button>
      </footer>
    </main></Layout
  >
</template>
<style scoped>
.live-setup {
  max-width: 1100px;
  margin: 30px auto;
  padding: 30px;
  border: 1px solid #596078;
  border-radius: 24px;
  background: #0b1020;
  color: white;
}
.eyebrow {
  font-size: 12px;
  letter-spacing: 2px;
  color: #ff2850;
}
h1 {
  font-size: 32px;
  font-weight: 800;
  margin: 12px 0;
}
h2 {
  font-size: 22px;
  margin: 16px 0;
}
.mode,
footer {
  display: flex;
  gap: 12px;
  margin: 24px 0;
}
.mode button,
footer button {
  flex: 1;
  padding: 18px;
  border: 1px solid #ff2750;
  border-radius: 15px;
  background: #252939;
  font-weight: 700;
}
.mode .active,
.primary {
  background: #ff2750 !important;
}
.teams {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
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
  border: 1px solid #69748e;
  background: #141c30;
  color: white;
  padding: 12px;
  border-radius: 10px;
  margin-top: 8px;
}
textarea {
  min-height: 100px;
}
.rules {
  display: flex;
  gap: 20px;
  align-items: center;
  flex-wrap: wrap;
}
.notes {
  padding: 20px;
  background: #252939;
  border-radius: 16px;
  width: 100%;
  text-align: left;
  font-size: 22px;
}
button:disabled {
  opacity: 0.4;
}
button:focus-visible {
  outline: 3px solid #8de6ff;
}
[role="alert"] {
  background: #641832;
  padding: 15px;
  border-radius: 10px;
}
@media (max-width: 700px) {
  .teams {
    grid-template-columns: 1fr;
  }
  .live-setup {
    margin: 12px;
    padding: 18px;
  }
}
</style>
