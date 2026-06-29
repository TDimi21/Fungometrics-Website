<script setup>
import { ref, computed, onMounted } from 'vue'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useTeamStore } from '@/store/team'
import { storeToRefs } from 'pinia'
import { DRILL_CATEGORIES, FOCUS_OPTIONS, GROUP_OPTIONS, DEFAULT_DRILL_LIBRARY } from '@/features/practice/drillLibrary.js'
import {
  buildTimeline, scheduledMinutesOf, allBlocksOf, minutesToTimestamp,
  buildShareText, loadSavedPractices, persistPractice, deletePractice,
} from '@/features/practice/practicePlanner.js'
import { EQUIPMENT_LIBRARY, EQUIPMENT_ALL, drillRunnable } from '@/features/practice/equipmentLibrary.js'

const { axiosGet, axiosPost, axiosDelete } = useAxiosAuth()
const teamStore = useTeamStore()
const { team } = storeToRefs(teamStore)
const activeTeamId = computed(() => team.value?.id_team ?? team.value?.id ?? null)

const CUSTOM_KEY = 'fmtrx_custom_drills'
const uid = () => `${Date.now()}_${Math.random().toString(36).slice(2, 7)}`

// ── Practice info ────────────────────────────────────────────────────────────
const title = ref('')
const date = ref(new Date().toISOString().slice(0, 10))
const focus = ref('Mixed')
const totalDuration = ref('90')
const notes = ref('')
const editingId = ref(null)

// ── Timeline ─────────────────────────────────────────────────────────────────
// slot = { id, blocks: [] }; blocks within a slot run in parallel.
const slots = ref([])

const timeline = computed(() => buildTimeline(slots.value))
const scheduledMinutes = computed(() => scheduledMinutesOf(slots.value))
const drillCount = computed(() => allBlocksOf(slots.value).length)
const plannedMinutes = computed(() => Number(totalDuration.value) || 0)
const remaining = computed(() => plannedMinutes.value - scheduledMinutes.value)
const isOver = computed(() => remaining.value < 0)

// ── Saved plans (server-synced, team-shared; local cache as offline fallback) ─
const savedPlans = ref([])
const syncing = ref(false)
const offline = ref(false)

const normalizePlan = (p) => ({
  id: p.id,
  title: p.title || '',
  date: p.date || '',
  focus: p.focus || 'Mixed',
  notes: p.notes || '',
  totalDuration: p.totalDuration ?? p.total_duration ?? '90',
  scheduledMinutes: p.scheduledMinutes ?? p.scheduled_minutes ?? 0,
  drillCount: p.drillCount ?? p.drill_count ?? 0,
  slots: Array.isArray(p.slots) ? p.slots : [],
  savedAt: p.savedAt || p.updated_at || '',
})

const refreshSaved = async () => {
  syncing.value = true
  try {
    const res = await axiosGet('coach/practice-plans')
    const rows = res?.data?.data
    if (!Array.isArray(rows)) throw new Error('bad response')
    savedPlans.value = rows.map(normalizePlan)
    offline.value = false
    try { localStorage.setItem('fmtrx_saved_practices', JSON.stringify(savedPlans.value)) } catch (_) { /* noop */ }
  } catch {
    savedPlans.value = loadSavedPractices().map(normalizePlan) // offline fallback
    offline.value = true
  } finally {
    syncing.value = false
  }
}

// ── Custom drill library ─────────────────────────────────────────────────────
const customLibrary = ref([])
const loadCustom = () => {
  try { customLibrary.value = JSON.parse(localStorage.getItem(CUSTOM_KEY) || '[]') } catch { customLibrary.value = [] }
}
const saveCustom = () => { try { localStorage.setItem(CUSTOM_KEY, JSON.stringify(customLibrary.value)) } catch { /* noop */ } }
const fullLibrary = computed(() => [...customLibrary.value, ...DEFAULT_DRILL_LIBRARY])

onMounted(() => { refreshSaved(); loadCustom(); loadEquipment() })

// ── Add drill (manual) modal ─────────────────────────────────────────────────
const showAddDrill = ref(false)
const parallelTargetSlotId = ref(null)
const drillForm = ref({ name: '', minutes: '', group: 'Full Team', focus: 'Hitting', notes: '' })

