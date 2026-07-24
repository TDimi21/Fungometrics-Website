<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Modal } from '@/components/shared'
import { toast } from '@/utils/AlertPlugin'
import axios from 'axios'
import { storeToRefs } from 'pinia'
import { usePlayerStore } from '@/store/players.js'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import ModalPlayer from '@/components/dashboard/ModalPlayer.vue'
import updatedLogo from '@/assets/img/login/assteslogin/updatedlogo.webp'
import { getAuthToken } from '@/utils/authToken.js'

const props = defineProps({
  item: { type: Object, required: true },
  type: { type: String, default: 'player' }, // 'player' | 'coach'
  idTeam: { type: String, required: true },
  autoOpenMetrics: { type: Boolean, default: false },
})

const emit = defineEmits(['remove-item'])

const router = useRouter()
const token = getAuthToken()
const api_url = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || ''
const isOpenDeleteModal = ref(false)
const playerStore = usePlayerStore()
const { players } = storeToRefs(playerStore)
const { axiosGet } = useAxiosAuth()
const apiOrigin = String(api_url || '').replace(/\/api\/?$/i, '').replace(/\/$/, '')

// ── Metrics modal (players only) ─────────────────────────────────────────────
const isOpenModal    = ref(false)
const isLoadingModal = ref(false)
const dataMetric     = ref([])
const dataScore      = ref({})

const resolvedPlayerId = computed(() => {
  if (props.type !== 'player') return null
  return (
    props.item?.user_id ||
    props.item?.user?.id ||
    props.item?.player?.id ||
    props.item?.id ||
    null
  )
})

const showMetrics = async () => {
  if (props.type !== 'player') return
  const pid = resolvedPlayerId.value
  if (!pid) return
  isLoadingModal.value = true
  isOpenModal.value = true
  try {
    const [scoreRes, fitnessRes] = await Promise.all([
      axiosGet(`coach/statistics/${pid}`).catch(() => null),
      axiosGet(`player/fitness/${pid}`).catch(() => null),
    ])
    dataScore.value  = scoreRes?.data?.data  ?? {}
    const raw        = fitnessRes?.data?.data
    dataMetric.value = Array.isArray(raw) ? raw : (raw ? [raw] : [])
  } finally {
    isLoadingModal.value = false
  }
}

const hasAutoOpened = ref(false)
watch(
  () => props.autoOpenMetrics,
  async (shouldOpen) => {
    if (!shouldOpen || hasAutoOpened.value || props.type !== 'player') return
    hasAutoOpened.value = true
    await showMetrics()
  },
  { immediate: true }
)

// ── Derived display values ────────────────────────────────────────────────────
const cardEntity = computed(() => props.item?.user ?? props.item ?? {})

const avatarSrc = computed(() => (
  cardEntity.value?.avatar
  ?? cardEntity.value?.profile?.picture
  ?? props.item?.avatar
  ?? props.item?.profile?.picture
  ?? null
))

