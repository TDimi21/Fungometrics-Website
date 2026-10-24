<script setup>
import LiveABBoxScore from "@/components/practice/LiveABBoxScore.vue";
import { computed, ref, onMounted } from "vue";
import { useRoute, useRouter, onBeforeRouteLeave } from "vue-router";
import Layout from "@/layout/Layout.vue";
import { useTrainingStore } from "@/store/training";
import { useAxiosAuth } from "@/composables/axios-auth";
import BattingScoringField from "@/components/practice/BattingScoringField.vue";
import LivePracticeStats from "@/components/statistics/LivePracticeStats.vue";
import { getPitchInfoFromCoordinate } from "@/utils/bullpenCoordinates";
import { getBattingFieldInfoFromCoordinate } from "@/utils/battingCoordinates";
import {
  clone,
  resultLabels,
  totals,
  battingSide,
  pitchingSide,
  countAfter,
  defaultMovements,
  scorePitch,
  adjustRunners,
  advanceHalf,
} from "@/utils/liveabWebScoring";
const route = useRoute(),
  router = useRouter(),
  training = useTrainingStore();
const { axiosGet, axiosPost, axiosPut } = useAxiosAuth();
const id = route.query.session || training.trainingActive?.id,
  base = `coach/liveab-session/${id}`;
const session = ref(null),
  state = ref(null),
  loading = ref(true),
  busy = ref(false),
  error = ref(""),
  notice = ref(""),
  pending = ref(null);
const pitch = ref("FB"),
  trajectory = ref("TK"),
  location = ref(0),
  velocity = ref(""),
  exitVelocity = ref(""),
  field = ref(0),
  quality = ref("A"),
  outcome = ref("");
const review = ref(false),
  movements = ref([]),
  thirdOut = ref("none"),
  rbi = ref(null),
  statsOpen = ref(false),
  statsMode = ref("B"),
  controls = ref(false),
  runnerMode = ref(false),
  runnerReason = ref("stolen_base"),
  finishing = ref(false),
  endNote = ref("");
const adding = ref(false),
  addSide = ref("away"),
  addRole = ref("hitters"),
  addPlayer = ref(""),
  addRoster = ref([]);
const pitchTypes = ["FB", "CH", "SL", "CV", "OTHER"],
  contacts = {
    TK: "Take",
    GB: "GB",
    LD: "LD",
    FB: "FLY",
    PF: "POP",
    F: "FOUL",
    SM: "S/M",
    HBP: "HBP",
  };
const config = computed(() => {
  if (session.value?.config) return session.value.config;
  const teams = session.value?.teams || [];
  const away = teams.find((t) => t.batting) || teams[0],
    home = teams.find((t) => !t.batting) || teams[1] || away;
  return {
    mode: "practice",
    name: "Live AB practice",
    innings: 9,
    unlimited: true,
    continuous: false,
    away: {
      team_id: away?.team_id,
      hitters: (session.value?.players.batters || []).map((p) => p.id),
      pitchers: [],
    },
    home: {
      team_id: home?.team_id,
      hitters: [],
      pitchers: (session.value?.players.pitchers || []).map((p) => p.id),
    },
  };
});
const rows = computed(() => session.value?.rows || []),
  allPlayers = computed(() => [
    ...new Map(
      [
        ...(session.value?.players.batters || []),
        ...(session.value?.players.pitchers || []),
      ].map((p) => [p.id, p]),
    ).values(),
  ]);
const name = (id) => {
  const p = allPlayers.value.find((p) => p.id === id);
  return p?.name?.full || "Player";
};
const side = computed(() => (state.value ? battingSide(state.value) : "away")),
  fielding = computed(() => (state.value ? pitchingSide(state.value) : "home"));
const hitterIds = computed(() => config.value[side.value].hitters),
  pitcherIds = computed(() => config.value[fielding.value].pitchers);
const hitter = computed(
    () => hitterIds.value[state.value?.hitterIndex[side.value] || 0],
  ),
  pitcher = computed(
    () => pitcherIds.value[state.value?.pitcherIndex[fielding.value] || 0],
  );