const openAddDrill = (slotId = null) => {
  parallelTargetSlotId.value = slotId
  drillForm.value = { name: '', minutes: '', group: 'Full Team', focus: 'Hitting', notes: '' }
  showAddDrill.value = true
}
const pushBlock = (block, slotId) => {
  if (slotId) {
    slots.value = slots.value.map((s) => (s.id === slotId ? { ...s, blocks: [...s.blocks, block] } : s))
  } else {
    slots.value = [...slots.value, { id: uid() + '_slot', blocks: [block] }]
  }
}
const addCustomDrill = () => {
  const min = parseInt(drillForm.value.minutes, 10)
  if (!drillForm.value.name.trim()) return
  if (!Number.isFinite(min) || min <= 0) return
  const block = {
    id: uid(),
    name: drillForm.value.name.trim(),
    minutes: min,
    group: drillForm.value.group,
    focus: drillForm.value.focus,
    drillNotes: drillForm.value.notes.trim(),
    players: [],
  }
  // remember for reuse
  customLibrary.value = [
    { id: 'custom_' + uid(), name: block.name, category: block.focus, suggestedMinutes: min, objective: block.drillNotes, isCustom: true },
    ...customLibrary.value.filter((d) => d.name.toLowerCase() !== block.name.toLowerCase()),
  ].slice(0, 60)
  saveCustom()
  pushBlock(block, parallelTargetSlotId.value)
  showAddDrill.value = false
  parallelTargetSlotId.value = null
}

// ── Library modal ────────────────────────────────────────────────────────────
const showLibrary = ref(false)
const libraryFilter = ref('All')
const librarySearch = ref('')
const onlyRunnable = ref(false)
const openLibrary = (slotId = null) => { parallelTargetSlotId.value = slotId; libraryFilter.value = 'All'; librarySearch.value = ''; showLibrary.value = true }
const filteredLibrary = computed(() => {
  const q = librarySearch.value.trim().toLowerCase()
  const avail = new Set(availableEquipment.value)
  return fullLibrary.value.filter((d) =>
    (libraryFilter.value === 'All' || d.category === libraryFilter.value) &&
    (!q || d.name.toLowerCase().includes(q) || (d.objective || '').toLowerCase().includes(q)) &&
    (!onlyRunnable.value || drillRunnable(d, avail)))
})

// ── Available equipment (what the program has) ───────────────────────────────
const EQUIP_KEY = 'fmtrx_available_equipment'
const availableEquipment = ref([...EQUIPMENT_ALL])
const showEquipment = ref(false)
const loadEquipment = () => {
  try {
    const raw = localStorage.getItem(EQUIP_KEY)
    availableEquipment.value = raw ? JSON.parse(raw) : [...EQUIPMENT_ALL]
  } catch { availableEquipment.value = [...EQUIPMENT_ALL] }
}
const saveEquipment = () => { try { localStorage.setItem(EQUIP_KEY, JSON.stringify(availableEquipment.value)) } catch (_) { /* noop */ } }
const hasEquipment = (item) => availableEquipment.value.includes(item)
const toggleEquipment = (item) => {
  const i = availableEquipment.value.indexOf(item)
  if (i >= 0) availableEquipment.value.splice(i, 1)
  else availableEquipment.value.push(item)
  saveEquipment()
}
const setAllEquipment = (on) => { availableEquipment.value = on ? [...EQUIPMENT_ALL] : []; saveEquipment() }
const addFromLibrary = (drill) => {
  pushBlock({
    id: uid(),
    name: drill.name,
    minutes: drill.suggestedMinutes,
    group: 'Full Team',
    focus: drill.category,
    drillNotes: drill.objective || '',
    players: [],
  }, parallelTargetSlotId.value)
  showLibrary.value = false
  parallelTargetSlotId.value = null
}

// ── Block operations ─────────────────────────────────────────────────────────
const removeBlock = (slotId, blockId) => {
  slots.value = slots.value
    .map((s) => (s.id === slotId ? { ...s, blocks: s.blocks.filter((b) => b.id !== blockId) } : s))
    .filter((s) => s.blocks.length > 0)
}
const moveSlot = (idx, dir) => {
  const next = [...slots.value]
  const t = idx + dir
  if (t < 0 || t >= next.length) return
  ;[next[idx], next[t]] = [next[t], next[idx]]
  slots.value = next
}
const setBlockMinutes = (blockId, val) => {
  const min = parseInt(val, 10)
  if (!Number.isFinite(min) || min <= 0) return
  slots.value = slots.value.map((s) => ({ ...s, blocks: s.blocks.map((b) => (b.id === blockId ? { ...b, minutes: min } : b)) }))
}
const setBlockField = (blockId, field, val) => {
  slots.value = slots.value.map((s) => ({ ...s, blocks: s.blocks.map((b) => (b.id === blockId ? { ...b, [field]: val } : b)) }))
}

