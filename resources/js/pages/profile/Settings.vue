<script setup>
import { reactive, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useUserStore } from '@/store/user'
import { useTeamStore } from '@/store/team'
import { usePlayerStore } from '@/store/players'
import { useAuthStore } from '@/store/auth'
import { getUiTheme, applyUiTheme } from '@/composables/useUiTheme'
import axios from 'axios'
import { toast } from '@/utils/AlertPlugin'

const router = useRouter()
const userStore = useUserStore()
const teamStore = useTeamStore()
const playerStore = usePlayerStore()
const { setToken, isLogged } = useAuthStore()
const { userData } = userStore
const theme = ref(getUiTheme())
const claimForm = reactive({ phone: '', teamCode: '' })
const joinLoading = ref(false)
const isPlayer = computed(() => (userData?.type || '').toLowerCase() === 'player')

const normalizeDigits = (value) => String(value || '').replace(/\D+/g, '')

const getTeamIdCandidates = (teamLike) => {
  const ids = [teamLike?.id_team, teamLike?.id]
    .filter(Boolean)
    .map((v) => String(v))
  return [...new Set(ids)]
}

const getSessionCountForTeam = async (apiUrl, token, teamLike) => {
  const ids = getTeamIdCandidates(teamLike)
  for (const id of ids) {
    try {
      const res = await axios.get(apiUrl + 'coach/sessions/lasts/' + id, {
        headers: { Authorization: `Bearer ${token}` },
      })
      const d = res?.data?.data ?? {}
      return [
        d.batting,
        d.bullpen,
        d.cage,
        d.live,
        d.weight_ball,
        d.long_toss,
        d.exit_velocity,
      ].reduce((sum, arr) => sum + (Array.isArray(arr) ? arr.length : 0), 0)
    } catch (e) {
      const status = e?.response?.status
      if (status !== 404 && status !== 403) break
    }
  }
  return 0
}

const pickBestTeamForCoach = async (apiUrl, token, teams) => {
  const list = Array.isArray(teams) ? teams : []
  if (!list.length) return null
  for (const candidate of list) {
    const count = await getSessionCountForTeam(apiUrl, token, candidate)
    if (count > 0) return candidate
  }
  return list[0]
}

const applyAuthSession = async ({ payload, apiUrl }) => {
  const token = payload?.token
  const user = payload?.user || null
  const team = payload?.team || null
  const teams = user?.teams || []

  if (!token || !user) throw new Error('missing auth payload')

  setToken(token)
  isLogged.status = true
  await userStore.setData(user)

  if ((user?.type || '').toLowerCase() === 'player') {
    await teamStore.setTeam(team || {})
    await teamStore.setTeams(team ? [team] : [])
    return
  }

  const rosterTeams = Array.isArray(teams) ? teams : []
  const selectedTeam = await pickBestTeamForCoach(apiUrl, token, rosterTeams)
  await teamStore.setTeam(selectedTeam ?? rosterTeams[0] ?? team ?? {})
  await teamStore.setTeams(rosterTeams)
  await playerStore.setPlayers(user?.players || [])
}

const joinTeamByCode = async () => {
  const apiUrl = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || ''
  const phone = normalizeDigits(isPlayer.value ? userData?.phone : (claimForm.phone || userData?.phone))
  const teamCode = String(claimForm.teamCode || '').trim().toUpperCase()

  if (phone.length < 10) {
    await toast.fire({ icon: 'warning', title: 'Join Team', text: 'Enter a valid mobile number.' })
    return
  }
  if (teamCode.length !== 6) {
    await toast.fire({ icon: 'warning', title: 'Join Team', text: 'Team code must be 6 characters.' })
    return
  }

  joinLoading.value = true
  try {
    const dataForm = new FormData()
    dataForm.append('phone', phone)
    dataForm.append('team_code', teamCode)

    const response = await axios.post(apiUrl + 'player/join', dataForm)
    const body = response?.data || {}

    if (body?.status === 'not_registered') {
      await toast.fire({ icon: 'warning', title: 'Join Team', text: 'No account found with that phone number.' })
      return
    }

    if (body?.status !== 'success' || !body?.data?.token) {
      throw new Error(body?.message || 'Invalid team code')
    }

    await applyAuthSession({ payload: body.data, apiUrl })
    await toast.fire({ icon: 'success', title: 'Joined Team', text: body?.message || 'You have joined successfully.' })
    await router.push(isPlayer.value ? '/player-dashboard' : '/dashboard')
  } catch (error) {
    const message = error?.response?.data?.message || error?.message || 'Could not join team. Please try again.'
    await toast.fire({ icon: 'error', title: 'Join Team', text: message })
  } finally {
    joinLoading.value = false
  }
}

const openEditProfile = () => {
  router.push(userData.type === 'player' ? '/profile-player' : '/profile')
}

