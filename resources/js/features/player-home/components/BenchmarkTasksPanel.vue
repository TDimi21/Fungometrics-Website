<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import DashCard from '@/features/shared/components/DashCard.vue'
import StatePanel from '@/features/shared/components/StatePanel.vue'
import { useAxiosAuth } from '@/composables/axios-auth'
import {
  asArray,
  benchmarkRefreshCompletionMessage,
  benchmarkTaskReviewNotice,
  formatTaskDate,
  humanizeTaskValue,
  taskPriorityClass,
  taskReviewStatusClass,
  taskReviewStatusLabel,
  taskStatusClass,
} from '../lib/playerHomeAdapter.js'

const { axiosGet, axiosPost } = useAxiosAuth()
const router = useRouter()

const tasks = ref([])
const loading = ref(false)
const loadError = ref('')
const actionLoading = ref('')
const message = ref('')
const actionError = ref('')
const workflow = ref(null)
const workflowTaskId = ref('')
const workflowLoading = ref('')
const completionSaving = ref('')
const formValues = ref({})
const completionNote = ref('')

const apiPayload = (response) => response?.data?.data || response?.data || {}

const activeTasks = computed(() => asArray(tasks.value).filter((task) => ['assigned', 'in_progress'].includes(task?.status)))
const completedTasks = computed(() => asArray(tasks.value).filter((task) => task?.status === 'completed'))
const taskCounts = computed(() =>
  asArray(tasks.value).reduce((counts, task) => {
    const status = task?.status || 'unknown'
    counts[status] = (counts[status] || 0) + 1
    return counts
  }, {})
)

const taskId = (task) => task?.task_id || task?.id || ''
const workflowForTask = (task) => {
  const id = taskId(task)
  return id && workflowTaskId.value === id ? workflow.value : null
}

const workflowFields = (flow) => [
  ...asArray(flow?.required_fields).map((field) => ({ ...field, required: true })),
  ...asArray(flow?.optional_fields).map((field) => ({ ...field, required: false })),
]

const fieldExistingValue = (flow, field) => {
  const key = field?.key
  const summary = flow?.existing_data_summary || {}
  if (!key) return ''
  if (summary[key] !== undefined && summary[key] !== null) return summary[key]
  if (key === 'position' && Array.isArray(summary.positions)) return summary.positions[0] || ''
  if (key === 'squat') return summary.back_squat ?? summary.front_squat ?? ''
  if (key === 'deadlift') return summary.dead_lift ?? ''
  if (key === 'pushups') return summary.push_ups ?? ''
  if (key === 'forty_yard_dash') return summary.yd_40_dash ?? ''
  if (key === 'sixty_yard_dash') return summary.yd_60_dash ?? ''
  return ''
}

const seedForm = (flow) => {
  const values = {}
  workflowFields(flow).forEach((field) => {
    if (!field?.key) return
    values[field.key] = fieldExistingValue(flow, field)
  })
  formValues.value = values
}

const closeWorkflow = () => {
  workflow.value = null
  workflowTaskId.value = ''
  formValues.value = {}
  completionNote.value = ''
}

const loadTasks = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const response = await axiosGet('player/benchmark-tasks')
    const payload = apiPayload(response)
    tasks.value = Array.isArray(payload.tasks) ? payload.tasks : []
  } catch {
    loadError.value = 'Couldn\'t load benchmark tasks.'
  } finally {
    loading.value = false
  }
}

const openTask = async (task) => {
  const id = taskId(task)
  if (!id || workflowLoading.value) return

  workflowLoading.value = id
  actionError.value = ''
  message.value = ''
  try {
    const response = await axiosGet(`player/benchmark-tasks/${id}/completion-workflow`)
    const payload = apiPayload(response)
    const flow = payload.workflow || null
    if (!flow) {
      actionError.value = 'Benchmark task workflow is not available yet.'
      return
    }

    if (flow.completion_mode === 'navigate' && flow.target_route) {
      await router.push({
        path: flow.target_route,
        query: { benchmarkTaskId: id },
      })
      return
    }

    workflow.value = flow
    workflowTaskId.value = id
    completionNote.value = ''
    seedForm(flow)
  } catch (error) {
    actionError.value = error?.response?.data?.message || 'Could not open benchmark task.'
  } finally {
    workflowLoading.value = ''
  }
}

