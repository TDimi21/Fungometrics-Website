<script setup>
import LongTossAlerts from '@/features/long-toss/LongTossAlerts.vue'
import {computed, onMounted} from 'vue'
import {storeToRefs} from 'pinia'
import Layout from '@/layout/Layout.vue'
import DailyReadiness from '@/components/readiness/DailyReadiness.vue'
import {useTeamStore} from '@/store/team'
const store = useTeamStore()
const {team, teams} = storeToRefs(store)
const teamId = computed(() => team.value?.id_team ?? team.value?.id)
function selectTeam(event) {
  const selected = teams.value.find(t => String(t.id_team ?? t.id) === event.target.value)
  if (selected) store.setTeam(selected)
}
onMounted(() => store.initializePreferences())
</script>
<template>
  <Layout>
    <main class="readiness-page">
      <RouterLink to="/dashboard" class="back">← Coach dashboard</RouterLink>
      <header>
        <div><h1>Player Readiness</h1><p>Review your players’ daily answers and recent history.</p></div>
        <label>Viewing team
          <select :value="teamId || ''" @change="selectTeam">
            <option v-if="!teamId" value="">Select a team</option>
            <option v-for="t in teams" :key="t.id_team ?? t.id" :value="t.id_team ?? t.id">{{t.name}}</option>
          </select>
        </label>
      </header>
      <LongTossAlerts v-if="teamId" :team-id="String(teamId)" />
      <DailyReadiness v-if="teamId" :key="teamId" :team-id="String(teamId)" />
      <p v-else>Select a team to view player readiness.</p>
    </main>
  </Layout>
</template>
<style scoped>
.readiness-page{max-width:1100px;margin:auto;padding:24px;color:#f4f8ff}.back{display:inline-block;color:#b8c9df;margin-bottom:20px}header{display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;margin-bottom:20px}h1{font-size:26px;font-weight:800}p{color:#b8c9df;margin-top:8px}label{font-size:13px;color:#b8c9df}select{display:block;background:#101d30;color:white;border:1px solid #344963;border-radius:8px;padding:12px;margin-top:6px;max-width:100%}.readiness-page :deep(.history){max-height:none}select:focus-visible,.back:focus-visible{outline:2px solid #81c9ff;outline-offset:3px}
</style>
