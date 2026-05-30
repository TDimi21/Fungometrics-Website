<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useUserStore } from '@/store/user'
import { getUiTheme, applyUiTheme } from '@/composables/useUiTheme'

const router = useRouter()
const { userData } = useUserStore()
const theme = ref(getUiTheme())

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
