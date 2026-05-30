<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { Modal } from '@/components/shared'
import { toast } from '@/utils/AlertPlugin'
import axios from 'axios'
import { storeToRefs } from 'pinia'
import { usePlayerStore } from '@/store/players.js'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import ModalPlayer from '@/components/dashboard/ModalPlayer.vue'

const props = defineProps({
  item: { type: Object, required: true },
  type: { type: String, default: 'player' }, // 'player' | 'coach'
  idTeam: { type: String, required: true },
})

const emit = defineEmits(['remove-item'])

const router = useRouter()
const token = JSON.parse(localStorage.getItem('auth')).token
const api_url = process.env.API_ENDPOINT
const isOpenDeleteModal = ref(false)
const playerStore = usePlayerStore()
const { players } = storeToRefs(playerStore)
const { axiosGet } = useAxiosAuth()

// ── Metrics modal (players only) ─────────────────────────────────────────────
const isOpenModal   = ref(false)
const isLoadingModal = ref(false)
const dataMetric    = ref({})
const dataScore     = ref({})

const showMetrics = async () => {
  if (props.type !== 'player') return
  isLoadingModal.value = true
  isOpenModal.value = true
  try {
    const [scoreRes, fitnessRes] = await Promise.all([
      axiosGet(`coach/statistics/${props.item.id}`).catch(() => null),
      axiosGet(`player/fitness/${props.item.id}`).catch(() => null),
    ])
    dataScore.value   = scoreRes?.data?.data  ?? {}
    dataMetric.value  = fitnessRes?.data?.data ?? {}
  } finally {
    isLoadingModal.value = false
  }
}

// ── Derived display values ────────────────────────────────────────────────────
const avatarSrc = props.item.avatar ?? null

const fullName = (() => {
  if (props.item.profile) {
    return `${props.item.profile.first_name ?? ''} ${props.item.profile.last_name ?? ''}`.trim()
  }
  return props.item.name?.full ?? props.item.name ?? '—'
})()

const initials = fullName
  .split(' ')
  .filter(Boolean)
  .slice(0, 2)
  .map(w => w[0].toUpperCase())
  .join('')

const positions = props.item.positions?.map(p => p.position).join(', ') ?? null
const jersey    = props.item.shirt_number ?? null
const phone     = props.item.phone ?? null
const email     = props.item.email ?? null
const heightFt  = props.item.body?.ft ?? null
const heightIn  = props.item.body?.inch ?? null

// ── Delete ────────────────────────────────────────────────────────────────────
const confirmDelete = (e) => { e.stopPropagation(); isOpenDeleteModal.value = true }

const submitDelete = async () => {
  if (props.type === 'player') {
    let form = new FormData()
    form.append('player', props.item.id)
    form.append('team', props.idTeam)
    const cfg = { headers: { Authorization: `Bearer ${token}` } }
    await axios.post(api_url + 'coach/remove/players', form, cfg)
      .then(() => {
        players.value = players.value.filter(p => p.id !== props.item.id)
        toast.fire({ icon: 'success', title: 'Player removed' })
        emit('remove-item', props.item)
      })
      .catch(() => toast.fire({ icon: 'error', title: 'Could not remove player' }))
  } else {
    emit('remove-item', props.item)
  }
  isOpenDeleteModal.value = false
}
</script>