// ── Player picker ────────────────────────────────────────────────────────────
const showPlayers = ref(false)
const playerBlockId = ref(null)
const teamPlayers = ref([])
const playersLoading = ref(false)
const playerSearch = ref('')
const loadTeamPlayers = async () => {
  if (!activeTeamId.value || teamPlayers.value.length) return
  playersLoading.value = true
  try {
    const res = await axiosGet(`coach/teams/${activeTeamId.value}`)
    const raw = Array.isArray(res?.data?.data) ? res.data.data : []
    teamPlayers.value = raw.map((p) => ({
      id: p?.id ?? p?.user_id,
      name: p?.name?.full || `${p?.name?.first || ''} ${p?.name?.last || ''}`.trim() || `Player #${p?.id}`,
    })).filter((p) => p.id)
  } catch { teamPlayers.value = [] } finally { playersLoading.value = false }
}
const openPlayers = (blockId) => { playerBlockId.value = blockId; playerSearch.value = ''; showPlayers.value = true; loadTeamPlayers() }
const blockById = (id) => allBlocksOf(slots.value).find((b) => b.id === id)
const togglePlayer = (player) => {
  slots.value = slots.value.map((s) => ({
    ...s,
    blocks: s.blocks.map((b) => {
      if (b.id !== playerBlockId.value) return b
      const has = (b.players || []).some((p) => String(p.id) === String(player.id))
      return { ...b, players: has ? b.players.filter((p) => String(p.id) !== String(player.id)) : [...(b.players || []), player] }
    }),
  }))
}
const filteredPlayers = computed(() => {
  const q = playerSearch.value.trim().toLowerCase()
  return q ? teamPlayers.value.filter((p) => p.name.toLowerCase().includes(q)) : teamPlayers.value
})

// ── Plan CRUD ────────────────────────────────────────────────────────────────
const message = ref({ type: '', text: '' })
const flash = (type, text) => { message.value = { type, text }; setTimeout(() => { message.value = { type: '', text: '' } }, 2500) }

const newPlan = () => {
  editingId.value = null
  title.value = ''
  date.value = new Date().toISOString().slice(0, 10)
  focus.value = 'Mixed'
  totalDuration.value = '90'
  notes.value = ''
  slots.value = []
}
const currentPlan = () => ({
  id: editingId.value || uid(),
  title: title.value.trim(),
  date: date.value,
  focus: focus.value,
  notes: notes.value.trim(),
  totalDuration: totalDuration.value,
  scheduledMinutes: scheduledMinutes.value,
  slots: slots.value,
  drillCount: drillCount.value,
  savedAt: new Date().toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }),
})
const savePlan = async () => {
  if (!title.value.trim()) { flash('error', 'Give your practice a name first.'); return }
  if (!drillCount.value) { flash('error', 'Add at least one drill to save.'); return }
  const rec = currentPlan()
  editingId.value = rec.id
  persistPractice(rec) // local cache immediately
  try {
    const res = await axiosPost('coach/practice-plans', {
      id: rec.id,
      team_id: activeTeamId.value || null,
      title: rec.title,
      date: rec.date,
      focus: rec.focus,
      notes: rec.notes,
      total_duration: Number(rec.totalDuration) || null,
      scheduled_minutes: rec.scheduledMinutes,
      drill_count: rec.drillCount,
      slots: rec.slots,
    })
    const saved = res?.data?.data
    if (saved?.id) editingId.value = saved.id
    await refreshSaved()
    flash('success', 'Practice plan saved & synced to your team.')
  } catch {
    await refreshSaved()
    flash('error', 'Saved locally — could not sync to the team (offline or no permission).')
  }
}
const loadPlan = (p) => {
  editingId.value = p.id
  title.value = p.title || ''
  date.value = p.date || new Date().toISOString().slice(0, 10)
  focus.value = p.focus || 'Mixed'
  totalDuration.value = String(p.totalDuration || p.scheduledMinutes || 90)
  notes.value = p.notes || ''
  slots.value = Array.isArray(p.slots) ? JSON.parse(JSON.stringify(p.slots)) : []
}
const duplicatePlan = (p) => {
  loadPlan(p)
  editingId.value = null
  title.value = `${p.title || 'Practice'} (Copy)`
}
const removePlan = async (id) => {
  deletePractice(id) // local
  try { await axiosDelete('coach/practice-plans/', id) } catch (_) { /* keep local removal */ }
  await refreshSaved()
  if (editingId.value === id) newPlan()
}

