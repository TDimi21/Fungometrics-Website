<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useUserStore } from '@/store/user'
import { useTeamStore } from '@/store/team'
import { useAxiosAuth } from '@/composables/axios-auth'
import { ARM_CARE_ROUTINES, countExercises } from '@/data/armCareRoutines'
import {
  EXERCISE_STATUS,
  computeScore,
  gradeForScore,
  buildSessionRecord,
  TONE_COLOR,
  readLocalHistory,
  writeLocalHistory,
  toApiPayload,
} from '@/utils/armCare'

const router = useRouter()
const { userData } = useUserStore()
const teamStore = useTeamStore()
const { axiosPost, axiosGet } = useAxiosAuth()

const routines = ARM_CARE_ROUTINES

// view: 'list' | 'session' | 'finished'
const view = ref('list')
const routine = ref(null)
const phaseIndex = ref(0)
const statuses = ref({})
const notes = ref({})
const expanded = ref({})
const saving = ref(false)
const history = ref([])
const startedAt = ref(0)

onMounted(async () => {
  history.value = readLocalHistory()
  // Best-effort: pull server history and merge by client_id (backend may not be deployed yet).
  try {
    const res = await axiosGet('result/armcare', { user_id: userData?.id })
    const rows = res?.data?.data
    if (Array.isArray(rows) && rows.length) {
      const seen = new Set(history.value.map((h) => h.localId))
      const mapped = rows
        .filter((r) => !seen.has(r.client_id))
        .map((r) => ({
          localId: r.client_id || `srv_${r.id}`,
          routineKey: r.routine_key,
          routineLabel: r.routine_label,
          score: r.score,
          grade: r.grade,
          assigned: r.assigned,
          completedRequired: r.completed,
          performedAt: r.performed_at,
          synced: true,
        }))
      if (mapped.length) {
        history.value = [...history.value, ...mapped].sort(
          (a, b) => new Date(b.performedAt) - new Date(a.performedAt),
        )
      }
    }
  } catch {
    /* offline / not deployed — local history is enough */
  }
})

// ── Derived ────────────────────────────────────────────────────────────────
const phase = computed(() => (routine.value ? routine.value.phases[phaseIndex.value] : null))
const totalPhases = computed(() => (routine.value ? routine.value.phases.length : 0))
const live = computed(() =>
  routine.value ? computeScore(routine.value, statuses.value) : { score: 0, assigned: 0, completedRequired: 0, completedTotal: 0, skipped: 0 },
)
const isSelectOne = computed(() => !!phase.value?.selectOne)

const avgCompliance = computed(() =>
  history.value.length
    ? Math.round(history.value.reduce((s, h) => s + (h.score || 0), 0) / history.value.length)
    : 0,
)

function isExerciseComplete(ex) {
  return ex.subItems?.length
    ? ex.subItems.every((si) => statuses.value[si.id] === EXERCISE_STATUS.COMPLETE)
    : statuses.value[ex.id] === EXERCISE_STATUS.COMPLETE
}
const phaseDone = computed(() =>
  phase.value ? phase.value.exercises.filter(isExerciseComplete).length : 0,
)
const phaseProgressLabel = computed(() =>
  isSelectOne.value
    ? `${phaseDone.value > 0 ? 1 : 0} of 1 chosen`
    : `${phaseDone.value} of ${phase.value?.exercises.length || 0} done`,
)

// ── Actions ────────────────────────────────────────────────────────────────
function startRoutine(r) {
  routine.value = r
  phaseIndex.value = 0
  statuses.value = {}
  notes.value = {}
  expanded.value = {}
  startedAt.value = Date.now()
  view.value = 'session'
}

function setStatus(id, status) {
  const current = statuses.value[id] || EXERCISE_STATUS.PENDING
  const next = current === status ? EXERCISE_STATUS.PENDING : status
  statuses.value = { ...statuses.value, [id]: next }
}

function chooseOne(exId) {
  const already = statuses.value[exId] === EXERCISE_STATUS.COMPLETE
  const next = { ...statuses.value }
  phase.value.exercises.forEach((ex) => {
    next[ex.id] = EXERCISE_STATUS.PENDING
  })
  next[exId] = already ? EXERCISE_STATUS.PENDING : EXERCISE_STATUS.COMPLETE
  statuses.value = next
}

