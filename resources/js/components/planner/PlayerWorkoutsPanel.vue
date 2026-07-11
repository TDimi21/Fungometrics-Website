<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { planFromApi, bucketTitle } from '@/features/planner/dailyPlanner.js'

const { axiosGet, axiosPost } = useAxiosAuth()

const workouts = ref([])
const current = ref(null)   // { plan, items: { [id]: { done } }, startedAt }
const loading = ref(false)
const saving = ref(false)
const offline = ref(false)

const load = async () => {
  loading.value = true
  try {
    const res = await axiosGet('player/daily-plans')
    const rows = res?.data?.data
    if (!Array.isArray(rows)) throw new Error('bad response')
    workouts.value = rows.map((r) => ({ ...planFromApi(r), progress: r.progress || null }))
    offline.value = false
  } catch {
    offline.value = true
  } finally {
    loading.value = false
  }
}
onMounted(load)

const itemCount = (w) => (w.buckets || []).reduce((n, b) => n + (b.items || []).length, 0)
const isDone = (w) => !!(w.progress && w.progress.completed_at)
const fmtDate = (iso) => {
  if (!iso) return ''
  try { return new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' }) } catch { return iso }
}

const open = (w) => {
  const items = {}
  const prog = w.progress?.items || {}
  ;(w.buckets || []).forEach((b) => (b.items || []).forEach((it) => { items[it.id] = { done: !!prog[it.id]?.done } }))
  current.value = { plan: w, items, startedAt: w.progress?.started_at || new Date().toISOString() }
}
const back = () => { current.value = null }
const toggleItem = (id) => { current.value.items[id].done = !current.value.items[id].done }

const total = computed(() => current.value ? itemCount(current.value.plan) : 0)
const done = computed(() => current.value ? Object.values(current.value.items).filter((i) => i.done).length : 0)

const finish = async () => {
  saving.value = true
  try {
    await axiosPost(`player/daily-plans/${current.value.plan.id}/progress`, {
      items: current.value.items,
      started_at: current.value.startedAt,
      completed_at: new Date().toISOString(),
    })
    await load()
    current.value = null
  } catch {
    alert('Could not save — check your connection and try again.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <!-- ══ LIST ══ -->
    <template v-if="!current">
      <div v-if="loading" class="pw-empty">Loading…</div>
      <div v-else-if="offline" class="pw-empty">Couldn't load your workouts. Check your connection.</div>
      <div v-else-if="workouts.length === 0" class="pw-empty">No workouts assigned yet. Your coach's plans show up here.</div>

      <div v-else class="grid gap-3 sm:grid-cols-2">
        <button v-for="w in workouts" :key="w.id" class="pw-card" @click="open(w)">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 text-left">
              <div class="font-extrabold text-white truncate">{{ w.name || 'Workout' }}</div>
              <div class="text-white/45 text-xs mt-0.5">{{ fmtDate(w.date) }} · {{ w.phase || '—' }}</div>
            </div>
            <span v-if="isDone(w)" class="pw-badge pw-badge--done">Completed</span>
            <span v-else class="pw-badge">{{ itemCount(w) }} items</span>
          </div>
          <div class="mt-3 flex flex-wrap gap-1.5">
            <span v-for="b in w.buckets" :key="b.type" class="pw-chip">{{ bucketTitle(b.type) }}</span>
          </div>
        </button>
      </div>
    </template>

    <!-- ══ DO A WORKOUT ══ -->
    <template v-else>
      <div class="flex items-center justify-between gap-3 mb-4">
        <button class="pw-link" @click="back">‹ All workouts</button>
        <div class="text-white/50 text-sm font-bold">{{ done }}/{{ total }} done</div>
      </div>

      <div class="mb-4">
        <div class="text-xl font-black text-white">{{ current.plan.name || 'Workout' }}</div>
        <div class="text-white/45 text-sm">{{ fmtDate(current.plan.date) }} · {{ current.plan.phase || '—' }}</div>
      </div>

      <div v-for="bucket in current.plan.buckets" :key="bucket.type" class="pw-bucket">
        <div class="pw-bucket-title">{{ bucketTitle(bucket.type) }}</div>
        <label v-for="it in bucket.items" :key="it.id" class="pw-item" :class="{ 'pw-item--done': current.items[it.id]?.done }">
          <input type="checkbox" :checked="current.items[it.id]?.done" @change="toggleItem(it.id)" />
          <span class="flex-1 min-w-0">
            <span class="pw-item-name">{{ it.name || 'Item' }}</span>
            <span v-if="it.sets || it.reps" class="pw-item-meta">{{ [it.sets ? `${it.sets} sets` : '', it.reps ? `${it.reps} reps` : ''].filter(Boolean).join(' · ') }}</span>
            <span v-if="it.note" class="pw-item-note">{{ it.note }}</span>
          </span>
        </label>
      </div>

      <div v-if="!current.plan.buckets.length" class="pw-empty">This workout has no items yet.</div>

      <button class="pw-finish" :disabled="saving" @click="finish">{{ saving ? 'Saving…' : 'Finish Workout' }}</button>
    </template>
  </div>
</template>

<style scoped>
.pw-empty { border: 1px dashed rgba(255,255,255,.14); border-radius: 16px; padding: 30px 20px; text-align: center; color: rgba(255,255,255,.5); font-size: 14px; }
.pw-card { display: block; width: 100%; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1); border-radius: 16px; padding: 16px; cursor: pointer; transition: border-color .12s, background .12s; }
.pw-card:hover { border-color: rgba(255,255,255,.24); background: rgba(255,255,255,.06); }
.pw-badge { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; padding: 3px 8px; border-radius: 6px; white-space: nowrap; color: rgba(255,255,255,.6); background: rgba(255,255,255,.08); }
.pw-badge--done { color: #43d089; background: rgba(52,211,153,.16); }
.pw-chip { font-size: 12px; font-weight: 700; color: rgba(255,255,255,.7); background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1); padding: 3px 10px; border-radius: 999px; }
.pw-link { background: none; border: none; color: #7ca6f5; font-weight: 800; font-size: 14px; cursor: pointer; }
.pw-link:hover { text-decoration: underline; }
.pw-bucket { border: 1px solid rgba(255,255,255,.1); border-radius: 14px; padding: 14px; margin-bottom: 12px; background: rgba(255,255,255,.03); }
.pw-bucket-title { font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: .06em; color: #fff; margin-bottom: 10px; }
.pw-item { display: flex; align-items: flex-start; gap: 12px; padding: 10px 6px; border-radius: 10px; cursor: pointer; }
.pw-item:hover { background: rgba(255,255,255,.04); }
.pw-item input { width: 20px; height: 20px; margin-top: 1px; accent-color: #ff2d55; flex: none; }
.pw-item-name { display: block; color: #fff; font-size: 15px; font-weight: 700; }
.pw-item--done .pw-item-name { text-decoration: line-through; color: rgba(255,255,255,.45); }
.pw-item-meta { display: block; color: rgba(255,255,255,.55); font-size: 12.5px; margin-top: 1px; }
.pw-item-note { display: block; color: rgba(255,255,255,.4); font-size: 12.5px; font-style: italic; margin-top: 1px; }
.pw-finish { width: 100%; margin-top: 8px; background: #22c55e; border: none; color: #06210f; font-weight: 900; font-size: 15px; padding: 14px; border-radius: 12px; cursor: pointer; }
.pw-finish:hover { background: #2dd46a; }
.pw-finish:disabled { opacity: .6; cursor: default; }
</style>
