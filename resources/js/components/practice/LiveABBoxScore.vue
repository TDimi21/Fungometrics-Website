<script setup>
import { computed, ref } from "vue";
import { liveabBoxScore, playResult } from "@/utils/liveabBoxScore";
const props = defineProps({
  rows: { type: Array, default: () => [] },
  config: Object,
  adjustments: { type: Array, default: () => [] },
});
const tab = ref("box"),
  score = computed(() =>
    liveabBoxScore(props.rows, props.adjustments, props.config),
  );
const displayName = (p) =>
  [p?.first_name, p?.last_name].filter(Boolean).join(" ") || "Player";
</script>
<template>
  <section class="box-score">
    <nav>
      <button :class="{ active: tab === 'box' }" @click="tab = 'box'">
        Box score</button
      ><button :class="{ active: tab === 'card' }" @click="tab = 'card'">
        Scorecard / ball by ball
      </button>
    </nav>
    <p v-if="!rows.length">Save a pitch to see this session’s results.</p>
    <template v-else-if="tab === 'box'"
      ><h3>Hitters</h3>
      <div class="scroll">
        <table>
          <thead>
            <tr>
              <th>Player</th>
              <th
                v-for="k in [
                  'PA',
                  'AB',
                  'H',
                  '2B',
                  '3B',
                  'HR',
                  'BB',
                  'K',
                  'RBI',
                  'AVG',
                  'OBP',
                  'SLG',
                  'OPS',
                ]"
                :key="k"
              >
                {{ k }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in score.hitters" :key="p.id">
              <th>{{ p.name }}</th>
              <td
                v-for="k in [
                  'PA',
                  'AB',
                  'H',
                  'double',
                  'triple',
                  'homeRun',
                  'BB',
                  'SO',
                  'RBI',
                ]"
                :key="k"
              >
                {{ p[k] }}
              </td>
              <td v-for="k in ['AVG', 'OBP', 'SLG', 'OPS']" :key="k">
                {{ p[k].toFixed(3) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <h3>Pitchers</h3>
      <div class="scroll">
        <table>
          <thead>
            <tr>
              <th>Player</th>
              <th
                v-for="k in [
                  'Pitches',
                  'IP',
                  'H',
                  'BB',
                  'K',
                  'R',
                  'Strike %',
                  'Avg mph',
                ]"
                :key="k"
              >
                {{ k }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in score.pitchers" :key="p.id">
              <th>{{ p.name }}</th>
              <td
                v-for="k in [
                  'pitches',
                  'IP',
                  'H',
                  'BB',
                  'SO',
                  'R',
                  'strikePct',
                  'avgVelo',
                ]"
                :key="k"
              >
                {{ p[k] }}
              </td>
            </tr>
          </tbody>
        </table>
      </div></template
    >
    <div v-else class="scroll">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Inning</th>
            <th>Hitter</th>
            <th>Pitcher</th>
            <th>Count</th>
            <th>Pitch</th>
            <th>Contact</th>
            <th>mph</th>
            <th>Result</th>
            <th>RBI</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, index) in rows" :key="r.id">
            <td>{{ index + 1 }}</td>
            <td>{{ r.inning ? `${r.half_inning} ${r.inning}` : "—" }}</td>
            <th>{{ displayName(r.batting?.profile) }}</th>
            <td>{{ displayName(r.pitching?.profile) }}</td>
            <td>{{ r.count_b_s }}</td>
            <td>{{ r.pitching?.type_throw }}</td>
            <td>{{ r.batting?.type_of_hit }}</td>
            <td>{{ Number(r.pitching?.miles_per_hour) || "—" }}</td>
            <td>{{ playResult(r).replaceAll("_", " ") || "In progress" }}</td>
            <td>{{ r.rbi || 0 }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <details v-if="adjustments.length">
      <summary>Runner and game adjustments ({{ adjustments.length }})</summary>
      <ul>
        <li v-for="(event, index) in adjustments" :key="index">
          {{ event.action.replaceAll("_", " ") }} · {{ event.created_at }}
        </li>
      </ul>
    </details>
  </section>
</template>
<style scoped>
.box-score {
  color: inherit;
  margin: 15px 0;
  padding: 12px;
  background: #121b2d;
  border-radius: 14px;
}
.scroll {
  overflow: auto;
}
h3 {
  font-size: 18px;
  font-weight: bold;
  margin: 18px 0 10px;
}
table {
  width: 100%;
  white-space: nowrap;
  text-align: center;
  font-size: 13px;
}
td,
th {
  padding: 10px;
  border-bottom: 1px solid #46516a;
}
th:first-child {
  text-align: left;
}
button {
  padding: 10px 15px;
  background: #26334d;
  color: white;
  border-radius: 12px;
  margin: 5px;
  min-height: 44px;
}
button.active {
  background: #ee204b;
}
button:focus-visible {
  outline: 3px solid #8de6ff;
}
</style>