const score = computed(() =>
  state.value ? totals(state.value) : { away: 0, home: 0 },
);
const teamName = (side) =>
  session.value?.teams.find((t) => t.team_id === config.value[side].team_id)
    ?.team?.name || side;
const info = computed(() => getPitchInfoFromCoordinate(location.value));
const isInPlay = computed(() =>
  ["GB", "LD", "FB", "PF"].includes(trajectory.value),
);
const writeable = computed(
  () =>
    session.value &&
    !session.value.is_completed &&
    !state.value?.finished &&
    !busy.value &&
    !pending.value &&
    hitter.value &&
    pitcher.value &&
    state.value.pitches === rows.value.length,
);
const validSpeed = (v) =>
  v === "" || (/^\d+$/.test(String(v)) && Number(v) >= 0 && Number(v) <= 130);
const canSave = computed(
  () =>
    writeable.value &&
    pitchTypes.includes(pitch.value) &&
    (location.value > 0 ||
      trajectory.value === "HBP" ||
      (review.value && ["IBB", "CI"].includes(outcome.value))) &&
    validSpeed(velocity.value) &&
    validSpeed(exitVelocity.value),
);
const allowedResults = computed(() =>
  isInPlay.value
    ? resultLabels
    : Object.fromEntries(
        Object.entries({
          ...resultLabels,
          WALK: "Walk",
          STRIKEOUT_LOOKING: "Strikeout looking",
          STRIKEOUT_SWINGING: "Strikeout swinging",
        }).filter(
          ([code]) =>
            code === outcome.value ||
            ([
              "STRIKEOUT_LOOKING",
              "STRIKEOUT_SWINGING",
              "DROPPED_THIRD_STRIKE_SAFE",
              "DROPPED_THIRD_STRIKE_OUT",
            ].includes(outcome.value) &&
              [
                "STRIKEOUT_LOOKING",
                "STRIKEOUT_SWINGING",
                "DROPPED_THIRD_STRIKE_SAFE",
                "DROPPED_THIRD_STRIKE_OUT",
              ].includes(code)),
        ),
      ),
);
const pointStyle = computed(() => ({
  left: `${((((location.value - 1) % 60) + 0.5) / 60) * 100}%`,
  top: `${((Math.floor((location.value - 1) / 60) + 0.5) / 60) * 100}%`,
}));
function accept(data) {
  session.value = data;
  state.value = clone(data.state);
}
async function load() {
  loading.value = true;
  error.value = "";
  try {
    if (!id) throw Error("Choose a Live AB session from Practice Sessions.");
    accept((await axiosGet(base)).data.data);
  } catch (e) {
    error.value = e.response
      ? "Unable to load the session. Check your access and retry."
      : e.message;
  } finally {
    loading.value = false;
  }
}
function clear() {
  location.value = 0;
  velocity.value = "";
  exitVelocity.value = "";
  field.value = 0;
  quality.value = "A";
  trajectory.value = "TK";
  outcome.value = "";
  review.value = false;
  runnerMode.value = false;
  thirdOut.value = "none";
  rbi.value = null;
}
function chooseLocation(e) {
  if (!writeable.value) return;
  const rect = e.currentTarget.getBoundingClientRect();
  const col = Math.max(
      0,
      Math.min(59, Math.floor(((e.clientX - rect.left) / rect.width) * 60)),
    ),
    row = Math.max(
      0,
      Math.min(59, Math.floor(((e.clientY - rect.top) / rect.height) * 60)),
    );
  location.value = row * 60 + col + 1;
}
function arrow(e) {
  const delta = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -60, ArrowDown: 60 }[
    e.key
  ];
  if (delta && writeable.value) {
    e.preventDefault();
    location.value = Math.min(
      3600,
      Math.max(1, (location.value || 1830) + delta),
    );
  }
}
function key(k) {
  if (!writeable.value) return;
  const target = review.value ? exitVelocity : velocity;
  target.value =
    k === "Clear"
      ? ""
      : k === "⌫"
        ? String(target.value).slice(0, -1)
        : `${target.value}${k}`.replace(/^0+(?=\d)/, "").slice(0, 3);
}
function suggest(result) {
  outcome.value = result;
  movements.value = defaultMovements(state.value, hitter.value, result);
  thirdOut.value = [
    "GROUND_OUT",
    "DOUBLE_PLAY",
    "TRIPLE_PLAY",
    "INFIELD_FLY",
  ].includes(result)
    ? "force"
    : "none";
  rbi.value = null;
}
function special(result) {
  clear();
  review.value = true;
  suggest(result);
}
function prepare() {
  if (!canSave.value) return;
  runnerMode.value = false;
  error.value = "";
  const auto = countAfter(
    state.value,
    info.value.zone,
    trajectory.value,
  ).result;
  if (isInPlay.value) {
    review.value = true;
    suggest("SINGLE");
  } else if (auto) {
    review.value = true;
    suggest(auto);
  } else save();
}
async function requestAction(kind, payload) {
  if (busy.value) return;
  busy.value = true;
  error.value = "";
  try {
    const data = (await axiosPost(`${base}/${kind}`, payload)).data.data;
    accept(data);
    pending.value = null;
    clear();
    notice.value = kind === "pitch" ? "Pitch saved." : "Session updated.";
  } catch (e) {
    if ([403, 404, 422].includes(e.response?.status)) {
      pending.value = null;
      error.value =
        e.response.data?.message ||
        "Check the selected players and scoring values.";
    } else if (e.response?.status === 409) {
      pending.value = null;
      error.value = "Session changed in another window. Reload before scoring.";
      await load();
    } else {
      pending.value = { kind, payload };
      error.value =
        "Could not confirm the save. Retry this same action to safely check it; do not enter it again.";
    }
  } finally {
    busy.value = false;
  }
}
async function save() {
  if (!canSave.value) return;
  try {
    const result = scorePitch(state.value, config.value, {
      zone: info.value.zone,
      trajectory: trajectory.value,
      result: review.value ? outcome.value : "",
      movements: movements.value,
      thirdOut: thirdOut.value,
      rbi: rbi.value === "" ? null : rbi.value,
    });
    const payload = {
      practice_id: id,
      client_id: crypto.randomUUID(),
      revision: state.value.revision,
      state: result.state,
      ...result.play,
      turn: {
        turn: state.value.turn,
        pitches:
          rows.value.filter((r) => Number(r.turn) === state.value.turn).length +
          1,
        strike: state.value.strikes,
        ball: state.value.balls,
        is_over: !!result.play.play_result,
      },
      pitch_mark: location.value || null,
      pitch_location: info.value.pitch_side || "",
      zone: info.value.zone,
      type_of_hit: trajectory.value,
      is_contact: isInPlay.value,
      batting: {
        team_id: config.value[side.value].team_id,
        batter_id: hitter.value,
        quality_of_contact: isInPlay.value ? quality.value : "N",
        field_mark: isInPlay.value ? field.value || null : null,
        field_direction: isInPlay.value
          ? getBattingFieldInfoFromCoordinate(field.value).direction || ""
          : null,
        velocity: isInPlay.value ? Number(exitVelocity.value || 0) : 0,
      },
      pitching: {
        team_id: config.value[fielding.value].team_id,
        pitcher_id: pitcher.value,
        type_throw: pitch.value,
        miles_per_hour: Number(velocity.value || 0),
      },
    };
    delete payload.state.revision;
    await requestAction("pitch", payload);
  } catch (e) {
    error.value = e.message;
  }
}
function changeState(next, action = "game_control") {
  delete next.revision;
  return requestAction("state", {
    client_id: crypto.randomUUID(),
    revision: state.value.revision,
    state: next,
    action,
  });
}
function selectPlayer(role, index) {
  const next = clone(state.value);
  next[role === "hitter" ? "hitterIndex" : "pitcherIndex"][
    role === "hitter" ? side.value : fielding.value
  ] = Number(index);
  changeState(next);
}
async function undo() {
  if (busy.value || pending.value || !session.value.can_undo) return;
  await requestAction("undo", { revision: state.value.revision });
}
function openRunners() {
  review.value = false;
  movements.value = state.value.runners
    .map((id, i) => (id ? { id, from: i + 1, to: i + 1 } : null))
    .filter(Boolean);
  runnerMode.value = true;
}
async function saveRunners() {
  try {
    await changeState(
      adjustRunners(
        state.value,
        config.value,
        movements.value,
        runnerReason.value,
      ),
      runnerReason.value,
    );
  } catch (e) {
    error.value = e.message;
  }
}
async function loadAddRoster() {
  addRoster.value = [];
  addPlayer.value = "";
  try {
    addRoster.value = (
      await axiosGet(`coach/teams/${config.value[addSide.value].team_id}`)
    ).data.data;
  } catch {
    error.value = "Unable to load roster.";
  }
}
async function openAdd() {
  adding.value = true;
  addSide.value = side.value;
  await loadAddRoster();
}
async function addToLineup() {
  if (!addPlayer.value || !writeable.value) return;
  await requestAction("lineup", {
    revision: state.value.revision,
    side: addSide.value,
    role: addRole.value,
    player_id: addPlayer.value,
  });
  if (!pending.value && !error.value) adding.value = false;
}
async function finish() {
  if (busy.value || pending.value) return;
  busy.value = true;
  try {
    await axiosPut(`training/${id}`, {
      is_completed: true,
      end_note: endNote.value,
    });
    await load();
    finishing.value = false;
  } catch {
    error.value =
      "Could not confirm the session ended. Reload before retrying.";
  } finally {
    busy.value = false;
  }
}
onBeforeRouteLeave(() => !busy.value && !pending.value);
onMounted(load);
</script>
<template>
  <Layout
    ><main class="liveab">
      <p v-if="loading">Loading Live AB…</p>
      <p v-if="error" class="error" role="alert">{{ error }}</p>
      <button v-if="!session && !loading" @click="load">Retry</button
      ><template v-if="session && state">
        <header>
          <div>
            <small
              >LIVE AB ·
              {{ config.mode === "game" ? "GAME MODE" : "PRACTICE" }}</small
            >
            <h1>{{ config.name }}</h1><small v-if="config.date">{{ config.date }}</small>
          </div>
          <nav>
            <button
              :disabled="busy || !!pending"
              @click="statsOpen = !statsOpen"
            >
              {{ statsOpen ? "Hide" : "Show" }} statistics →</button
            ><button
              :disabled="busy || !!pending"
              @click="router.push(`/session/report/${id}/live_ab`)"
            >
              Session report</button
            ><button
              class="primary"
              :disabled="busy || !!pending || session.is_completed"
              @click="finishing = true"
            >
              End practice →
            </button>
          </nav>
        </header>
        <div class="scoreboard">
          <strong
            >{{ teamName("away") }} <b>{{ score.away }}</b></strong
          >
          <div>
            <span>{{
              state.finished
                ? "FINAL"
                : `${state.half === "top" ? "▲" : "▼"} ${state.inning}`
            }}</span
            ><small
              >{{ state.balls }} BALL · {{ state.strikes }} STRIKE ·
              {{ state.outs }} OUT</small
            >
          </div>
          <strong
            >{{ teamName("home") }} <b>{{ score.home }}</b></strong
          >
        </div>
        <div class="inning-table" v-if="config.mode === 'game'">
          <table>
            <thead>
              <tr>
                <th>Team</th>
                <th
                  v-for="n in Math.max(config.innings, state.inning)"
                  :key="n"
                >
                  {{ n }}
                </th>
                <th>R</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="sideName in ['away', 'home']" :key="sideName">
                <th>{{ teamName(sideName) }}</th>
                <td
                  v-for="n in Math.max(config.innings, state.inning)"
                  :key="n"
                >
                  {{
                    state.scores.find(
                      (s) =>
                        s.inning === n &&
                        s.half === (sideName === "away" ? "top" : "bottom"),
                    )?.runs ?? "—"
                  }}
                </td>
                <td>{{ score[sideName] }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="session.is_completed" class="notice">
          Session complete. Statistics and Session Report remain available.
        </p>
        <p v-else-if="state.finished" class="notice">
          Game over. Review the final score, or Undo to correct the last play,
          then End practice.
        </p>
        <p v-if="state.pitches !== rows.length" class="error">
          This session has pitches saved outside this web scorer. Continue on
          the original device to avoid replacing its game state.
        </p>
        <p v-if="notice" role="status" class="notice">{{ notice }}</p>
        <div v-if="pending" class="error">
          <button
            :disabled="busy"
            @click="requestAction(pending.kind, pending.payload)"
          >
            Retry / confirm last action
          </button>
        </div>
        <fieldset :disabled="!writeable" class="scorer">
          <div class="players">
            <label
              >Hitter<select
                :value="state.hitterIndex[side]"
                @change="selectPlayer('hitter', $event.target.value)"
              >
                <option
                  v-for="(id, index) in hitterIds"
                  :key="id"
                  :value="index"
                >
                  {{ index + 1 }}. {{ name(id) }}
                </option>
              </select></label
            ><label
              >Pitcher<select
                :value="state.pitcherIndex[fielding]"
                @change="selectPlayer('pitcher', $event.target.value)"
              >
                <option
                  v-for="(id, index) in pitcherIds"
                  :key="id"
                  :value="index"
                >
                  {{ name(id) }}
                </option>
              </select></label
            ><small>Hitter: {{config[side].hitPositions?.[hitter] || 'DH'}} · Bats {{allPlayers.find(p=>p.id===hitter)?.hit_side || '—'}}<br />Pitcher throws {{allPlayers.find(p=>p.id===pitcher)?.throw_side || '—'}}</small>
            <button v-if="session.config" @click="openAdd">
              ＋ Add player</button
            ><button @click="controls = !controls">Game controls</button>
          </div>
          <section v-if="adding" class="controls">
            <h2>Add a roster player</h2>
            <label
              >Side<select v-model="addSide" @change="loadAddRoster">
                <option value="away">Away / hitting team</option>
                <option value="home">Home / pitching team</option>
              </select></label
            ><label
              >Role<select v-model="addRole">
                <option value="hitters">Hitter</option>
                <option value="pitchers">Pitcher</option>
              </select></label
            ><label
              >Player<select v-model="addPlayer">
                <option value="">Choose player</option>
                <option
                  v-for="p in addRoster.filter(
                    (p) => !config[addSide][addRole].includes(p.id),
                  )"
                  :key="p.id"
                  :value="p.id"
                >
                  {{ p.name?.full || "Player" }}
                </option>
              </select></label
            ><button @click="adding = false">Cancel</button
            ><button :disabled="!addPlayer" @click="addToLineup">
              Add to lineup
            </button>
          </section>
          <div v-if="!review" class="scoring-columns">
            <section class="glass">
              <div class="choices">
                <div>
                  <h2>Pitch</h2>
                  <button
                    v-for="p in pitchTypes"
                    :key="p"
                    :class="{ active: pitch === p }"
                    @click="pitch = p"
                  >
                    {{ p }}
                  </button>
                </div>
                <div>
                  <h2>Trajectory</h2>
                  <button
                    v-for="(label, t) in contacts"
                    :key="t"
                    :class="{ active: trajectory === t }"
                    @click="trajectory = t"
                  >
                    {{ label }}
                  </button>
                </div>
              </div>
              <div class="summary">
                <span
                  >Pitches <b>{{ rows.length }}</b></span
                ><span
                  >Matchup
                  <b>{{
                    rows.filter(
                      (r) =>
                        r.pitching?.pitcher_id === pitcher &&
                        r.batting?.batter_id === hitter,
                    ).length
                  }}</b></span
                >
              </div>
            </section>
            <section class="target">
              <div
                class="catcher"
                tabindex="0"
                role="button"
                aria-label="Select pitch location; arrow keys move the marker"
                @click="chooseLocation"
                @keydown="arrow"
              >
                <div class="strike-zone"></div>
                <div v-if="location" class="dot" :style="pointStyle"></div>
              </div>
              <p>
                {{
                  location
                    ? `${info.zone === "S" ? "Strike" : "Ball"} · ${info.pitch_side}`
                    : "Tap pitch location"
                }}
              </p>
              <div class="bases">
                <span
                  v-for="(runner, i) in state.runners"
                  :key="i"
                  :class="{ occupied: runner }"
                  >{{ i + 1 }}B {{ runner ? name(runner) : "Empty" }}</span
                >
              </div>
            </section>
            <section class="glass keypad-panel">
              <label
                >Pitch velocity · mph<input
                  v-model="velocity"
                  inputmode="numeric"
                  placeholder="—"
                  aria-label="Pitch velocity"
              /></label>
              <div class="keypad">
                <button
                  v-for="k in [
                    '1',
                    '2',
                    '3',
                    '4',
                    '5',
                    '6',
                    '7',
                    '8',
                    '9',
                    'Clear',
                    '0',
                    '⌫',
                  ]"
                  :key="k"
                  @click="key(k)"
                >
                  {{ k }}
                </button>
              </div>
              <button
                class="primary save"
                :disabled="!canSave"
                @click="prepare"
              >
                {{ isInPlay ? "Ball in play →" : "Save pitch" }}</button
              ><small>Leave velocity blank if not measured.</small>
            </section>
          </div>
          <section v-else class="review">
            <h2>
              {{ isInPlay ? "Ball in play" : "Plate appearance" }} ·
              {{ name(hitter) }}
            </h2>
            <div class="review-columns">
              <div v-if="isInPlay">
                <BattingScoringField
                  v-model="field"
                  :disabled="!writeable"
                /><label
                  >Contact quality<select v-model="quality">
                    <option value="W">Weak</option>
                    <option value="A">Average</option>
                    <option value="H">Hard</option>
                  </select></label
                ><label
                  >Exit velocity · mph<input
                    v-model="exitVelocity"
                    inputmode="numeric"
                    placeholder="Not measured"
                /></label>
              </div>
              <div>
                <label
                  >Result<select
                    :value="outcome"
                    @change="suggest($event.target.value)"
                  >
                    <option v-if="!resultLabels[outcome]" :value="outcome">
                      {{ outcome.replaceAll("_", " ") }}
                    </option>
                    <option
                      v-for="(label, code) in allowedResults"
                      :key="code"
                      :value="code"
                    >
                      {{ label }}
                    </option>
                  </select></label
                >
                <h3>Confirm batter and runners</h3>
                <label v-for="m in movements" :key="`${m.from}-${m.id}`"
                  >{{ m.from ? `${m.from}B` : "Batter" }} · {{ name(m.id)
                  }}<select v-model.number="m.to">
                    <option :value="0">Out</option>
                    <option :value="1">First base</option>
                    <option :value="2">Second base</option>
                    <option :value="3">Third base</option>
                    <option :value="4">Scored</option>
                  </select></label
                ><label
                  >Third out (if this ends the inning)<select
                    v-model="thirdOut"
                  >
                    <option value="none">
                      None / runs crossed before a tag
                    </option>
                    <option value="force">Force out — cancel runs</option>
                    <option value="batter_before_first">
                      Batter before first — cancel runs
                    </option>
                    <option value="appeal_preceding">
                      Appeal preceding runner — cancel runs
                    </option>
                  </select></label
                ><label
                  >RBI override (blank = automatic)<input
                    v-model="rbi"
                    type="number"
                    min="0"
                    max="4" /></label
                ><button @click="review = false">← Back to pitch</button
                ><button class="primary" :disabled="!canSave" @click="save">
                  Save play
                </button>
              </div>
            </div>
          </section>
          <section v-if="controls" class="controls">
            <h2>Game controls</h2>
            <button
              @click="changeState({ ...clone(state), balls: 0, strikes: 0 })"
            >
              Reset count</button
            ><button @click="openRunners">Move runners</button
            ><button
              v-if="config.mode === 'game'"
              @click="changeState(advanceHalf(state, config))"
            >
              End half inning →</button
            ><button @click="special('IBB')">Intentional walk</button
            ><button @click="special('CI')">Catcher interference</button>
          </section>
          <section v-if="runnerMode" class="controls">
            <h2>Runner adjustments</h2>
            <label
              >Reason<select v-model="runnerReason">
                <option
                  v-for="reason in [
                    'stolen_base',
                    'wild_pitch',
                    'passed_ball',
                    'balk',
                    'pickoff',
                    'caught_stealing',
                    'manual_advance',
                  ]"
                  :key="reason"
                  :value="reason"
                >
                  {{ reason.replaceAll("_", " ") }}
                </option>
              </select></label
            ><label v-for="m in movements" :key="m.id"
              >{{ name(m.id)
              }}<select v-model.number="m.to">
                <option :value="0">Out</option>
                <option :value="1">First</option>
                <option :value="2">Second</option>
                <option :value="3">Third</option>
                <option :value="4">Scored</option>
              </select></label
            ><button @click="runnerMode = false">Cancel</button
            ><button class="primary" @click="saveRunners">Save runners</button>
          </section>
        </fieldset>
        <footer>
          <button
            :disabled="busy || !!pending"
            @click="router.push('/training/live-ab')"
          >
            ← Leave session</button
          ><button
            :disabled="
              busy || !!pending || session.is_completed || !session.can_undo
            "
            @click="undo"
          >
            Undo last action</button
          ><span>{{ rows.length }} saved pitches</span>
        </footer>
        <section v-if="finishing" class="controls">
          <h2>End this session?</h2>
          <textarea
            v-model="endNote"
            aria-label="End session notes"
            placeholder="Session notes"
          /><button :disabled="busy" @click="finishing = false">Cancel</button
          ><button
            :disabled="busy || !!pending"
            class="primary"
            @click="finish"
          >
            Confirm end
          </button>
        </section>
        <section v-if="statsOpen" class="stats">
          <h2>Live AB statistics · this session</h2>
          <button
            :class="{ active: statsMode === 'B' }"
            @click="statsMode = 'B'"
          >
            Hitting</button
          ><button
            :class="{ active: statsMode === 'P' }"
            @click="statsMode = 'P'"
          >
            Pitching</button
          ><button
            :disabled="busy || !!pending"
            @click="router.push(`/training/live-ab/statistic/${id}`)"
          >
            Full Live AB statistics →</button
          ><LiveABBoxScore
            :rows="rows"
            :config="config"
            :adjustments="session.adjustments || []"
          /><LivePracticeStats
            :key="statsMode"
            :mode="statsMode"
            :rows="
              rows
                .map((r) => (statsMode === 'B' ? r.batting : r.pitching))
                .filter(Boolean)
            "
            :players="allPlayers"
          />
        </section>
      </template></main
  ></Layout>
