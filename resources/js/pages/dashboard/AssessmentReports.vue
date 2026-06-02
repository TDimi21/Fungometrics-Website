<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useTeamStore } from '@/store/team'
import { storeToRefs } from 'pinia'

const { axiosGet } = useAxiosAuth()
const teamStore = useTeamStore()
const { team } = storeToRefs(teamStore)

const loading = ref(false)
const rows = ref([])
const selected = ref(null)

const activeTeamId = computed(() => team.value?.id_team ?? team.value?.id ?? null)

const scoreColor = (s) => {
  if (!s && s !== 0) return '#64748B'
  if (s >= 85) return '#2ECC71'
  if (s >= 70) return '#27AE60'
  if (s >= 55) return '#F39C12'
  if (s >= 40) return '#E67E22'
  return '#E74C3C'
}

const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'

const loadReports = async () => {
  if (!activeTeamId.value) {
    rows.value = []
    selected.value = null
    return
  }

  loading.value = true
  try {
    const { data } = await axiosGet(`assessments/team/${activeTeamId.value}?all=1`)
    rows.value = data?.data ?? []
    selected.value = rows.value[0] ?? null
  } catch {
    rows.value = []
    selected.value = null
  } finally {
    loading.value = false
  }
}

onMounted(loadReports)
watch(activeTeamId, loadReports)
</script>

<template>
  <Layout>
    <div class="w-full h-full p-6 overflow-y-auto bg-[#080E1A] text-white">
      <div class="max-w-6xl mx-auto">
        <div class="mb-4">
          <h1 class="text-lg font-black uppercase tracking-widest">Assessment Reports</h1>
          <p class="text-sm text-white/45">All saved assessments for the active team. Click a row to open the report.</p>
        </div>

        <div v-if="loading" class="rounded-xl border border-white/10 bg-white/5 px-4 py-8 text-center text-white/45">
          Loading reports...
        </div>

        <div v-else-if="!rows.length" class="rounded-xl border border-white/10 bg-white/5 px-4 py-8 text-center text-white/40">
          No assessment reports found for this team.
        </div>

        <div v-else class="grid grid-cols-1 xl:grid-cols-[1.2fr_1fr] gap-4">
          <div class="rounded-xl border border-white/10 overflow-hidden">
            <div class="grid grid-cols-[1.4fr_1fr_0.8fr] gap-2 px-3 py-2 bg-white/10 text-[10px] uppercase tracking-widest text-white/55 font-bold">
              <div>Player / Date</div>
              <div>Type</div>
              <div class="text-right">Score</div>
            </div>
            <button
              v-for="r in rows"
              :key="r.id"
              class="w-full grid grid-cols-[1.4fr_1fr_0.8fr] gap-2 px-3 py-3 border-t border-white/10 text-left hover:bg-white/10"
              :class="selected?.id === r.id ? 'bg-[#C00000]/20' : 'bg-white/5'"
              @click="selected = r"
            >
              <div>
                <div class="text-sm font-bold text-white">{{ r.profile?.first_name }} {{ r.profile?.last_name }}</div>
                <div class="text-xs text-white/45">{{ formatDate(r.assessment_date) }}</div>
              </div>
              <div class="text-xs text-white/70 capitalize self-center">{{ r.type || 'full' }}</div>
              <div class="text-sm font-black self-center text-right" :style="{ color: scoreColor(r.overall_score) }">{{ r.overall_score ?? '—' }}</div>
            </button>
          </div>

          <div v-if="selected" class="rounded-xl border border-white/10 bg-white/5 p-4">
            <div class="text-sm font-black text-white mb-1">{{ selected.profile?.first_name }} {{ selected.profile?.last_name }}</div>
            <div class="text-xs text-white/50 mb-3">{{ formatDate(selected.assessment_date) }} · {{ selected.type }} assessment</div>

            <div class="grid grid-cols-3 gap-2 mb-3">
              <div class="rounded-lg border border-white/10 bg-white/5 px-2 py-2 text-center">
                <div class="text-[10px] uppercase text-white/45">Strength</div>
                <div class="font-black" :style="{ color: scoreColor(selected.strength_overall_score) }">{{ selected.strength_overall_score ?? '—' }}</div>
              </div>
              <div class="rounded-lg border border-white/10 bg-white/5 px-2 py-2 text-center">
                <div class="text-[10px] uppercase text-white/45">Mobility</div>
                <div class="font-black" :style="{ color: scoreColor(selected.mobility_overall_score) }">{{ selected.mobility_overall_score ?? '—' }}</div>
              </div>
              <div class="rounded-lg border border-white/10 bg-white/5 px-2 py-2 text-center">
                <div class="text-[10px] uppercase text-white/45">Overall</div>
                <div class="font-black" :style="{ color: scoreColor(selected.overall_score) }">{{ selected.overall_score ?? '—' }}</div>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-2 mb-3">
              <div class="rounded-lg border border-white/10 bg-white/5 px-2 py-2">
                <div class="text-[10px] uppercase text-white/45">Team Percentile</div>
                <div class="font-black text-white">{{ selected.overall_team_percentile ?? '—' }}</div>
              </div>
              <div class="rounded-lg border border-white/10 bg-white/5 px-2 py-2">
                <div class="text-[10px] uppercase text-white/45">Age Group Percentile</div>
                <div class="font-black text-white">{{ selected.overall_age_percentile ?? '—' }}</div>
              </div>
            </div>

            <div v-if="selected.notes" class="text-xs text-white/60 italic border-t border-white/10 pt-2">{{ selected.notes }}</div>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>