const setTheme = (next) => {
  theme.value = next === 'light' ? 'light' : 'dark'
  applyUiTheme(theme.value)
}
</script>

<template>
  <Layout>
    <div class="settings-page w-full px-4 md:px-8 py-6 md:py-10" :class="theme === 'light' ? 'theme-light' : 'theme-dark'">
      <div class="settings-panel max-w-4xl mx-auto rounded-2xl border border-white/10 bg-[#0a1020]/80 p-5 md:p-7">
        <h1 class="text-2xl md:text-4xl font-black uppercase tracking-wider text-white">Settings</h1>
        <p class="mt-2 text-sm font-bold text-white/65">Manage your account settings and interface preferences.</p>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
          <button
            type="button"
            class="settings-tile rounded-xl border border-white/15 bg-white/5 p-5 text-left hover:bg-white/10 transition"
            @click="openEditProfile"
          >
            <p class="text-xs font-black uppercase tracking-widest text-white/50">Account</p>
            <p class="mt-1 text-lg font-black text-white">Edit Profile</p>
            <p class="mt-1 text-sm font-bold text-white/65">Update team and personal profile details.</p>
          </button>

          <div class="settings-tile rounded-xl border border-white/15 bg-white/5 p-5">
            <p class="text-xs font-black uppercase tracking-widest text-white/50">Appearance</p>
            <p class="mt-1 text-lg font-black text-white">Theme Mode</p>
            <div class="mt-3 flex gap-2">
              <button
                type="button"
                class="px-3 py-2 rounded-lg border text-xs font-black uppercase tracking-wide"
                :class="theme === 'dark' ? 'bg-[#C00000]/20 border-[#C00000]/50 text-white' : 'bg-white/5 border-white/15 text-white/60'"
                @click="setTheme('dark')"
              >Dark</button>
              <button
                type="button"
                class="px-3 py-2 rounded-lg border text-xs font-black uppercase tracking-wide"
                :class="theme === 'light' ? 'bg-[#C00000]/20 border-[#C00000]/50 text-white' : 'bg-white/5 border-white/15 text-white/60'"
                @click="setTheme('light')"
              >Light</button>
            </div>
            <p class="mt-3 text-sm font-bold text-white/65">Current: {{ theme === 'light' ? 'Light mode' : 'Dark mode' }}</p>
          </div>

          <div class="settings-tile rounded-xl border border-white/15 bg-white/5 p-5 md:col-span-2">
            <p class="text-xs font-black uppercase tracking-widest text-white/50">{{ isPlayer ? 'Join Team' : 'Join / Link Team' }}</p>
            <p class="mt-1 text-lg font-black text-white">Team Claim Code</p>
            <p class="mt-1 text-sm font-bold text-white/65">
              {{ isPlayer
                ? 'Enter your 6-character team code to join instantly.'
                : 'Use mobile number + 6-character team code to join a roster instantly.' }}
            </p>

            <div class="mt-4 grid grid-cols-1 gap-3" :class="isPlayer ? 'md:grid-cols-2' : 'md:grid-cols-3'">
              <input
                v-if="!isPlayer"
                v-model="claimForm.phone"
                type="tel"
                autocomplete="tel"
                placeholder="Mobile number"
                class="rounded-lg border border-white/20 bg-white/5 px-3 py-2 text-sm font-bold text-white placeholder:text-white/40"
              />
              <input
                v-model="claimForm.teamCode"
                type="text"
                maxlength="6"
                autocomplete="off"
                placeholder="Team code"
                @input="claimForm.teamCode = String(claimForm.teamCode || '').toUpperCase()"
                class="rounded-lg border border-white/20 bg-white/5 px-3 py-2 text-sm font-black uppercase tracking-[0.18em] text-white placeholder:text-white/40"
              />
              <button
                type="button"
                :disabled="joinLoading"
                class="rounded-lg border border-[#C00000]/60 bg-[#C00000]/25 px-3 py-2 text-xs font-black uppercase tracking-wider text-white hover:bg-[#C00000]/40 transition disabled:opacity-60"
                @click="joinTeamByCode"
              >
                {{ joinLoading ? 'Joining...' : 'Join Team' }}
              </button>
            </div>

            <p v-if="isPlayer" class="mt-2 text-xs font-bold text-white/50">
              Using your account phone automatically.
            </p>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
.settings-page.theme-light .settings-panel {
  background: rgba(255, 255, 255, 0.92) !important;
  border-color: rgba(15, 23, 42, 0.14) !important;
}

.settings-page.theme-light .settings-tile {
  background: rgba(15, 23, 42, 0.06) !important;
  border-color: rgba(15, 23, 42, 0.14) !important;
}

.settings-page.theme-light h1,
.settings-page.theme-light p,
.settings-page.theme-light button {
  color: #0f172a !important;
}
</style>
