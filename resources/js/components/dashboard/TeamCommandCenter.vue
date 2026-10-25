<script setup>
import {computed, ref, onMounted} from 'vue'
import {storeToRefs} from 'pinia'
import {useTeamStore} from '@/store/team'
const store = useTeamStore()
const {team, teams, active_team_id, default_team_id, preferenceError, preferencesReady} = storeToRefs(store)
const saving = ref(false)
const isDefault = computed(() => !!active_team_id.value && String(active_team_id.value) === String(default_team_id.value))
const teamId = t => t.id_team ?? t.id
function select(event) {
  const selected = teams.value.find(t => String(teamId(t)) === event.target.value)
  if (selected) store.setTeam(selected)
}
async function saveDefault() { saving.value = true; try { await store.setDefaultTeam() } finally { saving.value = false } }
onMounted(() => store.initializePreferences())
</script>
<template>
  <section class="team-command" aria-labelledby="team-command-title">
    <div class="team-heading"><span>FMTRX / COACH DASHBOARD</span><h1 id="team-command-title">Team Command Center</h1></div>
    <div class="team-controls">
      <div class="team-selection"><label for="dashboard-team">Viewing team</label><select id="dashboard-team" :value="active_team_id || ''" @change="select" :disabled="!preferencesReady || saving"><option v-if="!teams.length" value="">{{ preferencesReady ? 'No teams available' : 'Loading teams…' }}</option><option v-for="item in teams" :key="teamId(item)" :value="teamId(item)">{{ item.name }}{{ String(teamId(item)) === String(default_team_id) ? ' ★ Default' : '' }}</option></select></div>
      <div class="team-default"><span v-if="isDefault" class="default-label">★ This is your default team</span><span v-else>Switching teams keeps your default unchanged.</span><button type="button" :disabled="!active_team_id || !preferencesReady || saving || isDefault" @click="saveDefault">{{ saving ? 'Saving…' : isDefault ? 'Default team' : 'Set as Default' }}</button></div>
    </div>
    <p v-if="preferenceError" role="alert">{{ preferenceError }} <button type="button" @click="store.initializePreferences()">Retry</button></p>
    <p v-else class="team-scope">{{ team?.name ? `Showing ${team.name} only. Roster, planner, statistics and sessions follow this team.` : 'Your coach account needs a team to display its dashboard.' }}</p>
  </section>
</template>
<style scoped>
.team-command{background:linear-gradient(120deg,#142239,#0a1422);border:1px solid #3a506b;border-top:3px solid #f03940;border-radius:12px;padding:20px;margin-bottom:20px;color:#ecf3fd}.team-heading>span{font-size:10px;letter-spacing:1.7px;color:#a7b8d0}.team-heading h1{font-size:24px;font-weight:800;margin:5px 0 18px}.team-controls{display:flex;align-items:end;gap:24px;flex-wrap:wrap}.team-selection{flex:1;min-width:220px}.team-selection label{display:block;font-size:12px;color:#a7b8d0;margin-bottom:6px}.team-selection select{width:100%;background:#091524;color:white;border:1px solid #526985;border-radius:7px;padding:12px;font-size:17px}.team-default{display:flex;flex-direction:column;gap:8px;font-size:12px;color:#a7b8d0}.default-label{color:#ffd17b}.team-command button{background:#b61d32;border:1px solid #ed394b;color:white;border-radius:6px;padding:10px 16px}.team-command button:disabled{opacity:.55}.team-scope{margin-top:14px;color:#a7b8d0;font-size:12px}.team-command [role=alert]{color:#ffb5b5;margin-top:12px}.team-command :is(button,select):focus-visible{outline:2px solid #9bcdff;outline-offset:3px}@media(max-width:600px){.team-controls{align-items:stretch}.team-selection,.team-default{width:100%}}
</style>
