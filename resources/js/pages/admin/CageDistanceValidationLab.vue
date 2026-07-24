<script setup>
import { ref, computed } from 'vue'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const { axiosPost } = useAxiosAuth()

// ── Single-point form ──────────────────────────────────────────────────────
const form = ref({
  exit_velocity_mph: 60,
  launch_angle_deg: -5,
  spray_angle_deg: 0,
  contact_height_ft: 3.0,
  mode: 'standardized',
  ball_profile: 'standardized',
  measured_spin_rpm: null,
  include_v1: true,
})

const measured = ref({
  device_measured_distance_ft: null,
  device_source: '',
  device_model: '',
  notes: '',
})

const loading = ref(false)
const error = ref('')
const result = ref(null)

const v1ErrorFt = computed(() => {
  if (!measured.value.device_measured_distance_ft || !result.value?.v1?.distance_ft) return null
  return round1(result.value.v1.distance_ft - measured.value.device_measured_distance_ft)
})
const v2ErrorFt = computed(() => {
  if (!measured.value.device_measured_distance_ft || result.value?.v2?.estimated_carry_ft == null) return null
  return round1(result.value.v2.estimated_carry_ft - measured.value.device_measured_distance_ft)
})

function round1(n) { return Math.round(n * 10) / 10 }

async function evaluate() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await axiosPost('/admin/cage-distance/validate', { ...form.value })
    result.value = data
  } catch (e) {
    error.value = e?.response?.data?.message || 'Validation request failed (endpoint may be disabled — see CAGE_DISTANCE_VALIDATION_ENABLED).'
    result.value = null
  } finally {
    loading.value = false
  }
}

// ── Table mode: LA sweep for one EV ─────────────────────────────────────────
const LAUNCH_ANGLES = [-15, -10, -5, 0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 60]
const tableEv = ref(60)
const tableSpray = ref(0)
const tableLoading = ref(false)
const tableRows = ref([])

async function loadTable() {
  tableLoading.value = true
  try {
    const responses = await Promise.all(LAUNCH_ANGLES.map((la) =>
      axiosPost('/admin/cage-distance/validate', {
        exit_velocity_mph: tableEv.value,
        launch_angle_deg: la,
        spray_angle_deg: tableSpray.value,
        include_v1: true,
      }).then((r) => r.data).catch(() => null),
    ))
    tableRows.value = LAUNCH_ANGLES.map((la, i) => ({ la, data: responses[i] })).filter((r) => r.data)
  } finally {
    tableLoading.value = false
  }
}

const peakLa = computed(() => {
  let best = null
  for (const row of tableRows.value) {
    const carry = row.data?.v2?.estimated_carry_ft
    if (carry == null) continue
    if (!best || carry > best.carry) best = { la: row.la, carry }
  }
  return best?.la ?? null
})

const chartOptions = computed(() => ({
  chart: { id: 'cage-distance-validation-chart', toolbar: { show: false } },
  xaxis: { categories: LAUNCH_ANGLES, title: { text: 'Launch angle (deg)' } },
  yaxis: { title: { text: 'Estimated carry (ft)' } },
  stroke: { width: 2, curve: 'straight' },
  markers: { size: 3 },
  legend: { position: 'top' },
  colors: ['#8888AC', '#3b82f6', '#22c55e'],
}))

const chartSeries = computed(() => {
  const v1 = tableRows.value.map((r) => r.data?.v1?.distance_ft ?? null)
  const v2 = tableRows.value.map((r) => r.data?.v2?.estimated_carry_ft ?? null)
  const series = [
    { name: 'v1 (mobile)', data: v1 },
    { name: 'v2 (backend)', data: v2 },
  ]
  if (measured.value.device_measured_distance_ft) {
    series.push({ name: 'measured', data: LAUNCH_ANGLES.map(() => measured.value.device_measured_distance_ft) })
  }
  return series
})

function statusClass(status) {
  return {
    pass: 'text-green-600',
    warning: 'text-amber-600',
    fail: 'text-red-600',
  }[status] || ''
}
</script>