<template>
  <!-- Card -->
  <div
    @click="type === 'player' ? showMetrics() : null"
    :class="type === 'player' ? 'cursor-pointer' : ''"
    class="relative flex flex-col bg-app-card border border-white/10 rounded-2xl overflow-hidden
           transition-all duration-200 hover:border-app-red/40 hover:shadow-lg hover:shadow-app-red/10
           hover:-translate-y-0.5"
  >
    <!-- Loading overlay while fetching metrics -->
    <div v-if="isLoadingModal" class="absolute inset-0 bg-app-bg/70 flex items-center justify-center z-10 rounded-2xl">
      <svg class="animate-spin w-6 h-6 text-app-red" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
      </svg>
    </div>
    <!-- Red accent top bar -->
    <div class="h-0.5 w-full bg-gradient-to-r from-app-red via-app-blue to-transparent" />

    <!-- Avatar + name block -->
    <div class="flex flex-col items-center pt-6 pb-4 px-4">
      <!-- Avatar circle -->
      <div class="relative mb-3">
        <div class="w-16 h-16 rounded-full ring-2 ring-app-red/50 overflow-hidden bg-app-navy flex items-center justify-center">
          <img
            v-if="avatarSrc"
            :src="avatarSrc"
            alt="Avatar"
            class="w-full h-full object-cover"
          />
          <span v-else class="text-xl font-bold text-white/80 select-none">{{ initials }}</span>
        </div>
  
      </div>

      <!-- Name -->
      <h3 class="mt-2 text-white font-bold text-sm text-center leading-tight line-clamp-2">
        {{ fullName }}
      </h3>

      <!-- Positions (player only) -->
      <p v-if="positions" class="mt-1 text-app-muted text-xs text-center">{{ positions }}</p>
    </div>

    <!-- Stats row -->
    <div class="flex divide-x divide-white/10 border-t border-white/10 text-center">
      <div v-if="jersey != null" class="flex-1 py-2">
        <div class="text-white font-bold text-sm">#{{ jersey }}</div>
        <div class="text-app-muted text-[10px] uppercase tracking-wide">Jersey</div>
      </div>
      <div v-if="heightFt != null" class="flex-1 py-2">
        <div class="text-white font-bold text-sm">{{ heightFt }}'{{ heightIn ?? 0 }}"</div>
        <div class="text-app-muted text-[10px] uppercase tracking-wide">Height</div>
      </div>
      <div v-if="phone" class="flex-1 py-2 min-w-0">
        <div class="text-white font-bold text-xs truncate px-1">{{ phone }}</div>
        <div class="text-app-muted text-[10px] uppercase tracking-wide">Phone</div>
      </div>
    </div>

    <!-- Email row (if exists) -->
    <div v-if="email" class="px-4 py-2 border-t border-white/10">
      <p class="text-app-muted text-xs text-center truncate">{{ email }}</p>
    </div>

    <!-- Action buttons -->
    <div class="flex gap-2 px-4 py-3 border-t border-white/10">
      <!-- Edit — players only (have a detail page) -->
      <router-link
        v-if="type === 'player'"
        :to="{ path: `/roster/player/${item.id}`, params: { playerData: item } }"
        @click.stop
        class="flex-1 flex items-center justify-center gap-1.5 bg-app-blue/20 hover:bg-app-blue/40
               text-app-blue text-xs font-bold py-2 rounded-xl border border-app-blue/30 transition"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
        </svg>
        Edit
      </router-link>

      <!-- Remove -->
      <button
        @click.stop="confirmDelete($event)"
        class="flex-1 flex items-center justify-center gap-1.5 bg-app-red/10 hover:bg-app-red/30
               text-app-red text-xs font-bold py-2 rounded-xl border border-app-red/30 transition"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
        Remove
      </button>
    </div>
  </div>

  <!-- Delete confirm modal -->
  <Modal :modalTitle="`Remove ${type}`" :isOpen="isOpenDeleteModal">
    <template #content>
      <p class="text-sm text-gray-700 py-2">
        Are you sure you want to remove <strong>{{ fullName }}</strong>?
      </p>
    </template>
    <template #actions>
      <div class="flex justify-between items-center gap-3">
        <button
          @click="submitDelete"
          class="bg-app-red text-white px-5 py-2 rounded-xl text-sm font-bold hover:bg-app-red-hover transition"
        >
          Yes, remove
        </button>
        <button
          @click="isOpenDeleteModal = false"
          class="bg-gray-200 text-gray-700 px-5 py-2 rounded-xl text-sm font-bold hover:bg-gray-300 transition"
        >
          Cancel
        </button>
      </div>
    </template>
  </Modal>

  <!-- Metrics modal (players only) -->
  <ModalPlayer
    v-if="isOpenModal"
    :isOpen="isOpenModal"
    :item="item"
    :response="dataMetric"
    :score="dataScore"
    @closeModal="isOpenModal = false"
  />
</template>