function toggleExpand(exId) {
  expanded.value = { ...expanded.value, [exId]: !expanded.value[exId] }
}

function addNote(exId) {
  const text = window.prompt('Add a note for this drill:', notes.value[exId] || '')
  if (text !== null) notes.value = { ...notes.value, [exId]: text.trim() }
}

function isDimmed(ex) {
  return (
    isSelectOne.value &&
    phaseDone.value > 0 &&
    statuses.value[ex.id] !== EXERCISE_STATUS.COMPLETE
  )
}

function goNext() {
  if (phaseIndex.value + 1 < totalPhases.value) phaseIndex.value += 1
  else view.value = 'finished'
}
function goPrev() {
  if (phaseIndex.value > 0) phaseIndex.value -= 1
}

async function saveSession() {
  saving.value = true
  const durationSeconds = Math.round((Date.now() - startedAt.value) / 1000)
  const record = buildSessionRecord(routine.value, statuses.value, notes.value, { durationSeconds })

  // Local-first so the day-by-day list never loses a session.
  history.value = writeLocalHistory([record, ...readLocalHistory()])

  try {
    const teamId = teamStore?.team?.id_team || teamStore?.team?.id || null
    await axiosPost('result/armcare', toApiPayload(record, { userId: userData?.id, teamId }))
    record.synced = true
    history.value = writeLocalHistory(
      readLocalHistory().map((r) => (r.localId === record.localId ? { ...r, synced: true } : r)),
    )
  } catch {
    /* keep unsynced; will retry next save */
  }
  saving.value = false
  routine.value = null
  view.value = 'list'
}

function exitSession() {
  routine.value = null
  view.value = 'list'
}

// ── Formatting ─────────────────────────────────────────────────────────────
function fmtDay(iso) {
  try {
    return new Date(iso).toLocaleDateString(undefined, {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    })
  } catch {
    return ''
  }
}
function toneColor(score) {
  return TONE_COLOR[gradeForScore(score).tone]
}

function subStats(ex) {
  const total = ex.subItems.length
  const checked = ex.subItems.filter((si) => statuses.value[si.id] === EXERCISE_STATUS.COMPLETE).length
  const skipped = ex.subItems.filter((si) => statuses.value[si.id] === EXERCISE_STATUS.SKIP).length
  return { total, checked, skipped, allChecked: checked === total, allAddressed: checked + skipped === total }
}
</script>

