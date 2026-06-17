<script setup>
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'

const router = useRouter()

const ROLES = [
  {
    role: 'Super Admin',
    description: 'Full system access. Manage all users, teams, and settings.',
    permissions: ['All Permissions'],
    color: '#E10600',
    users: 1,
  },
  {
    role: 'Coach',
    description: 'Manage their team roster, sessions, and player stats.',
    permissions: ['View/Edit Roster', 'Create Sessions', 'View Stats', 'Invite Players'],
    color: '#60A5FA',
    users: null,
  },
  {
    role: 'Player',
    description: 'View personal stats, session history, and training data.',
    permissions: ['View Own Stats', 'View Sessions', 'Update Profile'],
    color: '#4ADE80',
    users: null,
  },
]
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
      <h1 class="text-white text-xl font-bold">Role Management</h1>
    </div>

    <!-- Note -->
    <div class="bg-white/5 border border-white/10 rounded-xl p-4 mb-5 text-center">
      <p class="text-white/40 text-sm leading-relaxed">
        Role-based access control is enforced at the API level.<br>
        This view is for reference only.
      </p>
    </div>

    <!-- Role Cards -->
    <div class="space-y-4">
      <div
        v-for="r in ROLES" :key="r.role"
        class="bg-white/5 border border-white/8 rounded-xl p-5"
        :style="{ borderLeftColor: r.color, borderLeftWidth: '4px' }"
      >
        <div class="flex items-start justify-between mb-3">
          <div class="flex-1">
            <p class="font-bold text-base mb-1" :style="{ color: r.color }">{{ r.role }}</p>
            <p class="text-white/50 text-xs leading-relaxed">{{ r.description }}</p>
          </div>
          <div v-if="r.users !== null" class="rounded-xl p-2 text-center ml-4 flex-shrink-0"
            :style="{ backgroundColor: `${r.color}22` }">
            <p class="font-black text-xl" :style="{ color: r.color }">{{ r.users }}</p>
            <p class="text-xs font-semibold" :style="{ color: r.color }">users</p>
          </div>
        </div>
        <div class="flex flex-wrap gap-2 mt-3">
          <span
            v-for="p in r.permissions" :key="p"
            class="bg-white/8 text-white/65 text-xs px-3 py-1.5 rounded-lg font-medium"
          >✓ {{ p }}</span>
        </div>
      </div>
    </div>
  </Layout>
</template>