const completeWorkflow = async (task) => {
  const id = taskId(task)
  const flow = workflowForTask(task)
  if (!id || !flow || completionSaving.value) return

  completionSaving.value = id
  actionError.value = ''
  message.value = ''
  try {
    const payload = {
      values: flow.completion_mode === 'inline_form' ? formValues.value : {},
      manual_confirm: flow.completion_mode !== 'inline_form',
      note: completionNote.value || undefined,
    }
    const response = await axiosPost(`player/benchmark-tasks/${id}/complete-with-payload`, payload)
    const completionPayload = apiPayload(response)
    const refreshMessage = benchmarkRefreshCompletionMessage(completionPayload.refresh)
    const reviewMessage = benchmarkTaskReviewNotice(completionPayload.task || completionPayload.review?.task)
    await loadTasks()
    closeWorkflow()
    const baseMessage = flow.completion_mode === 'inline_form'
      ? 'Task data saved and marked complete.'
      : 'Task marked collected.'
    message.value = reviewMessage || (refreshMessage ? `${baseMessage} ${refreshMessage}` : baseMessage)
  } catch (error) {
    const missing = error?.response?.data?.missing_fields
    actionError.value = Array.isArray(missing) && missing.length
      ? `Missing required fields: ${missing.join(', ')}.`
      : error?.response?.data?.message || error?.response?.data?.error || 'Could not complete benchmark task.'
  } finally {
    completionSaving.value = ''
  }
}

const updateTaskStatus = async (task, action) => {
  const id = taskId(task)
  if (!id || actionLoading.value) return

  actionLoading.value = `${action}:${id}`
  actionError.value = ''
  message.value = ''
  try {
    const response = await axiosPost(`player/benchmark-tasks/${id}/${action}`, {})
    const refreshMessage = action === 'complete'
      ? benchmarkRefreshCompletionMessage(apiPayload(response).refresh)
      : ''
    const reviewMessage = action === 'complete'
      ? benchmarkTaskReviewNotice(apiPayload(response).task || apiPayload(response).review?.task)
      : ''
    await loadTasks()
    const baseMessage = action === 'start'
      ? 'Task started.'
      : action === 'complete'
        ? 'Task marked complete.'
        : 'Task dismissed.'
    message.value = reviewMessage || (refreshMessage ? `${baseMessage} ${refreshMessage}` : baseMessage)
  } catch (error) {
    actionError.value = error?.response?.data?.message || `Could not ${action} task.`
  } finally {
    actionLoading.value = ''
  }
}

onMounted(loadTasks)
</script>

