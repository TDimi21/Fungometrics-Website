<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useTeamStore } from '@/store/team'
import { storeToRefs } from 'pinia'
import {
  PLAN_BUCKETS, PLAN_PHASES, WORKLOAD_LEVELS, bucketTitle,
  blankPlan, blankItem, estimateMinutes, planToApi, planFromApi, groupFromApi,
} from '@/features/planner/dailyPlanner.js'

const { axiosGet, axiosPost, axiosDelete } = useAxiosAuth()
const teamStore = useTeamStore()
const { team } = storeToRefs(teamStore)
const activeTeamId = computed(() => team.value?.id_team ?? team.value?.id ?? null)

const plans = ref([])
const groups = ref([])
const teamPlayers = ref([])
const editing = ref(null)       // plan object while building, else null
const loading = ref(false)
const saving = ref(false)
const offline = ref(false)
const playerSearch = ref('')

// ── data ─────────────────────────────────────────────────────────────────────
const loadPlans = async () => {
  loading.value = true
  try {
    const res = await axiosGet('coach/daily-plans')
    const rows = res?.data?.data
    if (!Array.isArray(rows)) throw new Error('bad response')
    plans.value = rows.map(planFromApi).filter((p) => p.status !== 'template')
    offline.value = false
  } catch {
    offline.value = true
  } finally {
    loading.value = false
  }
}

const loadGroups = async () => {
  try {
    const res = await axiosGet('coach/player-groups')
    const rows = res?.data?.data
    groups.value = Array.isArray(rows) ? rows.map(groupFromApi) : []
  } catch { groups.value = [] }
}

const loadRoster = async () => {
  if (!activeTeamId.value) return
  try {
    const res = await axiosGet(`coach/teams/${activeTeamId.value}`)
    const raw = Array.isArray(res?.data?.data) ? res.data.data : []
    teamPlayers.value = raw.map((p) => ({
      id: String(p?.id ?? p?.user_id ?? ''),
      name: p?.name?.full || `${p?.name?.first || ''} ${p?.name?.last || ''}`.trim() || `Player #${p?.id}`,
    })).filter((p) => p.id)
  } catch { teamPlayers.value = [] }
}

onMounted(() => { loadPlans(); loadGroups(); loadRoster() })

// ── builder ──────────────────────────────────────────────────────────────────
const newPlan = () => { editing.value = blankPlan() }
const editPlan = (p) => { editing.value = JSON.parse(JSON.stringify(p)) }
const cancelEdit = () => { editing.value = null }

const availableBuckets = computed(() =>
  PLAN_BUCKETS.filter((b) => !(editing.value?.buckets || []).some((x) => x.type === b.type)))

const addBucket = (b) => { editing.value.buckets.push({ type: b.type, title: b.title, items: [blankItem()] }) }
const removeBucket = (type) => { editing.value.buckets = editing.value.buckets.filter((b) => b.type !== type) }
const addItem = (bucket) => { bucket.items.push(blankItem()) }
const removeItem = (bucket, id) => { bucket.items = bucket.items.filter((it) => it.id !== id) }

const itemCount = (p) => (p.buckets || []).reduce((n, b) => n + (b.items || []).length, 0)

// ── assign ───────────────────────────────────────────────────────────────────
const selected = computed(() => new Set((editing.value?.assignedPlayerIds || []).map(String)))
const togglePlayer = (id) => {
  const s = new Set(editing.value.assignedPlayerIds.map(String))
  s.has(String(id)) ? s.delete(String(id)) : s.add(String(id))
  editing.value.assignedPlayerIds = [...s]
}
const applyGroup = (g) => {
  const ids = (g.memberIds || []).filter((id) => teamPlayers.value.some((p) => p.id === String(id)))
  editing.value.assignedPlayerIds = [...new Set(ids.map(String))]
}
const assignWholeTeam = () => { editing.value.assignedPlayerIds = teamPlayers.value.map((p) => p.id) }
const clearAssign = () => { editing.value.assignedPlayerIds = [] }
const filteredPlayers = computed(() => {
  const q = playerSearch.value.trim().toLowerCase()
  return q ? teamPlayers.value.filter((p) => p.name.toLowerCase().includes(q)) : teamPlayers.value
})

// ── save / delete ────────────────────────────────────────────────────────────
const save = async (status) => {
  if (!String(editing.value.name || '').trim()) { alert('Name your plan first.'); return }
  editing.value.status = status
  if (status === 'published' && !editing.value.publishedAt) editing.value.publishedAt = new Date().toISOString()
  saving.value = true
  try {
    await axiosPost('coach/daily-plans', planToApi(editing.value, activeTeamId.value))
    await loadPlans()
    editing.value = null
  } catch {
    alert('Could not reach the server — check your connection and try again.')
  } finally {
    saving.value = false
  }
}

const del = async (p) => {
  if (!confirm(`Delete "${p.name || 'Untitled'}"?`)) return
  plans.value = plans.value.filter((x) => x.id !== p.id)
  try { await axiosDelete('coach/daily-plans/', p.id) } catch { /* server reconciles next load */ }
}