// ── Export ───────────────────────────────────────────────────────────────────
const copyPlan = async () => {
  try { await navigator.clipboard.writeText(buildShareText(currentPlan())); flash('success', 'Plan copied to clipboard.') }
  catch { flash('error', 'Could not copy. Try Print instead.') }
}
const printPlan = () => {
  const el = document.getElementById('practice-print')
  if (!el) { window.print(); return }
  const portal = document.createElement('div')
  portal.id = 'practice-print-portal'
  const head = document.createElement('div')
  head.className = 'pp-print-head'
  head.innerHTML =
    `<h1>${(title.value || 'Practice Plan')}</h1>` +
    `<p>${date.value} &middot; Focus: ${focus.value} &middot; Planned ${totalDuration.value} min &middot; Scheduled ${minutesToTimestamp(scheduledMinutes.value)} &middot; ${drillCount.value} drills</p>`
  portal.appendChild(head)
  portal.appendChild(el.cloneNode(true))
  if (notes.value.trim()) {
    const n = document.createElement('p')
    n.className = 'pp-print-notes'
    n.textContent = `Notes: ${notes.value.trim()}`
    portal.appendChild(n)
  }
  document.body.appendChild(portal)
  document.body.classList.add('practice-printing')
  const cleanup = () => { portal.remove(); document.body.classList.remove('practice-printing'); window.removeEventListener('afterprint', cleanup) }
  window.addEventListener('afterprint', cleanup)
  window.print()
  setTimeout(cleanup, 1500)
}

const groupColor = (g) => ({
  Pitchers: '#38BDF8', Hitters: '#F97316', Infield: '#65D84E', Outfield: '#A78BFA', Varsity: '#FACC15', JV: '#F472B6',
}[g] || '#94A3B8')
</script>