<template>
  <DashCard title="Benchmark Tasks" subtitle="Assigned collection tasks from your coach.">
    <template #badge>
      <span class="rounded-full border border-sky-300/30 bg-sky-500/15 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-sky-100">
        {{ activeTasks.length }} Active
      </span>
    </template>

    <div class="mb-3 grid grid-cols-3 gap-2">
      <div class="rounded-lg border border-white/10 bg-white/5 p-2">
        <p class="text-[10px] uppercase tracking-widest text-white/45">Assigned</p>
        <p class="mt-1 text-xl font-black">{{ taskCounts.assigned || 0 }}</p>
      </div>
      <div class="rounded-lg border border-white/10 bg-white/5 p-2">
        <p class="text-[10px] uppercase tracking-widest text-white/45">Started</p>
        <p class="mt-1 text-xl font-black">{{ taskCounts.in_progress || 0 }}</p>
      </div>
      <div class="rounded-lg border border-white/10 bg-white/5 p-2">
        <p class="text-[10px] uppercase tracking-widest text-white/45">Done</p>
        <p class="mt-1 text-xl font-black">{{ taskCounts.completed || 0 }}</p>
      </div>
    </div>

    <p v-if="message" class="mb-3 rounded-lg border border-emerald-300/20 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-100">
      {{ message }}
    </p>
    <p v-if="actionError" class="mb-3 rounded-lg border border-accent-2/30 bg-accent-2/10 px-3 py-2 text-xs text-red-100">
      {{ actionError }}
    </p>

    <StatePanel v-if="loading" state="loading" message="Loading benchmark tasks…" />
    <StatePanel v-else-if="loadError" state="error" :message="loadError" @retry="loadTasks" />
    <StatePanel
      v-else-if="!activeTasks.length && !completedTasks.length"
      state="empty"
      message="No benchmark tasks assigned yet."
    />

    <div v-else class="space-y-3">
      <div
        v-for="task in activeTasks"
        :key="task.task_id"
        class="rounded-xl border border-white/10 bg-white/5 p-3"
      >
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="text-sm font-black text-white">{{ task.title }}</p>
            <p class="mt-1 text-[11px] font-black uppercase tracking-wider" :class="taskPriorityClass(task.priority)">
              {{ humanizeTaskValue(task.priority, 'Priority') }} · {{ task.task_type_label || humanizeTaskValue(task.task_type, 'Benchmark Task') }}
            </p>
          </div>
          <span class="rounded-full border px-2 py-1 text-[10px] font-black uppercase tracking-wider" :class="taskStatusClass(task.status)">
            {{ humanizeTaskValue(task.status) }}
          </span>
        </div>
        <span
          v-if="task.review_status"
          class="mt-2 inline-flex rounded-full border px-2 py-1 text-[10px] font-black uppercase tracking-wider"
          :class="taskReviewStatusClass(task.review_status)"
        >
          {{ taskReviewStatusLabel(task.review_status) }}
        </span>

        <p v-if="task.description" class="mt-2 text-xs leading-5 text-white/65">{{ task.description }}</p>
        <p class="mt-2 text-[11px] uppercase tracking-wider text-white/45">
          {{ humanizeTaskValue(task.due_window, 'No due window') }} · {{ task.estimated_minutes || '—' }} min
        </p>

        <p
          v-if="benchmarkTaskReviewNotice(task)"
          class="mt-2 rounded-lg border px-3 py-2 text-xs leading-5"
          :class="taskReviewStatusClass(task.review_status)"
        >
          {{ benchmarkTaskReviewNotice(task) }}
        </p>

        <div v-if="asArray(task.instructions).length" class="mt-3 rounded-lg border border-white/10 bg-surface/60 p-3">
          <p class="text-[10px] font-black uppercase tracking-widest text-white/45">What To Do</p>
          <ul class="mt-2 space-y-1 text-xs leading-5 text-white/75">
            <li v-for="instruction in asArray(task.instructions)" :key="instruction">• {{ instruction }}</li>
          </ul>
          <p v-if="task.why" class="mt-2 text-xs leading-5 text-sky-100/85">{{ task.why }}</p>
        </div>

        <p v-if="task.coach_notes" class="mt-2 rounded-lg border border-amber-300/20 bg-amber-500/10 px-3 py-2 text-xs leading-5 text-amber-100">
          {{ task.coach_notes }}
        </p>

        <div
          v-if="workflowForTask(task)"
          class="mt-3 rounded-lg border border-sky-300/20 bg-sky-500/10 p-3"
        >
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
              <p class="text-[10px] font-black uppercase tracking-widest text-sky-100/75">Completion Workflow</p>
              <p class="mt-1 text-xs text-white/70">
                {{ humanizeTaskValue(workflowForTask(task).completion_mode) }}
                <span v-if="workflowForTask(task).target_screen">
                  · {{ workflowForTask(task).target_screen }}
                </span>
              </p>
            </div>
            <button
              type="button"
              class="rounded-lg border border-white/10 bg-white/5 px-2 py-1 text-[10px] font-black uppercase tracking-wider text-white/55"
              @click="closeWorkflow"
            >
              Close
            </button>
          </div>

          <div
            v-if="workflowForTask(task).completion_mode === 'inline_form'"
            class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2"
          >
            <label
              v-for="field in workflowFields(workflowForTask(task))"
              :key="field.key"
              class="block rounded-lg border border-white/10 bg-surface/60 p-2"
            >
              <span class="block text-[10px] font-black uppercase tracking-wider text-white/45">
                {{ field.label || humanizeTaskValue(field.key) }}
                <span v-if="field.required" class="text-accent-2">*</span>
              </span>
              <select
                v-if="field.type === 'select'"
                v-model="formValues[field.key]"
                class="mt-1 w-full rounded-md border border-white/10 bg-surface-raised px-2 py-2 text-xs text-white outline-none"
              >
                <option value="">Select</option>
                <option
                  v-for="option in asArray(field.options)"
                  :key="option"
                  :value="option"
                >
                  {{ option }}
                </option>
              </select>
              <input
                v-else
                v-model="formValues[field.key]"
                :type="field.type === 'date' ? 'date' : field.type === 'number' ? 'number' : 'text'"
                :min="field.min"
                :max="field.max"
                :step="field.step || 1"
                class="mt-1 w-full rounded-md border border-white/10 bg-surface-raised px-2 py-2 text-xs text-white outline-none"
              />
              <span v-if="field.unit" class="mt-1 block text-[10px] uppercase tracking-wider text-white/35">{{ field.unit }}</span>
            </label>
          </div>

          <div
            v-else
            class="mt-3 rounded-lg border border-white/10 bg-surface/60 p-3 text-xs leading-5 text-white/70"
          >
            <p v-if="workflowForTask(task).existing_data_found" class="text-emerald-100">
              FMTRX found existing data for this task. You can mark it collected.
            </p>
            <p v-else>
              Confirm this baseline was collected in the correct FMTRX session or outside the web flow.
            </p>
          </div>

          <label class="mt-3 block">
            <span class="block text-[10px] font-black uppercase tracking-wider text-white/45">Optional Note</span>
            <textarea
              v-model="completionNote"
              rows="2"
              class="mt-1 w-full rounded-lg border border-white/10 bg-surface/60 px-3 py-2 text-xs text-white outline-none"
              placeholder="Add a note for your coach"
            />
          </label>

          <button
            type="button"
            class="mt-3 rounded-lg border border-emerald-300/30 bg-emerald-500/15 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-emerald-100 disabled:opacity-50"
            :disabled="completionSaving === task.task_id"
            @click="completeWorkflow(task)"
          >
            {{ completionSaving === task.task_id ? 'Saving...' : (workflowForTask(task).completion_mode === 'inline_form' ? 'Save & Complete' : 'Mark Collected') }}
          </button>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
          <button
            v-if="task.status === 'assigned'"
            type="button"
            class="rounded-lg border border-sky-300/30 bg-sky-500/15 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-sky-100 disabled:opacity-50"
            :disabled="!!actionLoading"
            @click="updateTaskStatus(task, 'start')"
          >
            {{ actionLoading === `start:${task.task_id}` ? 'Starting...' : 'Start Task' }}
          </button>
          <button
            type="button"
            class="rounded-lg border border-sky-300/30 bg-sky-500/15 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-sky-100 disabled:opacity-50"
            :disabled="!!workflowLoading"
            @click="openTask(task)"
          >
            {{ workflowLoading === task.task_id ? 'Opening...' : (task.status === 'in_progress' ? 'Continue Task' : 'Open Task') }}
          </button>
          <button
            type="button"
            class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white/55 disabled:opacity-50"
            :disabled="!!actionLoading"
            @click="updateTaskStatus(task, 'dismiss')"
          >
            {{ actionLoading === `dismiss:${task.task_id}` ? 'Dismissing...' : 'Dismiss' }}
          </button>
        </div>
      </div>

      <div v-if="completedTasks.length" class="rounded-xl border border-emerald-300/20 bg-emerald-500/10 p-3">
        <p class="text-[10px] font-black uppercase tracking-widest text-emerald-100/75">Completed</p>
        <div class="mt-2 space-y-2">
          <div
            v-for="task in completedTasks.slice(0, 4)"
            :key="task.task_id"
            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-white/10 bg-surface/40 px-3 py-2 text-xs"
          >
            <span class="font-bold text-white">{{ task.title }}</span>
            <span class="flex flex-wrap items-center justify-end gap-2">
              <span
                v-if="task.review_status"
                class="rounded-full border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider"
                :class="taskReviewStatusClass(task.review_status)"
              >
                {{ taskReviewStatusLabel(task.review_status) }}
              </span>
              <span class="text-white/55">{{ formatTaskDate(task.completed_at) }}</span>
            </span>
          </div>
        </div>
      </div>
    </div>
  </DashCard>
</template>