const fmtDate = (iso) => {
  if (!iso) return ''
  try { return new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' }) } catch { return iso }
}
</script>

<template>
  <div class="min-h-screen bg-[#060b14] text-white">
    <div class="w-full px-4 py-6 lg:px-8 lg:py-8 pb-28 md:pb-12">

      <!-- ══ LIST ══ -->
      <template v-if="!editing">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
          <div>
            <h1 class="text-2xl font-black tracking-wide flex items-center gap-2"><span>💪</span> Workout Plans</h1>
            <p class="text-white/40 text-sm mt-0.5">Build a player's day, assign it to players or a group, and publish it to their app.</p>
          </div>
          <button class="dp-btn dp-btn--primary" @click="newPlan">+ New Plan</button>
        </div>

        <p v-if="offline" class="dp-hint mb-4">Couldn't reach the server. Published plans and new saves need a connection.</p>

        <div v-if="loading" class="dp-empty">Loading…</div>
        <div v-else-if="plans.length === 0" class="dp-empty">
          No workout plans yet. Click <strong>New Plan</strong> to build one.
        </div>

        <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          <div v-for="p in plans" :key="p.id" class="dp-card" @click="editPlan(p)">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <div class="font-extrabold truncate">{{ p.name || 'Untitled plan' }}</div>
                <div class="text-white/40 text-xs mt-0.5">{{ fmtDate(p.date) }} · {{ p.phase || '—' }}</div>
              </div>
              <span class="dp-badge" :class="p.status === 'published' ? 'dp-badge--pub' : 'dp-badge--draft'">
                {{ p.status === 'published' ? 'Published' : 'Draft' }}
              </span>
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-3 text-xs text-white/50">
              <span>{{ p.buckets.length }} bucket{{ p.buckets.length === 1 ? '' : 's' }}</span>
              <span>{{ itemCount(p) }} item{{ itemCount(p) === 1 ? '' : 's' }}</span>
              <span>{{ estimateMinutes(p) }} min</span>
              <span>{{ p.assignedPlayerIds.length }} assigned</span>
            </div>
            <div class="mt-3 pt-3 border-t border-white/10 flex justify-end">
              <button class="dp-link dp-link--danger" @click.stop="del(p)">Delete</button>
            </div>
          </div>
        </div>
      </template>

      <!-- ══ BUILDER ══ -->
      <template v-else>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
          <button class="dp-link" @click="cancelEdit">‹ Back to plans</button>
          <div class="flex gap-2">
            <button class="dp-btn" :disabled="saving" @click="save('draft')">Save Draft</button>
            <button class="dp-btn dp-btn--primary" :disabled="saving" @click="save('published')">{{ saving ? 'Saving…' : 'Publish' }}</button>
          </div>
        </div>

        <!-- Plan info -->
        <div class="dp-panel grid gap-3 sm:grid-cols-2 mb-4">
          <label class="dp-field sm:col-span-2">
            <span class="dp-label">Plan name</span>
            <input v-model="editing.name" class="dp-input" placeholder="e.g. Tuesday Lift + Throw" />
          </label>
          <label class="dp-field"><span class="dp-label">Date</span><input v-model="editing.date" type="date" class="dp-input" /></label>
          <label class="dp-field"><span class="dp-label">Phase</span>
            <select v-model="editing.phase" class="dp-input"><option v-for="ph in PLAN_PHASES" :key="ph" :value="ph">{{ ph }}</option></select>
          </label>
          <label class="dp-field"><span class="dp-label">Workload</span>
            <select v-model="editing.workloadLevel" class="dp-input"><option v-for="w in WORKLOAD_LEVELS" :key="w" :value="w">{{ w }}</option></select>
          </label>
          <label class="dp-field"><span class="dp-label">Primary goal</span><input v-model="editing.primaryGoal" class="dp-input" placeholder="Optional" /></label>
        </div>

        <!-- Buckets -->
        <div class="dp-panel mb-4">
          <div class="dp-section">Buckets</div>
          <div v-if="availableBuckets.length" class="flex flex-wrap gap-1.5 mb-3">
            <button v-for="b in availableBuckets" :key="b.type" class="dp-chip" @click="addBucket(b)">+ {{ b.title }}</button>
          </div>

          <div v-if="editing.buckets.length === 0" class="dp-empty dp-empty--sm">Add a bucket to start building the day.</div>

          <div v-for="bucket in editing.buckets" :key="bucket.type" class="dp-bucket">
            <div class="flex items-center justify-between mb-2">
              <div class="font-bold">{{ bucketTitle(bucket.type) }}</div>
              <button class="dp-link dp-link--danger" @click="removeBucket(bucket.type)">Remove</button>
            </div>
            <div v-for="it in bucket.items" :key="it.id" class="dp-item">
              <input v-model="it.name" class="dp-input flex-1 min-w-0" placeholder="Drill / lift name" />
              <input v-model.number="it.sets" type="number" min="0" class="dp-input dp-input--num" placeholder="Sets" />
              <input v-model.number="it.reps" type="number" min="0" class="dp-input dp-input--num" placeholder="Reps" />
              <input v-model="it.note" class="dp-input flex-1 min-w-0" placeholder="Note (optional)" />
              <button class="dp-x" title="Remove item" @click="removeItem(bucket, it.id)">×</button>
            </div>
            <button class="dp-link mt-1" @click="addItem(bucket)">+ Add item</button>
          </div>
        </div>

        <!-- Assign -->
        <div class="dp-panel">
          <div class="dp-section flex items-center justify-between">
            <span>Assign to</span>
            <span class="text-white/40 text-xs font-normal normal-case">{{ editing.assignedPlayerIds.length }} selected</span>
          </div>
          <div class="flex flex-wrap gap-1.5 mb-3">
            <button class="dp-chip" @click="assignWholeTeam">Whole team</button>
            <button v-for="g in groups" :key="g.id" class="dp-chip" @click="applyGroup(g)">{{ g.name }}</button>
            <button class="dp-chip dp-chip--ghost" @click="clearAssign">Clear</button>
          </div>
          <input v-model="playerSearch" class="dp-input mb-2" placeholder="Search players…" />
          <div v-if="teamPlayers.length === 0" class="dp-empty dp-empty--sm">No roster found for this team.</div>
          <div v-else class="dp-players">
            <label v-for="p in filteredPlayers" :key="p.id" class="dp-player">
              <input type="checkbox" :checked="selected.has(p.id)" @change="togglePlayer(p.id)" />
              <span>{{ p.name }}</span>
            </label>
          </div>
        </div>
      </template>

    </div>
  </div>
</template>

<style scoped>
.dp-btn { background:#141d31; border:1px solid rgba(255,255,255,.12); color:#fff; font-weight:800; font-size:13px; padding:9px 16px; border-radius:10px; cursor:pointer; }
.dp-btn:hover { background:#1b2742; }
.dp-btn:disabled { opacity:.55; cursor:default; }
.dp-btn--primary { background:#d8232a; border-color:#d8232a; }
.dp-btn--primary:hover { background:#e5484d; }
.dp-panel { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.09); border-radius:14px; padding:16px; }
.dp-section { font-size:12px; font-weight:900; text-transform:uppercase; letter-spacing:.06em; color:#fff; margin-bottom:12px; }
.dp-field { display:flex; flex-direction:column; gap:5px; }
.dp-label { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:rgba(255,255,255,.45); font-weight:700; }
.dp-input { background:#0b1322; border:1px solid rgba(255,255,255,.12); color:#fff; border-radius:9px; padding:9px 11px; font-size:14px; outline:none; }
.dp-input:focus { border-color:#3a6df0; }
.dp-input--num { width:76px; flex:none; }
.dp-chip { background:#1b2742; border:1px solid rgba(255,255,255,.14); color:#fff; font-size:13px; font-weight:700; padding:6px 12px; border-radius:999px; cursor:pointer; }
.dp-chip:hover { background:#243357; }
.dp-chip--ghost { background:transparent; color:rgba(255,255,255,.6); }
.dp-bucket { border:1px solid rgba(255,255,255,.09); border-radius:12px; padding:12px; margin-bottom:10px; background:rgba(255,255,255,.02); }
.dp-item { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-bottom:8px; }
.dp-x { width:30px; height:30px; border-radius:8px; border:1px solid rgba(255,255,255,.12); background:transparent; color:rgba(255,255,255,.6); font-size:18px; line-height:1; cursor:pointer; flex:none; }
.dp-x:hover { color:#f0787e; border-color:#f0787e; }
.dp-link { background:none; border:none; color:#7ca6f5; font-weight:700; font-size:13px; cursor:pointer; padding:2px 0; }
.dp-link:hover { text-decoration:underline; }
.dp-link--danger { color:#f0787e; }
.dp-card { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.09); border-radius:14px; padding:16px; cursor:pointer; transition:border-color .12s, background .12s; }
.dp-card:hover { border-color:rgba(255,255,255,.22); background:rgba(255,255,255,.06); }
.dp-badge { font-size:10.5px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; padding:3px 8px; border-radius:6px; white-space:nowrap; }
.dp-badge--pub { color:#43d089; background:rgba(52,211,153,.15); }
.dp-badge--draft { color:#9aa6c0; background:rgba(148,163,184,.15); }
.dp-empty { border:1px dashed rgba(255,255,255,.14); border-radius:14px; padding:34px 20px; text-align:center; color:rgba(255,255,255,.5); font-size:14px; }
.dp-empty--sm { padding:18px; font-size:13px; }
.dp-hint { color:#f5a524; font-size:13px; }
.dp-players { max-height:320px; overflow-y:auto; display:grid; gap:2px; }
.dp-player { display:flex; align-items:center; gap:10px; padding:8px 6px; border-radius:8px; font-size:14px; cursor:pointer; }
.dp-player:hover { background:rgba(255,255,255,.05); }
.dp-player input { width:16px; height:16px; accent-color:#d8232a; }
</style>