const normalizeAvatarSrc = (raw) => {
  const src = String(raw ?? '').trim()
  if (!src) return null

  const invalid = new Set(['avatar', 'null', 'undefined', 'nan', '[object object]'])
  if (invalid.has(src.toLowerCase())) return null

  if (/^https?:\/\//i.test(src)) return src
  if (src.startsWith('data:image/')) return src

  const clean = src.replace(/^\/+/, '')
  if (!clean) return null

  if (apiOrigin) {
    if (clean.startsWith('public/')) return `${apiOrigin}/${clean}`
    if (clean.startsWith('storage/')) return `${apiOrigin}/public/${clean}`
    if (clean.startsWith('players/')) return `${apiOrigin}/public/storage/${clean}`
    if (clean.startsWith('teams/')) return `${apiOrigin}/public/storage/${clean}`
  }

  return clean
}

const imageFailed = ref(false)
watch(avatarSrc, () => { imageFailed.value = false })

const resolvedAvatarSrc = computed(() => {
  if (imageFailed.value) return updatedLogo
  return normalizeAvatarSrc(avatarSrc.value) || updatedLogo
})

const fullName = computed(() => {
  const p = cardEntity.value?.profile || props.item?.profile || {}
  const first = p?.first_name || cardEntity.value?.name?.first || props.item?.name?.first || ''
  const last = p?.last_name || cardEntity.value?.name?.last || props.item?.name?.last || ''
  const full = `${first} ${last}`.trim()
  return full || cardEntity.value?.name?.full || props.item?.name?.full || cardEntity.value?.name || props.item?.name || '—'
})

const positions = computed(() => {
  const raw = cardEntity.value?.positions ?? props.item?.positions ?? []
  return Array.isArray(raw)
    ? raw.map(p => (typeof p === 'object' ? p.position : p)).filter(Boolean).join(', ') || null
    : null
})
const jersey    = computed(() => cardEntity.value?.shirt_number ?? cardEntity.value?.number_in_shirt ?? props.item?.shirt_number ?? props.item?.number_in_shirt ?? cardEntity.value?.player?.number_in_shirt ?? null)
const phone     = computed(() => cardEntity.value?.phone ?? props.item?.phone ?? null)
const email     = computed(() => cardEntity.value?.email ?? props.item?.email ?? null)
const heightFt  = computed(() => cardEntity.value?.body?.ft ?? cardEntity.value?.player?.height_in_ft ?? props.item?.body?.ft ?? null)
const heightIn  = computed(() => cardEntity.value?.body?.inch ?? cardEntity.value?.player?.height_in_inch ?? props.item?.body?.inch ?? null)

const modalItem = computed(() => {
  const p = cardEntity.value?.profile || props.item?.profile || {}
  const pl = cardEntity.value?.player || props.item?.player || {}
  return {
    ...(props.item || {}),
    id: resolvedPlayerId.value || props.item?.id || null,
    avatar: avatarSrc.value,
    email: email.value,
    phone: phone.value,
    name: {
      first: p?.first_name || '',
      last: p?.last_name || '',
      full: fullName.value,
    },
    body: {
      ...(props.item?.body || {}),
      ft: heightFt.value,
      inch: heightIn.value,
      full_height: heightFt.value != null ? `${heightFt.value}.${heightIn.value ?? 0}` : null,
    },
    shirt_number: jersey.value,
    number_in_shirt: jersey.value,
    throw_side: pl?.throw_side ?? props.item?.throw_side ?? null,
    hit_side: pl?.hit_side ?? props.item?.hit_side ?? null,
    born: props.item?.born ?? (pl?.born_date ? { date: pl.born_date } : undefined),
    positions: Array.isArray(cardEntity.value?.positions) ? cardEntity.value.positions : (props.item?.positions ?? []),
  }
})

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
    <div class="h-0.5 w-full bg-gradient-to-r from-app-red via-app-red/50 to-transparent" />

    <!-- Avatar + name block -->
    <div class="flex flex-col items-center pt-6 pb-4 px-4">
      <!-- Avatar circle -->
      <div class="relative mb-3">
        <div class="w-16 h-16 rounded-full ring-2 ring-app-red/50 overflow-hidden bg-app-navy flex items-center justify-center">
          <img
            :src="resolvedAvatarSrc"
            alt="Avatar"
            loading="lazy"
            decoding="async"
            class="w-full h-full object-cover"
            @error="imageFailed = true"
          />
        </div>
  
      </div>

      <!-- Name -->
      <h3 class="mt-2 text-white font-black text-sm text-center leading-tight line-clamp-2">
        {{ fullName }}
      </h3>

      <!-- Positions (player only) -->
      <p v-if="positions" class="mt-1 text-app-muted text-xs text-center font-bold">{{ positions }}</p>
    </div>

    <!-- Stats row -->
    <div class="flex divide-x divide-white/10 border-t border-white/10 text-center">
      <div v-if="jersey != null" class="flex-1 py-2">
        <div class="text-white font-bold text-sm">#{{ jersey }}</div>
        <div class="text-app-muted text-[10px] uppercase tracking-wide font-black">Jersey</div>
      </div>
      <div v-if="heightFt != null" class="flex-1 py-2">
        <div class="text-white font-bold text-sm">{{ heightFt }}'{{ heightIn ?? 0 }}"</div>
        <div class="text-app-muted text-[10px] uppercase tracking-wide font-black">Height</div>
      </div>
      <div v-if="phone" class="flex-1 py-2 min-w-0">
        <div class="text-white font-bold text-xs truncate px-1">{{ phone }}</div>
        <div class="text-app-muted text-[10px] uppercase tracking-wide font-black">Phone</div>
      </div>
    </div>

    <!-- Email row (if exists) -->
    <div v-if="email" class="px-4 py-2 border-t border-white/10">
      <p class="text-app-muted text-xs text-center truncate font-bold">{{ email }}</p>
    </div>

    <!-- Action buttons -->
    <div class="flex gap-2 px-4 py-3 border-t border-white/10">
      <!-- Edit — players only (have a detail page) -->
      <router-link
        v-if="type === 'player'"
        :to="{ path: `/roster/player/${resolvedPlayerId || item.id}`, params: { playerData: modalItem } }"
        @click.stop
         class="flex-1 flex items-center justify-center gap-1.5 bg-white/5 hover:bg-white/10
           text-white text-xs font-black py-2 rounded-xl border border-white/20 transition"
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
           text-app-red text-xs font-black py-2 rounded-xl border border-app-red/30 transition"
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
    :item="modalItem"
    :response="dataMetric"
    :score="dataScore"
    @closeModal="isOpenModal = false"
  />
</template>