</template>
<style scoped>
.liveab {
  max-width: 1400px;
  margin: 24px auto;
  padding: 20px;
  background: #0b1020;
  color: white;
  border: 1px solid #5d6479;
  border-radius: 24px;
}
h1 {
  font-size: 23px;
  font-weight: 800;
}
h2,
h3 {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 12px;
}
small {
  font-size: 11px;
  color: #d6d8e0;
}
header,
nav,
footer,
.players {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
header {
  justify-content: space-between;
  background: #111a2c;
  padding: 16px;
  border-radius: 16px;
}
button {
  border: 1px solid #ffffff55;
  border-radius: 22px;
  padding: 12px 18px;
  min-height: 44px;
  color: white;
  background: #232a3b;
  font-weight: 700;
}
button:disabled {
  opacity: 0.4;
}
.primary,
.active {
  background: #ff2450 !important;
}
button:focus-visible,
select:focus-visible,
.catcher:focus-visible {
  outline: 3px solid #8de6ff;
  outline-offset: 2px;
}
.scoreboard {
  display: flex;
  justify-content: space-around;
  align-items: center;
  gap: 15px;
  padding: 18px;
  background: #191b44;
  margin: 15px 0;
  border-radius: 16px;
}
.scoreboard strong {
  display: flex;
  align-items: center;
  gap: 16px;
}
.scoreboard b {
  font-size: 30px;
}
.scoreboard div {
  text-align: center;
}
.scoreboard small {
  display: block;
  margin-top: 8px;
}
.inning-table {
  overflow: auto;
}
table {
  width: 100%;
  text-align: center;
}
th,
td {
  padding: 8px;
  border-bottom: 1px solid #4b5369;
}
.scorer {
  border: 0;
  margin: 16px 0;
  padding: 14px;
  background: url("../../assets/img/training/bullpen-stadium.png") center/cover;
  border-radius: 20px;
}
.players {
  background: #070d18c9;
  padding: 12px;
  border-radius: 16px;
  margin-bottom: 14px;
}
.players label {
  flex: 1;
  min-width: 160px;
}
.scoring-columns {
  display: grid;
  grid-template-columns: 1fr 1.2fr 1fr;
  gap: 16px;
  align-items: stretch;
}
.glass {
  padding: 14px;
  background: #080d1bb8;
  border: 1px solid #ffffff66;
  border-radius: 20px;
}
.choices {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.choices button {
  display: block;
  width: 100%;
  margin-bottom: 8px;
  background: #161b2c99;
}
.summary {
  display: flex;
  gap: 8px;
}
.summary span {
  display: grid;
  padding: 12px;
  background: #1c1945b0;
  border-radius: 16px;
  flex: 1;
}
.summary b {
  font-size: 24px;
}
.target {
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.catcher {
  position: relative;
  aspect-ratio: 1;
  cursor: crosshair;
  touch-action: manipulation;
}
.strike-zone {
  position: absolute;
  left: 25%;
  top: 23.333%;
  width: 50%;
  height: 51.667%;
  border: 3px solid white;
  background: #ffffff44;
  border-radius: 8px;
  pointer-events: none;
}
.dot {
  position: absolute;
  width: 18px;
  height: 18px;
  border: 3px solid white;
  background: #ff2450;
  border-radius: 50%;
  transform: translate(-50%, -50%);
  pointer-events: none;
}
.target p {
  text-align: center;
  background: #080d1bc9;
  padding: 8px;
  border-radius: 16px;
}
.bases {
  display: flex;
  justify-content: center;
  gap: 5px;
  flex-wrap: wrap;
  margin-top: 10px;
}
.bases span {
  font-size: 11px;
  padding: 10px;
  background: #141a29;
  border-radius: 10px;
}
.bases .occupied {
  background: #b21942;
}
.keypad {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  margin: 16px 0;
}
.keypad button {
  min-height: 55px;
  background: #e91d4b88;
  font-size: 22px;
  padding: 8px;
}
.keypad-panel input {
  font-size: 40px;
  text-align: center;
  border-radius: 25px;
  background: #070a15bb;
}
.save {
  width: 100%;
  margin-bottom: 12px;
}
.review,
.controls,
.stats {
  background: #0b1324f2;
  border: 1px solid #64728c;
  border-radius: 18px;
  padding: 20px;
  margin: 15px 0;
}
.review-columns {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}
label {
  display: block;
  font-size: 13px;
  margin-bottom: 12px;
}
input,
select,
textarea {
  display: block;
  width: 100%;
  background: #162137;
  color: white;
  padding: 12px;
  border: 1px solid #708099;
  border-radius: 10px;
  margin-top: 6px;
  min-width: 0;
}
.controls button,
.review button,
.stats > button {
  margin: 6px;
}
.controls textarea {
  min-height: 90px;
}
.error,
.notice {
  padding: 12px;
  border-radius: 10px;
  margin: 12px 0;
  background: #591632;
}
.notice {
  background: #15263c;
}
footer {
  justify-content: space-between;
}
.stats {
  overflow: auto;
}
@media (max-width: 800px) {
  .scoring-columns {
    grid-template-columns: 1fr 1fr;
  }
  .target {
    grid-column: 1/-1;
    grid-row: 1;
    max-width: 350px;
    width: 100%;
    justify-self: center;
  }
  .catcher {
    background: url("../../assets/img/training/bullpen-stadium.png")
      center/cover;
  }
  .review-columns {
    grid-template-columns: 1fr;
  }
  .liveab {
    margin: 8px;
    padding: 12px;
  }
  .scoreboard {
    font-size: 12px;
  }
  .scoreboard strong {
    flex-direction: column;
  }
  .keypad button {
    font-size: 18px;
  }
  .choices button {
    padding: 10px 4px;
  }
  .glass {
    padding: 10px;
  }
}
</style>