<template>
  <Layout>
    <div class="p-6 max-w-6xl mx-auto">
      <h1 class="text-2xl font-fungo-700 text-fungo-darkblue mb-1">Cage Distance Validation Lab</h1>
      <p class="text-sm text-[#8888AC] mb-6">
        Developer tool — compares the mobile v1 model (src/utils/ballFlight.js) against backend v2
        (CageDistanceService) and checks v2 against physical-behavior rules. Does not affect Cage Mode
        scoring or saved production distances.
      </p>

      <!-- Single point -->
      <div class="bg-white rounded-2xl p-6 drop-shadow mb-8">
        <h2 class="font-fungo-700 text-fungo-darkblue mb-4">Single point</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
          <label class="text-sm">Exit velocity (mph)
            <input v-model.number="form.exit_velocity_mph" type="number" class="w-full border rounded px-2 py-1" />
          </label>
          <label class="text-sm">Launch angle (deg)
            <input v-model.number="form.launch_angle_deg" type="number" class="w-full border rounded px-2 py-1" />
          </label>
          <label class="text-sm">Spray angle (deg)
            <input v-model.number="form.spray_angle_deg" type="number" class="w-full border rounded px-2 py-1" />
          </label>
          <label class="text-sm">Contact height (ft)
            <input v-model.number="form.contact_height_ft" type="number" step="0.1" class="w-full border rounded px-2 py-1" />
          </label>
          <label class="text-sm">Environment mode
            <select v-model="form.mode" class="w-full border rounded px-2 py-1">
              <option value="standardized">Standardized</option>
              <option value="facility">Facility</option>
            </select>
          </label>
          <label class="text-sm">Ball profile
            <select v-model="form.ball_profile" class="w-full border rounded px-2 py-1">
              <option value="standardized">Standardized</option>
              <option value="flat_seam_pro">MLB/Pro Flat Seam</option>
              <option value="raised_seam">College/NCAA Raised Seam</option>
              <option value="high_school">High School</option>
              <option value="youth">Youth</option>
            </select>
          </label>
          <label class="text-sm">Measured spin (rpm, optional)
            <input v-model.number="form.measured_spin_rpm" type="number" class="w-full border rounded px-2 py-1" placeholder="estimated if blank" />
          </label>
          <label class="text-sm flex items-center gap-2 mt-5">
            <input v-model="form.include_v1" type="checkbox" /> Include v1 comparison
          </label>
        </div>

        <div class="border-t pt-4 mb-4">
          <p class="text-sm font-semibold text-fungo-darkblue mb-2">Optional measured distance (calibration input — not persisted)</p>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <label class="text-sm">Measured distance (ft)
              <input v-model.number="measured.device_measured_distance_ft" type="number" class="w-full border rounded px-2 py-1" />
            </label>
            <label class="text-sm">Device source
              <input v-model="measured.device_source" type="text" class="w-full border rounded px-2 py-1" placeholder="Rapsodo, TrackMan..." />
            </label>
            <label class="text-sm">Device model
              <input v-model="measured.device_model" type="text" class="w-full border rounded px-2 py-1" />
            </label>
            <label class="text-sm">Notes
              <input v-model="measured.notes" type="text" class="w-full border rounded px-2 py-1" />
            </label>
          </div>
        </div>

        <button
          class="bg-fungo-darkblue text-white rounded-lg px-4 py-2 text-sm disabled:opacity-50"
          :disabled="loading"
          @click="evaluate"
        >
          {{ loading ? 'Evaluating...' : 'Evaluate' }}
        </button>

        <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>

        <div v-if="result" class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6 text-sm">
          <div v-if="result.v1"><span class="text-[#8888AC]">v1 distance</span><br><b>{{ result.v1.distance_ft ?? '—' }} ft</b></div>
          <div><span class="text-[#8888AC]">v2 estimate</span><br><b>{{ result.v2.estimated_carry_ft ?? '—' }} ft</b></div>
          <div v-if="result.v2.carry_low_ft != null"><span class="text-[#8888AC]">v2 likely range</span><br><b>{{ result.v2.carry_low_ft }} - {{ result.v2.carry_high_ft }} ft</b></div>
          <div v-if="result.comparison.difference_ft != null"><span class="text-[#8888AC]">Difference (v2-v1)</span><br><b>{{ result.comparison.difference_ft }} ft ({{ result.comparison.difference_percent }}%)</b></div>
          <div><span class="text-[#8888AC]">Hang time</span><br><b>{{ result.v2.hang_time_seconds }} s</b></div>
          <div><span class="text-[#8888AC]">Maximum height</span><br><b>{{ result.v2.maximum_height_ft }} ft</b></div>
          <div><span class="text-[#8888AC]">Landing (x, y)</span><br><b>({{ result.v2.landing_x_ft }}, {{ result.v2.landing_y_ft }}) ft</b></div>
          <div><span class="text-[#8888AC]">Confidence</span><br><b>{{ result.v2.confidence }}</b></div>
          <div v-if="v1ErrorFt !== null"><span class="text-[#8888AC]">v1 error vs measured</span><br><b>{{ v1ErrorFt }} ft</b></div>
          <div v-if="v2ErrorFt !== null"><span class="text-[#8888AC]">v2 error vs measured</span><br><b>{{ v2ErrorFt }} ft</b></div>
          <div class="col-span-2 md:col-span-4">
            <span class="text-[#8888AC]">Validation</span><br>
            <b :class="statusClass(result.validation.status)">{{ result.validation.status.toUpperCase() }}</b>
            <span v-if="result.validation.flags.length"> — {{ result.validation.flags.join(', ') }}</span>
            <ul class="list-disc list-inside text-[#8888AC] mt-1">
              <li v-for="(explanation, i) in result.validation.explanations" :key="i">{{ explanation }}</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Table mode -->
      <div class="bg-white rounded-2xl p-6 drop-shadow">
        <h2 class="font-fungo-700 text-fungo-darkblue mb-4">Table mode — launch-angle sweep</h2>
        <div class="flex gap-4 items-end mb-4">
          <label class="text-sm">Exit velocity (mph)
            <input v-model.number="tableEv" type="number" class="border rounded px-2 py-1" />
          </label>
          <label class="text-sm">Spray angle (deg)
            <input v-model.number="tableSpray" type="number" class="border rounded px-2 py-1" />
          </label>
          <button class="bg-fungo-darkblue text-white rounded-lg px-4 py-2 text-sm disabled:opacity-50" :disabled="tableLoading" @click="loadTable">
            {{ tableLoading ? 'Loading...' : 'Load sweep' }}
          </button>
        </div>

        <apexchart v-if="tableRows.length" width="100%" type="line" height="350px" :options="chartOptions" :series="chartSeries" />

        <table v-if="tableRows.length" class="w-full text-sm mt-4">
          <thead>
            <tr class="text-left text-[#8888AC] border-b">
              <th class="py-1">LA</th>
              <th>v1</th>
              <th>v2</th>
              <th>v2 range</th>
              <th>Hang</th>
              <th>Type</th>
              <th>Flags</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in tableRows"
              :key="row.la"
              class="border-b"
              :class="{ 'bg-green-50 font-semibold': row.la === peakLa }"
            >
              <td class="py-1">{{ row.la }}°</td>
              <td>{{ row.data.v1?.distance_ft ?? '—' }}</td>
              <td>{{ row.data.v2.estimated_carry_ft ?? '—' }}</td>
              <td>{{ row.data.v2.carry_low_ft != null ? `${row.data.v2.carry_low_ft}-${row.data.v2.carry_high_ft}` : '—' }}</td>
              <td>{{ row.data.v2.hang_time_seconds }}</td>
              <td>{{ row.data.v2.batted_ball_type }}</td>
              <td :class="{ 'text-red-600': row.data.validation.flags.length }">{{ row.data.validation.flags.join(', ') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </Layout>
</template>
