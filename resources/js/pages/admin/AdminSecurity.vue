<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'

const router = useRouter()

const SECTIONS = [
  {
    title: 'PASSWORD POLICIES',
    items: [
      { label: 'Force Password Reset', desc: 'Require all users to reset passwords', icon: '🔑', action: 'force_reset' },
      { label: 'Password Complexity',  desc: 'Min 8 chars, uppercase, number required', icon: '🔐', action: 'complexity', active: true },
      { label: 'Password Expiry',      desc: 'Expire passwords every 90 days', icon: '⏱️', action: 'expiry' },
    ],
  },
  {
    title: 'MULTI-FACTOR AUTHENTICATION',
    items: [
      { label: 'Require MFA for Coaches', desc: 'Enforce 2FA on all coach accounts', icon: '📱', action: 'mfa_coaches' },
      { label: 'SMS Verification',        desc: 'Send login codes via SMS', icon: '💬', action: 'sms_verify', active: true },
      { label: 'MFA Enrollment Status',   desc: '6 of 9 coaches enrolled', icon: '📊', action: 'mfa_status' },
    ],
  },
  {
    title: 'SESSION MANAGEMENT',
    items: [
      { label: 'Active Sessions',   desc: 'View and revoke active sessions', icon: '🔗', action: 'sessions' },
      { label: 'Session Timeout',   desc: 'Auto-logout after 30 minutes idle', icon: '⏰', action: 'timeout', active: true },
      { label: 'Concurrent Sessions', desc: 'Max 1 active session per user', icon: '🔒', action: 'concurrent' },
    ],
  },
  {
    title: 'THREAT DETECTION',
    items: [
      { label: 'Failed Login Lockout',   desc: 'Lock after 5 failed attempts', icon: '🚫', action: 'lockout', active: true },
      { label: 'Suspicious IP Alerts',   desc: 'Alert on logins from new locations', icon: '📍', action: 'ip_alerts' },
      { label: 'Brute Force Protection', desc: 'Rate limit login attempts', icon: '🛡️', action: 'brute_force', active: true },
    ],
  },
]

const toggles = ref(
  SECTIONS.flatMap(s => s.items)
    .filter(i => i.active)
    .reduce((acc, i) => ({ ...acc, [i.action]: true }), {})
)

const alertMsg = ref('')

function handleAction(action) {
  if (action === 'force_reset') {
    alertMsg.value = 'Password reset scheduled for all users on next login.'
    setTimeout(() => { alertMsg.value = '' }, 3000)
    return
  }
  if (action === 'sessions') {
    alertMsg.value = 'Session management UI coming soon.'
    setTimeout(() => { alertMsg.value = '' }, 3000)
    return
  }
  if (action === 'mfa_status') {
    alertMsg.value = '6 of 9 coaches enrolled in MFA. 3 accounts pending.'
    setTimeout(() => { alertMsg.value = '' }, 3000)
    return
  }
  toggles.value[action] = !toggles.value[action]
}
</script>

<template>
  <Layout>
    <!-- Header -->
    <div class="flex items-center gap-3 mb-6">
      <button class="text-white/50 hover:text-white" @click="router.push({ name: 'admin.dashboard' })">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <h1 class="text-white text-xl font-bold">Security</h1>
    </div>

    <!-- Alert -->
    <Transition name="fade">
      <div v-if="alertMsg" class="bg-blue-900/30 border border-blue-500/30 text-blue-300 text-sm rounded-xl p-3 mb-4 text-center">
        {{ alertMsg }}
      </div>
    </Transition>

    <!-- Security Score -->
    <div class="bg-white/5 border border-white/10 rounded-xl p-6 text-center mb-6">
      <p class="text-white/35 text-xs tracking-widest font-bold uppercase mb-2">Security Score</p>
      <p class="text-amber-400 text-6xl font-black leading-none">78</p>
      <p class="text-white/35 text-sm mt-1">/100</p>
      <div class="w-full bg-white/8 rounded-full h-2 mt-4 overflow-hidden">
        <div class="h-full bg-amber-400 rounded-full" style="width: 78%"></div>
      </div>
      <p class="text-white/35 text-xs mt-3">Enable MFA for coaches to improve score</p>
    </div>

    <!-- Sections -->
    <div v-for="section in SECTIONS" :key="section.title" class="bg-white/5 border border-white/7 rounded-xl p-4 mb-4">
      <p class="text-white/30 text-xs font-bold tracking-widest uppercase mb-3">{{ section.title }}</p>
      <div v-for="(item, i) in section.items" :key="item.action"
        class="flex items-center gap-3 py-3 cursor-pointer"
        :class="i < section.items.length - 1 ? 'border-b border-white/5' : ''"
        @click="handleAction(item.action)"
      >
        <span class="text-xl w-8 text-center flex-shrink-0">{{ item.icon }}</span>
        <div class="flex-1 min-w-0">
          <p class="text-white font-semibold text-sm">{{ item.label }}</p>
          <p class="text-white/35 text-xs mt-0.5">{{ item.desc }}</p>
        </div>
        <!-- Toggle switch -->
        <div class="relative w-11 h-6 rounded-full flex-shrink-0 transition-colors"
          :class="toggles[item.action] ? 'bg-green-500' : 'bg-white/15'">
          <div class="absolute top-0.5 h-5 w-5 rounded-full transition-transform"
            :class="toggles[item.action] ? 'translate-x-5 bg-white' : 'translate-x-0.5 bg-white/50'">
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
