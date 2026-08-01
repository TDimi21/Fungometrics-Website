<script setup>
import { reactive, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useUserStore } from '@/store/user'
import { getUiTheme, applyUiTheme } from '@/composables/useUiTheme'
import { toast } from '@/utils/AlertPlugin'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const router = useRouter()
const userStore = useUserStore()
const { userData } = userStore
const theme = ref(getUiTheme())
const claimForm = reactive({ phone: '', teamCode: '' })
const joinLoading = ref(false)
const isPlayer = computed(() => (userData?.type || '').toLowerCase() === 'player')
const { axiosPost } = useAxiosAuth()

const joinTeamByCode = async () => {
  const teamCode = String(claimForm.teamCode || '').trim().toUpperCase()

  if (!isPlayer.value) {
    await toast.fire({ icon: 'warning', title: 'Join Team', text: 'Coach team access requires a coach invitation.' })
    return
  }
  if (teamCode.length !== 6) {
    await toast.fire({ icon: 'warning', title: 'Join Team', text: 'Team code must be 6 characters.' })
    return
  }

  joinLoading.value = true
  try {
    const response = await axiosPost('player/teams/join', { team_code: teamCode })
    const body = response?.data || {}
    if (body?.status !== 'success') {
      throw new Error(body?.message || 'Invalid team code')
    }

    await toast.fire({ icon: 'success', title: 'Joined Team', text: body?.message || 'You have joined successfully.' })
    await router.push('/player-dashboard')
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

          <div v-if="isPlayer" class="settings-tile rounded-xl border border-white/15 bg-white/5 p-5 md:col-span-2">
            <p class="text-xs font-black uppercase tracking-widest text-white/50">Join Team</p>
            <p class="mt-1 text-lg font-black text-white">Team Code</p>
            <p class="mt-1 text-sm font-bold text-white/65">
              Enter your 6-character team code to join while signed in.
            </p>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
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