<template>
  <Layout>
    <div class="min-h-full bg-[#060b14] text-white px-4 py-5 lg:px-6">
      <div class="mx-auto max-w-3xl space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-black tracking-wide">Arm Care</h1>
            <p class="text-sm text-white/55">Throwing prep &amp; recovery — work through it and score your reps.</p>
          </div>
          <button
            type="button"
            @click="router.push({ name: 'playerDashboard' })"
            class="rounded-lg border border-white/15 bg-white/5 px-3 py-2 text-xs font-bold uppercase tracking-widest text-white/80"
          >
            ‹ Dashboard
          </button>
        </div>

        <!-- ════════════ LIST VIEW ════════════ -->
        <template v-if="view === 'list'">
          <!-- Summary -->
          <div class="flex items-center rounded-2xl border border-white/10 bg-[#0b1230]/75 py-4">
            <div class="flex-1 text-center">
              <p class="text-2xl font-black">{{ history.length }}</p>
              <p class="text-xs font-bold text-white/55">Sessions</p>
            </div>
            <div class="h-9 w-px bg-white/10"></div>
            <div class="flex-1 text-center">
              <p class="text-2xl font-black">{{ avgCompliance }}%</p>
              <p class="text-xs font-bold text-white/55">Avg Compliance</p>
            </div>
          </div>

          <!-- Routine cards -->
          <p class="text-xs font-black uppercase tracking-widest text-white/45 pt-1">Choose a routine</p>
          <button
            v-for="r in routines"
            :key="r.key"
            type="button"
            @click="startRoutine(r)"
            class="flex w-full items-center justify-between rounded-xl border border-white/10 bg-[#0b1230]/75 p-4 text-left hover:border-[#ff2d55]/60"
          >
            <div>
              <p class="text-base font-black">{{ r.label }}</p>
              <p class="text-sm text-white/55">{{ r.subtitle }}</p>
              <div class="mt-2 flex flex-wrap gap-2">
                <span class="rounded-md bg-white/10 px-2 py-1 text-[11px] font-bold text-white/75">{{ r.phases.length }} phases</span>
                <span class="rounded-md bg-white/10 px-2 py-1 text-[11px] font-bold text-white/75">{{ countExercises(r) }} drills</span>
                <span class="rounded-md bg-white/10 px-2 py-1 text-[11px] font-bold text-white/75">~{{ r.estMinutes }} min</span>
              </div>
            </div>
            <span class="text-2xl text-white/30">›</span>
          </button>

          <!-- Running history list -->
          <template v-if="history.length">
            <p class="text-xs font-black uppercase tracking-widest text-white/45 pt-2">Arm care history</p>
            <div class="space-y-2">
              <div
                v-for="h in history"
                :key="h.localId"
                class="flex items-center rounded-xl border border-white/10 bg-[#0b1230]/75 p-3"
              >
                <div
                  class="flex min-w-[58px] items-center justify-center rounded-lg border-2 px-2 py-1.5"
                  :style="{ borderColor: toneColor(h.score), color: toneColor(h.score) }"
                >
                  <span class="text-base font-black">{{ h.score }}%</span>
                </div>
                <div class="ml-3 flex-1">
                  <p class="font-bold">{{ fmtDay(h.performedAt) }}</p>
                  <p class="text-xs text-white/55">
                    {{ h.routineLabel }} · {{ h.grade }}<span v-if="!h.synced"> · not synced</span>
                  </p>
                </div>
                <span class="ml-2 text-sm font-bold text-white/45">{{ h.completedRequired }}/{{ h.assigned }}</span>
              </div>
            </div>
          </template>
        </template>

        <!-- ════════════ SESSION VIEW ════════════ -->
        <template v-else-if="view === 'session' && phase">
          <!-- Phase header -->
          <div class="rounded-2xl border border-white/10 bg-[#0b1230]/75 p-4">
            <div class="flex items-center justify-between">
              <span class="text-xs font-black uppercase tracking-widest text-[#ff2d55]">Phase {{ phaseIndex + 1 }} of {{ totalPhases }}</span>
              <span class="text-base font-black">{{ live.score }}%</span>
            </div>
            <h2 class="mt-1 text-xl font-black">{{ phase.name }}</h2>
            <p class="text-sm text-white/55">{{ phase.goal }}</p>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/10">
              <div class="h-full bg-[#ff2d55]" :style="{ width: `${(phaseIndex / totalPhases) * 100}%` }"></div>
            </div>
            <p class="mt-2 text-xs font-bold text-white/45">{{ phaseProgressLabel }} · ~{{ phase.estMinutes }} min</p>
          </div>

          <!-- Choose-one hint -->
          <div v-if="isSelectOne" class="rounded-xl border border-[#ff2d55]/40 bg-[#ff2d55]/10 py-3 text-center">
            <span class="text-xs font-black uppercase tracking-wide text-[#ff2d55]">Choose one — pick a single way to get warm</span>
          </div>

          <!-- Exercise cards -->
          <template v-for="(ex, exIndex) in phase.exercises" :key="ex.id">
            <!-- Sub-drill checklist exercise -->
            <div
              v-if="ex.subItems?.length"
              class="overflow-hidden rounded-xl border bg-[#0b1230]/75"
              :class="subStats(ex).allChecked ? 'border-emerald-500/60' : 'border-white/10'"
            >
              <button type="button" @click="toggleExpand(ex.id)" class="flex w-full items-center p-4 text-left">
                <span
                  class="mr-3 flex h-7 w-7 items-center justify-center rounded-full border-2 text-sm font-black"
                  :class="subStats(ex).allChecked ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-white/25 text-white/75'"
                >{{ exIndex + 1 }}</span>
                <div class="flex-1">
                  <p class="font-black" :class="subStats(ex).allChecked ? 'text-white/60 line-through' : ''">{{ ex.name }}</p>
                  <p class="text-sm font-semibold text-white/65">{{ ex.prescription }}</p>
                  <p class="mt-1 text-xs font-bold text-white/40">
                    {{ subStats(ex).checked }}/{{ subStats(ex).total }} done<span v-if="subStats(ex).skipped"> · {{ subStats(ex).skipped }} skipped</span>
                  </p>
                </div>
                <span class="ml-2 text-white/45">{{ expanded[ex.id] ? '▾' : '▸' }}</span>
              </button>

              <!-- Collapsed Start button -->
              <button
                v-if="!expanded[ex.id]"
                type="button"
                @click="toggleExpand(ex.id)"
                class="w-full border-t border-white/10 py-3 text-sm font-black text-white"
                :class="subStats(ex).allAddressed ? 'bg-emerald-500' : 'bg-[#ff2d55]'"
              >
                {{ subStats(ex).allAddressed ? 'Completed ✓' : (subStats(ex).checked + subStats(ex).skipped > 0 ? 'Continue checklist' : 'Start') }}
              </button>

              <!-- Expanded sub-list -->
              <div v-else class="border-t border-white/10 px-3 py-1">
                <div
                  v-for="si in ex.subItems"
                  :key="si.id"
                  class="flex items-center border-b border-white/5 py-2 last:border-0"
                >
                  <button type="button" @click="setStatus(si.id, EXERCISE_STATUS.COMPLETE)" class="flex flex-1 items-center text-left">
                    <span
                      class="mr-3 flex h-5 w-5 items-center justify-center rounded border-2 text-xs font-black"
                      :class="statuses[si.id] === EXERCISE_STATUS.COMPLETE ? 'border-emerald-500 bg-emerald-500 text-white'
                        : statuses[si.id] === EXERCISE_STATUS.SKIP ? 'border-white/40 text-white/50' : 'border-white/25'"
                    >{{ statuses[si.id] === EXERCISE_STATUS.COMPLETE ? '✓' : statuses[si.id] === EXERCISE_STATUS.SKIP ? '–' : '' }}</span>
                    <span
                      class="text-sm font-semibold"
                      :class="statuses[si.id] === EXERCISE_STATUS.COMPLETE ? 'text-white/55 line-through'
                        : statuses[si.id] === EXERCISE_STATUS.SKIP ? 'text-white/45' : 'text-white/90'"
                    >{{ si.name }}</span>
                  </button>
                  <button
                    type="button"
                    @click="setStatus(si.id, EXERCISE_STATUS.SKIP)"
                    class="ml-2 rounded-md px-3 py-1 text-xs font-bold"
                    :class="statuses[si.id] === EXERCISE_STATUS.SKIP ? 'bg-white/20 text-white/80' : 'bg-white/10 text-white/55'"
                  >Skip</button>
                </div>
                <button type="button" @click="toggleExpand(ex.id)" class="w-full py-2.5 text-xs font-black text-emerald-400">
                  Done with {{ ex.name }}
                </button>
              </div>
            </div>

            <!-- Standard single-tap exercise -->
            <div
              v-else
              class="overflow-hidden rounded-xl border bg-[#0b1230]/75"
              :class="[
                statuses[ex.id] === EXERCISE_STATUS.COMPLETE ? 'border-emerald-500/60' : 'border-white/10',
                statuses[ex.id] === EXERCISE_STATUS.SKIP ? 'opacity-70' : '',
                isDimmed(ex) ? 'opacity-40' : '',
              ]"
            >
              <button
                type="button"
                :disabled="isDimmed(ex)"
                @click="isSelectOne ? chooseOne(ex.id) : setStatus(ex.id, EXERCISE_STATUS.COMPLETE)"
                class="flex w-full items-start p-4 text-left"
              >
                <span
                  class="mr-3 mt-0.5 flex h-6 w-6 items-center justify-center border-2 text-sm font-black"
                  :class="[
                    isSelectOne ? 'rounded-full' : 'rounded-md',
                    statuses[ex.id] === EXERCISE_STATUS.COMPLETE ? 'border-emerald-500 bg-emerald-500 text-white'
                      : statuses[ex.id] === EXERCISE_STATUS.SKIP ? 'border-white/40 text-white/50' : 'border-white/25',
                  ]"
                >{{ statuses[ex.id] === EXERCISE_STATUS.COMPLETE ? (isSelectOne ? '●' : '✓') : statuses[ex.id] === EXERCISE_STATUS.SKIP ? '–' : '' }}</span>
                <div class="flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <p class="font-black" :class="statuses[ex.id] === EXERCISE_STATUS.COMPLETE ? 'text-white/60 line-through' : ''">{{ ex.name }}</p>
                    <span v-if="!ex.required" class="rounded border border-white/25 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white/45">optional</span>
                  </div>
                  <p class="text-sm font-semibold text-white/65">{{ ex.prescription }}</p>
                  <p v-if="ex.cue" class="mt-1 text-xs italic text-white/45">{{ ex.cue }}</p>
                  <p v-if="notes[ex.id]" class="mt-1.5 text-xs text-amber-400">📝 {{ notes[ex.id] }}</p>
                </div>
              </button>
              <div class="flex border-t border-white/10">
                <button
                  v-if="!isSelectOne"
                  type="button"
                  @click="setStatus(ex.id, EXERCISE_STATUS.SKIP)"
                  class="flex-1 border-r border-white/10 py-2.5 text-sm font-bold text-white/55"
                  :class="statuses[ex.id] === EXERCISE_STATUS.SKIP ? 'bg-white/10 text-white/80' : ''"
                >Skip</button>
                <button
                  type="button"
                  :disabled="isDimmed(ex)"
                  @click="addNote(ex.id)"
                  class="flex-1 py-2.5 text-sm font-bold text-white/55"
                >{{ notes[ex.id] ? 'Edit note' : 'Add note' }}</button>
              </div>
            </div>
          </template>

          <!-- Phase nav -->
          <div class="flex gap-3 pt-1">
            <button
              type="button"
              :disabled="phaseIndex === 0"
              @click="goPrev"
              class="flex-1 rounded-xl bg-white/10 py-3.5 font-black disabled:opacity-40"
            >‹ Prev</button>
            <button
              type="button"
              @click="goNext"
              class="flex-[1.6] rounded-xl bg-[#ff2d55] py-3.5 font-black"
            >{{ phaseIndex + 1 >= totalPhases ? 'Finish ›' : 'Next Phase ›' }}</button>
          </div>
          <button type="button" @click="exitSession" class="w-full py-2 text-sm font-bold text-white/45">Exit without saving</button>
        </template>

        <!-- ════════════ FINISH VIEW ════════════ -->
        <template v-else-if="view === 'finished' && routine">
          <div class="text-center">
            <p class="pt-2 text-xs font-black uppercase tracking-widest text-[#ff2d55]">Arm Care Complete</p>
            <div
              class="mx-auto my-4 flex h-40 w-40 flex-col items-center justify-center rounded-full border-8"
              :style="{ borderColor: toneColor(live.score) }"
            >
              <span class="text-5xl font-black" :style="{ color: toneColor(live.score) }">{{ live.score }}%</span>
              <span class="text-sm font-black" :style="{ color: toneColor(live.score) }">{{ gradeForScore(live.score).label }}</span>
            </div>
            <p class="text-sm text-white/55">
              {{ live.completedRequired }}/{{ live.assigned }} required · {{ live.completedTotal }} total done · {{ live.skipped }} skipped
            </p>
          </div>

          <div class="divide-y divide-white/10 rounded-2xl border border-white/10 bg-[#0b1230]/75 px-4">
            <div v-for="p in routine.phases" :key="p.id" class="flex items-center justify-between py-3">
              <span class="font-bold">{{ p.name }}</span>
              <span class="text-sm font-bold text-white/55">
                {{ p.selectOne
                  ? `${p.exercises.filter(isExerciseComplete).length > 0 ? 1 : 0}/1`
                  : `${p.exercises.filter(isExerciseComplete).length}/${p.exercises.length}` }}
              </span>
            </div>
          </div>

          <button
            type="button"
            :disabled="saving"
            @click="saveSession"
            class="w-full rounded-xl bg-[#ff2d55] py-4 font-black disabled:opacity-60"
          >{{ saving ? 'Saving…' : 'Save Session' }}</button>
          <button type="button" :disabled="saving" @click="view = 'session'" class="w-full py-2 text-sm font-bold text-white/45">‹ Back to exercises</button>
        </template>
      </div>
    </div>
  </Layout>
</template>
