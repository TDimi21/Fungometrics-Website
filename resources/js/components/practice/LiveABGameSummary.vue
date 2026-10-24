<script setup>
import { computed } from "vue";
import { totals } from "@/utils/liveabWebScoring";
const props = defineProps({ state: Object, config: Object });
const score = computed(() => totals(props.state)),
  innings = computed(() =>
    Math.max(props.config?.innings || 1, props.state.inning),
  );
</script>
<template>
  <section v-if="state" class="game-summary">
    <h3>
      {{ config?.name || "Live AB" }} ·
      {{ state.finished ? "Final score" : "Game in progress" }}
    </h3>
    <div class="scores">
      <strong>Away {{ score.away }}</strong
      ><span
        >{{ state.half === "top" ? "Top" : "Bottom" }} {{ state.inning }} ·
        {{ state.outs }} outs</span
      ><strong>Home {{ score.home }}</strong>
    </div>
    <div class="scroll">
      <table>
        <thead>
          <tr>
            <th>Team</th>
            <th v-for="n in innings" :key="n">{{ n }}</th>
            <th>Runs</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="side in ['away', 'home']" :key="side">
            <th>{{ side === "away" ? "Away" : "Home" }}</th>
            <td v-for="n in innings" :key="n">
              {{
                state.scores.find(
                  (s) =>
                    s.inning === n &&
                    s.half === (side === "away" ? "top" : "bottom"),
                )?.runs ?? "—"
              }}
            </td>
            <td>{{ score[side] }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
<style scoped>
.game-summary {
  background: #151c35;
  color: #fff;
  border: 1px solid #53627b;
  border-radius: 15px;
  padding: 20px;
  margin: 16px;
}
.game-summary h3 {
  font-size: 18px;
  font-weight: 800;
  margin-bottom: 12px;
}
.scores {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}
.scroll {
  overflow: auto;
}
table {
  width: 100%;
  text-align: center;
  margin-top: 14px;
}
td,
th {
  padding: 8px;
  border-bottom: 1px solid #414e69;
}
</style>