<template>
  <Layout>
    <div class="min-h-screen bg-[#060b14] text-white">
      <div class="w-full px-4 py-6 lg:px-8 lg:py-8 pb-28 md:pb-12">

        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5 no-print">
          <div>
            <h1 class="text-2xl font-black tracking-wide flex items-center gap-2"><span>🧠</span> Practice Planner</h1>
            <p class="text-white/40 text-sm mt-0.5">Build a practice, drop in drills, split into stations, and share it with your staff.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="pp-btn" @click="newPlan">New</button>
            <button class="pp-btn" @click="copyPlan">Copy</button>
            <button class="pp-btn" @click="printPlan">Print / PDF</button>
            <button class="pp-btn pp-btn--primary" @click="savePlan">{{ editingId ? 'Update Plan' : 'Save Plan' }}</button>
          </div>
        </div>

        <div v-if="message.text" class="mb-4 text-sm font-bold no-print" :class="message.type === 'success' ? 'text-green-300' : 'text-red-300'">{{ message.text }}</div>

        <div class="grid grid-cols-1 xl:grid-cols-[340px_1fr] gap-5">
          <!-- Left: info + saved plans -->
          <div class="flex flex-col gap-5 no-print">
            <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 p-4">
              <h2 class="pp-section">📋 Practice Info</h2>
              <label class="pp-label">Title</label>
              <input v-model="title" type="text" placeholder="e.g. Tuesday Hitting Session" class="pp-input" />
              <div class="grid grid-cols-2 gap-2 mt-3">
                <div>
                  <label class="pp-label">Date</label>
                  <input v-model="date" type="date" class="pp-input" />
                </div>
                <div>
                  <label class="pp-label">Planned (min)</label>
                  <input v-model="totalDuration" type="number" min="0" class="pp-input" />
                </div>
              </div>
              <label class="pp-label mt-3">Focus</label>
              <select v-model="focus" class="pp-input">
                <option v-for="f in FOCUS_OPTIONS" :key="f" :value="f">{{ f }}</option>
              </select>
              <label class="pp-label mt-3">Notes</label>
              <textarea v-model="notes" rows="2" class="pp-input" placeholder="Anything the staff should know…"></textarea>
            </div>

            <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 p-4">
              <h2 class="pp-section flex items-center gap-2">💾 Saved Plans
                <span v-if="syncing" class="text-[10px] text-white/40 font-bold normal-case">syncing…</span>
                <span v-else-if="offline" class="text-[10px] text-amber-300/80 font-bold normal-case">offline (local only)</span>
                <span v-else class="text-[10px] text-green-300/70 font-bold normal-case">team-synced</span>
              </h2>
              <p v-if="!savedPlans.length" class="text-white/35 text-sm">No saved plans yet.</p>
              <div v-for="p in savedPlans" :key="p.id" class="rounded-xl border border-white/10 bg-white/5 p-3 mb-2"
                :class="editingId === p.id ? 'border-[#C00000]/50' : ''">
                <div class="flex items-center justify-between gap-2">
                  <button class="text-left flex-1 min-w-0" @click="loadPlan(p)">
                    <p class="text-sm font-bold text-white truncate">{{ p.title || 'Untitled' }}</p>
                    <p class="text-[11px] text-white/40">{{ p.date }} · {{ p.drillCount }} drills · {{ minutesToTimestamp(p.scheduledMinutes) }}</p>
                  </button>
                  <div class="flex gap-1.5 shrink-0">
                    <button class="pp-mini" title="Duplicate" @click="duplicatePlan(p)">⧉</button>
                    <button class="pp-mini pp-mini--danger" title="Delete" @click="removePlan(p.id)">✕</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right: timeline -->
          <div>
            <!-- Toolbar + totals -->
            <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 p-4 mb-4 flex flex-wrap items-center gap-3 justify-between no-print">
              <div class="flex gap-2">
                <button class="pp-btn pp-btn--primary" @click="openAddDrill()">+ Add Drill</button>
                <button class="pp-btn" @click="openLibrary()">📚 Drill Library</button>
                <button class="pp-btn" @click="showEquipment = true">🧰 Equipment</button>
              </div>
              <div class="flex items-center gap-4 text-sm">
                <span class="text-white/60">⏱ <b class="text-white">{{ minutesToTimestamp(scheduledMinutes) }}</b> scheduled</span>
                <span :class="isOver ? 'text-red-300' : 'text-white/60'">
                  {{ isOver ? `⚠ ${minutesToTimestamp(Math.abs(remaining))} over` : `${minutesToTimestamp(remaining)} left` }}
                </span>
                <span class="text-white/60">🎯 <b class="text-white">{{ drillCount }}</b> drills</span>
              </div>
            </div>

            <!-- Printable plan title -->
            <div class="hidden print:block mb-3">
              <h1 class="text-xl font-black">{{ title || 'Practice Plan' }}</h1>
              <p class="text-sm">{{ date }} · Focus: {{ focus }} · Planned {{ totalDuration }} min · Scheduled {{ minutesToTimestamp(scheduledMinutes) }}</p>
            </div>

            <!-- Empty -->
            <div v-if="!slots.length" class="rounded-2xl border border-dashed border-white/15 bg-[#0a1020]/50 p-10 text-center no-print">
              <p class="text-white/40">No drills yet. Add a drill or pull one from the library to start building the timeline.</p>
            </div>

            <!-- Timeline -->
            <div class="flex flex-col gap-3" id="practice-print">
              <div v-for="(slot, idx) in timeline" :key="slot.id" class="rounded-2xl border border-white/10 bg-[#0a1020]/80 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2 bg-white/5 border-b border-white/10">
                  <div class="flex items-center gap-2">
                    <span class="text-[#38BDF8] font-black text-sm tabular-nums">{{ slot.startLabel }} → {{ slot.endLabel }}</span>
                    <span class="text-white/40 text-xs">({{ slot.duration }} min)</span>
                    <span v-if="slot.blocks.length > 1" class="text-[10px] font-black uppercase text-[#A78BFA] border border-[#A78BFA]/40 rounded-full px-2 py-0.5">⟺ {{ slot.blocks.length }} stations</span>
                  </div>
                  <div class="flex gap-1 no-print">
                    <button class="pp-mini" :disabled="idx === 0" @click="moveSlot(idx, -1)">↑</button>
                    <button class="pp-mini" :disabled="idx === timeline.length - 1" @click="moveSlot(idx, 1)">↓</button>
                    <button class="pp-mini" title="Add a parallel station" @click="openLibrary(slot.id)">+⟺</button>
                  </div>
                </div>

                <div class="p-3 grid gap-3" :class="slot.blocks.length > 1 ? 'sm:grid-cols-2' : ''">
                  <div v-for="b in slot.blocks" :key="b.id" class="rounded-xl border border-white/10 bg-white/5 p-3">
                    <div class="flex items-start justify-between gap-2">
                      <div class="min-w-0">
                        <p class="font-bold text-white text-sm">{{ b.name }}</p>
                        <p class="text-[11px] text-white/40">{{ b.focus }}</p>
                      </div>
                      <button class="pp-mini pp-mini--danger no-print shrink-0" @click="removeBlock(slot.id, b.id)">✕</button>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 mt-2">
                      <span class="text-[10px] font-black uppercase rounded px-2 py-0.5" :style="{ background: groupColor(b.group) + '22', color: groupColor(b.group) }">{{ b.group }}</span>
                      <div class="flex items-center gap-1 no-print">
                        <input type="number" min="1" :value="b.minutes" @change="setBlockMinutes(b.id, $event.target.value)" class="pp-num" />
                        <span class="text-[10px] text-white/40">min</span>
                      </div>
                      <span class="hidden print:inline text-[11px] text-white/60">{{ b.minutes }} min</span>
                    </div>

                    <div class="flex flex-wrap gap-2 mt-2 no-print">
                      <select :value="b.group" @change="setBlockField(b.id, 'group', $event.target.value)" class="pp-mini-select">
                        <option v-for="g in GROUP_OPTIONS" :key="g" :value="g">{{ g }}</option>
                      </select>
                      <button class="pp-mini-btn" @click="openPlayers(b.id)">👥 {{ (b.players || []).length || 'Players' }}</button>
                    </div>

                    <p v-if="b.drillNotes" class="text-[11px] text-white/55 mt-2 italic">{{ b.drillNotes }}</p>
                    <p v-if="(b.players || []).length" class="text-[11px] text-[#38BDF8] mt-1">{{ b.players.map(p => p.name).join(', ') }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Drill modal -->
    <Teleport to="body">
      <div v-if="showAddDrill" class="pp-modal" @click.self="showAddDrill = false">
        <div class="pp-modal-card">
          <h3 class="pp-section">+ Add Drill {{ parallelTargetSlotId ? '(parallel station)' : '' }}</h3>
          <label class="pp-label">Drill name</label>
          <input v-model="drillForm.name" class="pp-input" placeholder="e.g. Tee Work" />
          <div class="grid grid-cols-2 gap-2 mt-3">
            <div><label class="pp-label">Minutes</label><input v-model="drillForm.minutes" type="number" min="1" class="pp-input" /></div>
            <div><label class="pp-label">Focus</label>
              <select v-model="drillForm.focus" class="pp-input"><option v-for="c in DRILL_CATEGORIES" :key="c" :value="c">{{ c }}</option></select>
            </div>
          </div>
          <label class="pp-label mt-3">Group</label>
          <select v-model="drillForm.group" class="pp-input"><option v-for="g in GROUP_OPTIONS" :key="g" :value="g">{{ g }}</option></select>
          <label class="pp-label mt-3">Notes / coaching cues</label>
          <textarea v-model="drillForm.notes" rows="2" class="pp-input"></textarea>
          <div class="flex justify-end gap-2 mt-4">
            <button class="pp-btn" @click="showAddDrill = false">Cancel</button>
            <button class="pp-btn pp-btn--primary" @click="addCustomDrill">Add to Plan</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Library modal -->
    <Teleport to="body">
      <div v-if="showLibrary" class="pp-modal" @click.self="showLibrary = false">
        <div class="pp-modal-card pp-modal-card--lg">
          <div class="flex items-center justify-between gap-2">
            <h3 class="pp-section">📚 Drill Library {{ parallelTargetSlotId ? '(parallel station)' : '' }}</h3>
            <button class="pp-mini" @click="showLibrary = false">✕</button>
          </div>
          <input v-model="librarySearch" class="pp-input mt-1" placeholder="Search drills…" />
          <div class="flex flex-wrap gap-1.5 my-3">
            <button v-for="c in ['All', ...DRILL_CATEGORIES]" :key="c" class="pp-chip" :class="libraryFilter === c ? 'pp-chip--on' : ''" @click="libraryFilter = c">{{ c }}</button>
          </div>
          <label class="flex items-center gap-2 mb-3 text-xs text-white/70 cursor-pointer select-none">
            <input type="checkbox" v-model="onlyRunnable" class="accent-[#C00000]" />
            Only show drills I can run with my equipment
            <button type="button" class="ml-auto text-[10px] font-black uppercase text-[#38BDF8]" @click.prevent="showEquipment = true">Edit equipment</button>
          </label>
          <div class="overflow-y-auto pr-1" style="max-height: 48vh;">
            <button v-for="d in filteredLibrary" :key="d.id" class="w-full text-left rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 p-3 mb-2" @click="addFromLibrary(d)">
              <div class="flex items-center justify-between gap-2">
                <span class="font-bold text-white text-sm">{{ d.name }}<span v-if="d.isCustom" class="ml-1 text-[9px] text-[#38BDF8]">CUSTOM</span></span>
                <span class="text-[11px] text-white/40 shrink-0">{{ d.category }} · {{ d.suggestedMinutes }}m</span>
              </div>
              <p v-if="d.objective" class="text-[11px] text-white/50 mt-0.5">{{ d.objective }}</p>
              <div v-if="d.skill || d.difficulty || (d.equipmentTags && d.equipmentTags.length)" class="flex items-center flex-wrap gap-2 mt-1">
                <span v-if="d.skill" class="text-[9px] font-black uppercase tracking-wide text-[#38BDF8] border border-[#38BDF8]/40 rounded-full px-2 py-0.5">{{ d.skill }}</span>
                <span v-if="d.difficulty" class="text-[10px] text-amber-300">{{ '★'.repeat(d.difficulty) }}</span>
                <span v-for="t in (d.equipmentTags || [])" :key="t" class="text-[9px] text-white/55 bg-white/5 border border-white/15 rounded px-1.5 py-0.5">🧰 {{ t }}</span>
              </div>
            </button>
            <p v-if="!filteredLibrary.length" class="text-white/35 text-sm text-center py-6">No drills match.</p>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Player picker modal -->
    <Teleport to="body">
      <div v-if="showPlayers" class="pp-modal" @click.self="showPlayers = false">
        <div class="pp-modal-card">
          <div class="flex items-center justify-between gap-2">
            <h3 class="pp-section">👥 Assign Players</h3>
            <button class="pp-mini" @click="showPlayers = false">✕</button>
          </div>
          <input v-model="playerSearch" class="pp-input mt-1" placeholder="Search players…" />
          <div class="overflow-y-auto mt-3" style="max-height: 50vh;">
            <p v-if="playersLoading" class="text-white/40 text-sm">Loading roster…</p>
            <p v-else-if="!filteredPlayers.length" class="text-white/35 text-sm">No players found for this team.</p>
            <button v-for="p in filteredPlayers" :key="p.id"
              class="w-full text-left rounded-lg border border-white/10 px-3 py-2 mb-1.5 flex items-center justify-between"
              :class="(blockById(playerBlockId)?.players || []).some(x => String(x.id) === String(p.id)) ? 'bg-[#C00000]/20 border-[#C00000]/40' : 'bg-white/5'"
              @click="togglePlayer(p)">
              <span class="text-sm text-white">{{ p.name }}</span>
              <span v-if="(blockById(playerBlockId)?.players || []).some(x => String(x.id) === String(p.id))" class="text-[#65D84E] text-sm">✓</span>
            </button>
          </div>
          <div class="flex justify-end mt-3"><button class="pp-btn pp-btn--primary" @click="showPlayers = false">Done</button></div>
        </div>
      </div>
    </Teleport>

    <!-- Available Equipment modal -->
    <Teleport to="body">
      <div v-if="showEquipment" class="pp-modal" @click.self="showEquipment = false">
        <div class="pp-modal-card pp-modal-card--lg">
          <div class="flex items-center justify-between gap-2">
            <h3 class="pp-section">🧰 Available Equipment</h3>
            <button class="pp-mini" @click="showEquipment = false">✕</button>
          </div>
          <p class="text-[11px] text-white/45 mb-2">Check the gear your program has. The drill library can then show only drills you can run.</p>
          <div class="flex gap-2 mb-3">
            <button class="pp-btn" @click="setAllEquipment(true)">Select all</button>
            <button class="pp-btn" @click="setAllEquipment(false)">Clear all</button>
          </div>
          <div class="overflow-y-auto pr-1" style="max-height: 52vh;">
            <div v-for="(items, cat) in EQUIPMENT_LIBRARY" :key="cat" class="mb-3">
              <div class="text-[10px] font-black uppercase tracking-widest text-white/40 mb-1.5">{{ cat }}</div>
              <div class="flex flex-wrap gap-1.5">
                <button v-for="item in items" :key="item" type="button"
                  class="pp-chip" :class="hasEquipment(item) ? 'pp-chip--on' : ''"
                  @click="toggleEquipment(item)">
                  {{ hasEquipment(item) ? '✓ ' : '' }}{{ item }}
                </button>
              </div>
            </div>
          </div>
          <div class="flex justify-end mt-3"><button class="pp-btn pp-btn--primary" @click="showEquipment = false">Done</button></div>
        </div>
      </div>
    </Teleport>
  </Layout>
</template>

<style scoped>
.pp-section { font-size: 13px; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; color: #fff; margin-bottom: 10px; }
.pp-label { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: rgba(255,255,255,.45); margin-bottom: 4px; }
.pp-input {
  width: 100%; border-radius: 8px; border: 1px solid rgba(255,255,255,.15);
  background: rgba(255,255,255,.05); color: #fff; font-size: 13px; padding: 8px 10px; outline: none;
}
.pp-input:focus { border-color: rgba(248,113,113,.5); }
/* Override the global repeating select arrow with a single custom chevron. */
select.pp-input,
.pp-mini-select {
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
  background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M6 9l6 6 6-6'/></svg>");
  background-repeat: no-repeat;
  background-position: right 10px center;
  background-size: 14px;
}
select.pp-input { padding-right: 32px; }
.pp-mini-select { padding-right: 22px; background-position: right 5px center; background-size: 11px; }
.pp-num { width: 56px; text-align: center; border-radius: 6px; border: 1px solid rgba(255,255,255,.15); background: rgba(255,255,255,.05); color: #fff; font-size: 12px; padding: 3px 5px; outline: none; }
.pp-btn { border: 1px solid rgba(255,255,255,.2); background: rgba(255,255,255,.05); color: rgba(255,255,255,.85); border-radius: 8px; padding: 8px 14px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; cursor: pointer; }
.pp-btn--primary { background: #C00000; border-color: #C00000; color: #fff; }
.pp-btn--primary:hover { background: #a30000; }
.pp-mini { border: 1px solid rgba(255,255,255,.18); background: rgba(255,255,255,.05); color: rgba(255,255,255,.8); border-radius: 6px; width: 26px; height: 26px; font-size: 12px; cursor: pointer; }
.pp-mini:disabled { opacity: .35; }
.pp-mini--danger { color: #F87171; border-color: rgba(248,113,113,.4); }
.pp-mini-select { border-radius: 6px; border: 1px solid rgba(255,255,255,.15); background: rgba(255,255,255,.05); color: #fff; font-size: 11px; padding: 3px 6px; outline: none; }
.pp-mini-btn { border-radius: 6px; border: 1px solid rgba(255,255,255,.15); background: rgba(255,255,255,.05); color: rgba(255,255,255,.8); font-size: 11px; padding: 3px 8px; cursor: pointer; }
.pp-chip { border-radius: 999px; border: 1px solid rgba(255,255,255,.15); background: rgba(255,255,255,.05); color: rgba(255,255,255,.6); font-size: 11px; font-weight: 700; padding: 4px 10px; cursor: pointer; }
.pp-chip--on { background: rgba(8,155,255,.18); border-color: rgba(8,155,255,.5); color: #38BDF8; }
.pp-modal { position: fixed; inset: 0; z-index: 60; background: rgba(2,8,20,.82); display: flex; align-items: flex-start; justify-content: center; padding: 24px 16px; overflow-y: auto; }
.pp-modal-card { width: 100%; max-width: 460px; border-radius: 16px; border: 1px solid rgba(255,255,255,.15); background: #0a1020; padding: 18px; }
.pp-modal-card--lg { max-width: 640px; }
</style>

<style>
@media print {
  @page { size: A4 portrait; margin: 10mm; }
  body.practice-printing { background: #fff !important; }
  /* Show only the cloned plan portal. */
  body.practice-printing > *:not(#practice-print-portal) { display: none !important; }
  #practice-print-portal { display: block !important; color: #fff; }
  #practice-print-portal,
  #practice-print-portal * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
  #practice-print-portal .no-print { display: none !important; }
  #practice-print-portal .pp-print-head h1 { font-size: 20px; font-weight: 900; color: #0a1020; }
  #practice-print-portal .pp-print-head p { font-size: 12px; color: #475569; margin-bottom: 12px; }
  #practice-print-portal .pp-print-notes { margin-top: 12px; font-size: 12px; color: #334155; }
}
</style>
