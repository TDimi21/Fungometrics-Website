<script setup>
import { ref, reactive, computed } from 'vue'
import { SearchIcon, ArrowDownIcon } from '@/components/icons'
import {toast} from "../../utils/AlertPlugin";
import useChartOptions from '@/composables/useChartOptions.js'
import Loader from "@/components/Loader.vue";

const { dinamicChartOptionsFitness } = useChartOptions()

const props = defineProps({
  item: {
    type: Object,
    required: true
  },
  response: {
    type: [Array, Object],
    required: true,
    default: () => []
  },
})

const tableData = ref([])
const isLoading = reactive({status: true})
const showChart = ref(false)
const series = [{
  name: "",
  data: []
}]

const categoriesMonths = []
const normalizedResponse = computed(() => {
  if (Array.isArray(props.response)) return props.response
  if (Array.isArray(props.response?.data)) return props.response.data
  return []
})

const getOrderArray = () => normalizedResponse.value
  .slice()
  .sort((a, b) => new Date(b.fitness_date).getTime() - new Date(a.fitness_date).getTime())

// ── Interactive metric chart ─────────────────────────────────────────────────
const METRICS = [
  { key: 'body_weight',    label: 'Weight',      unit: 'lb',   lowerBetter: true,  color: '#C00000' },
  { key: 'bench_press',   label: 'Bench Press', unit: 'lb',   lowerBetter: false, color: '#3b82f6' },
  { key: 'dead_lift',     label: 'Deadlift',    unit: 'lb',   lowerBetter: false, color: '#a855f7' },
  { key: 'front_squat',   label: 'Front Squat', unit: 'lb',   lowerBetter: false, color: '#f59e0b' },
  { key: 'back_squat',    label: 'Back Squat',  unit: 'lb',   lowerBetter: false, color: '#10b981' },
  { key: 'power_clean',   label: 'Power Clean', unit: 'lb',   lowerBetter: false, color: '#06b6d4' },
  { key: 'yd_40_dash',    label: '40 Time',     unit: 's',    lowerBetter: true,  color: '#f97316' },
  { key: 'yd_60_dash',    label: '60 Time',     unit: 's',    lowerBetter: true,  color: '#ec4899' },
  { key: 'sleep_hours',   label: 'Sleep Hrs',   unit: 'hrs',  lowerBetter: false, color: '#8b5cf6' },
  { key: 'recovery_score',label: 'Recovery',    unit: '/100', lowerBetter: false, color: '#22d3ee' },
  { key: 'mobility_score',label: 'Mobility',    unit: '/100', lowerBetter: false, color: '#4ade80' },
]

const DATE_RANGES = [
  { label: 'All Time', months: 0  },
  { label: '1 Mo',     months: 1  },
  { label: '3 Mo',     months: 3  },
  { label: '6 Mo',     months: 6  },
  { label: '1 Year',   months: 12 },
]

// Set of active metric keys (multi-select)
const activeMetricKeys = ref(new Set(['body_weight']))
const activeDateRange  = ref(0)

const toggleMetric = (key) => {
  const next = new Set(activeMetricKeys.value)
  if (next.has(key)) {
    if (next.size > 1) next.delete(key) // always keep at least 1
  } else {
    next.add(key)
  }
  activeMetricKeys.value = next
}

const activeMetrics = computed(() => METRICS.filter(m => activeMetricKeys.value.has(m.key)))

// Cutoff date for the selected range
const cutoffDate = computed(() => {
  if (activeDateRange.value === 0) return null
  const d = new Date()
  d.setMonth(d.getMonth() - activeDateRange.value)
  return d
})

// For multi-metric we build a unified X-axis (all unique dates across selected metrics)
const allRecordsInRange = computed(() => {
  let records = normalizedResponse.value
    .filter(r => r.fitness_date)
    .slice()
    .sort((a, b) => new Date(a.fitness_date) - new Date(b.fitness_date))
  if (cutoffDate.value) records = records.filter(r => new Date(r.fitness_date) >= cutoffDate.value)
  return records
})

// Unique sorted date labels across all selected metrics
const unifiedDates = computed(() => {
  const dateSet = new Set()
  allRecordsInRange.value.forEach(r => {
    activeMetrics.value.forEach(m => {
      if (r[m.key] != null && parseFloat(r[m.key]) > 0) {
        dateSet.add(r.fitness_date)
      }
    })
  })
  return [...dateSet].sort((a, b) => new Date(a) - new Date(b))
})

const unifiedDateLabels = computed(() =>
  unifiedDates.value.map(d =>
    new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: '2-digit' })
  )
)

// Build one series per active metric, aligned to unifiedDates (null for missing points)
const chartSeries = computed(() =>
  activeMetrics.value.map(m => {
    // build a map date -> value for this metric
    const map = {}
    allRecordsInRange.value.forEach(r => {
      const v = parseFloat(r[m.key])
      if (v > 0) map[r.fitness_date] = v
    })
    return {
      name: `${m.label} (${m.unit})`,
      data: unifiedDates.value.map(d => map[d] ?? null),
    }
  })
)

// Per-metric stats (current, change, low, high)
const metricStats = computed(() =>
  activeMetrics.value.map(m => {
    const vals = allRecordsInRange.value
      .filter(r => r[m.key] != null && parseFloat(r[m.key]) > 0)
      .map(r => parseFloat(r[m.key]))
    if (!vals.length) return { ...m, current: null, change: null, low: null, high: null }
    const current = vals[vals.length - 1]
    const change  = vals.length > 1 ? +(current - vals[0]).toFixed(2) : null
    const good    = change === null ? null : (m.lowerBetter ? change <= 0 : change >= 0)
    return { ...m, current, change, good, low: Math.min(...vals), high: Math.max(...vals), count: vals.length }
  })
)

const hasAnyData = computed(() => chartSeries.value.some(s => s.data.some(v => v !== null)))

const chartColors = computed(() => activeMetrics.value.map(m => m.color))

const chartOptions = computed(() => ({
  chart: {
    type: 'line',
    height: 280,
    background: 'transparent',
    toolbar: { show: false },
    zoom: { enabled: false },
    animations: { enabled: true, speed: 350 },
  },
  stroke: { curve: 'monotoneCubic', width: activeMetrics.value.length > 1 ? 2.5 : 3 },
  connectNulls: true,
  colors: chartColors.value,
  fill: {
    type: activeMetrics.value.length === 1 ? 'gradient' : 'solid',
    opacity: activeMetrics.value.length === 1 ? 1 : 0,
    gradient: {
      shade: 'dark', type: 'vertical',
      opacityFrom: 0.15, opacityTo: 0.01,
    },
  },
  markers: {
    size: 5,
    strokeColors: '#0b1120',
    strokeWidth: 2,
    hover: { size: 7 },
  },
  dataLabels: { enabled: false },
  legend: {
    show: activeMetrics.value.length > 1,
    labels: { colors: 'rgba(255,255,255,0.65)' },
    markers: { width: 10, height: 10, radius: 9999 },
  },
  grid: {
    borderColor: 'rgba(255,255,255,0.07)',
    row: { colors: ['transparent'], opacity: 1 },
  },
  xaxis: {
    categories: unifiedDateLabels.value,
    labels: {
      style: { colors: 'rgba(255,255,255,0.45)', fontSize: '10px', fontWeight: 600 },
      rotate: -35,
    },
    axisBorder: { color: 'rgba(255,255,255,0.08)' },
    axisTicks:  { color: 'rgba(255,255,255,0.08)' },
  },
  yaxis: activeMetrics.value.length === 1 ? {
    min: (min) => Math.max(0, parseFloat((min * 0.94).toFixed(1))),
    labels: {
      style: { colors: 'rgba(255,255,255,0.45)', fontSize: '10px', fontWeight: 600 },
      formatter: (v) => v != null ? `${v} ${activeMetrics.value[0].unit}` : '',
    },
  } : {
    labels: {
      style: { colors: 'rgba(255,255,255,0.45)', fontSize: '10px', fontWeight: 600 },
      formatter: (v) => v != null ? String(v) : '',
    },
  },
  tooltip: {
    theme: 'dark',
    shared: true,
    intersect: false,
  },
  theme: { mode: 'dark' },
}))


const entriesModelYAxis= ref('')
const entriesOptionYAxis = ref([
  {label: 'Bench Press', key: '1', title: 'Bench Press'},
  {label: 'Dead Lift', key: '2', title: 'Dead Lift'},
  {label: '40 Time', key: '3', title: '40 Time'},
  {label: '60 Time', key: '4', title: '60 Time'},
  {label: 'Front Squat', key: '5', title: 'Front Squat'},
  {label: 'Back Squat', key: '6', title: 'Back Squat'},
  {label: 'Power Clean', key: '7', title: 'Power Clean'},
  {label: 'Body Weight', key: '8', title: 'Body Weight'},
  {label: 'FMTRX Strength Score', key: '9', title: 'FMTRX Strength Score'},
])

const entriesModelXAxis= ref('')
const entriesOptionXAxis = ref([
  {label: 'All Time', key: '0'},
  {label: '3 Month', key: '3'},
  {label: '6 Month', key: '6'},
])

const searchChart = async () => {
  isLoading.status =!isLoading.status;
  showChart.value = false
  categoriesMonths.length = 0
  series[0].data = []
  const orderArray = getOrderArray()

  if (orderArray.length !== 0) {
    if(entriesModelYAxis.value == '' || entriesModelXAxis.value == ''){
      toast.fire({
        icon: 'warning',
        title: 'Validation !!!',
        text: 'You must complete all the fields',
      })
      isLoading.status =!isLoading.status;
    }else{
      switch (entriesModelYAxis.value.key) {
        case "1":
          if(entriesModelXAxis.value.key != 0){
            const newDate = dateSearch();
            orderArray.forEach((item)=> {
              let date = new Date(item.fitness_date);
              if (item.bench_press > 0) {
                if(newDate.getTime() < date.getTime()){
                  // if (!dayArray.includes(date.getTime())) {
                  //   dayArray.push(date.getTime());
                  //   categoriesMonths.push(date.toLocaleDateString())
                  //   series[0].data.push(item.bench_press == null || item.bench_press == undefined ? 0 : item.bench_press)
                  // }
                  // dayArray.push(date.getTime());
                  categoriesMonths.push(date.toLocaleDateString())
                  series[0].data.push(item.bench_press == null || item.bench_press == undefined ? 0 : item.bench_press)
                }
              }
            })
          }else{
            orderArray.forEach((item)=> {
              let date = new Date(item.fitness_date);
              if (item.bench_press > 0) {
                // if (!dayArray.includes(date.getTime())) {
                //   dayArray.push(date.getTime());
                //   categoriesMonths.push(date.toLocaleDateString())
                //   series[0].data.push(item.bench_press == null || item.bench_press == undefined ? 0 : item.bench_press)
                // }
                categoriesMonths.push(date.toLocaleDateString())
                series[0].data.push(item.bench_press == null || item.bench_press == undefined ? 0 : item.bench_press)
              }
            })
          }
          categoriesMonths.reverse()
          series[0].data.reverse()
          break;
        case "2":
          if(entriesModelXAxis.value.key != 0){
            const newDate = dateSearch();
            orderArray.forEach((item)=> {
              let date = new Date(item.fitness_date);
              if (item.dead_lift > 0) {
                if(newDate.getTime() < date.getTime()){
                  // if (!dayArray.includes(date.getTime())) {
                  //   dayArray.push(date.getTime());
                  //   categoriesMonths.push(date.toLocaleDateString())
                  //   series[0].data.push(item.dead_lift == null || item.dead_lift == undefined ? 0 : item.dead_lift)
                  // }
                  // dayArray.push(date.getTime());
                  categoriesMonths.push(date.toLocaleDateString())
                  series[0].data.push(item.dead_lift == null || item.dead_lift == undefined ? 0 : item.dead_lift)
                }
              }
            })
          }else{
            orderArray.forEach((item)=> {
              let date = new Date(item.fitness_date);
              if (item.dead_lift > 0) {
                // if (!dayArray.includes(date.getTime())) {
                //   dayArray.push(date.getTime());
                //   categoriesMonths.push(date.toLocaleDateString())
                //   series[0].data.push(item.dead_lift == null || item.dead_lift == undefined ? 0 : item.dead_lift)
                // }
                categoriesMonths.push(date.toLocaleDateString())
                series[0].data.push(item.dead_lift == null || item.dead_lift == undefined ? 0 : item.dead_lift)
              }
            })
          }
          categoriesMonths.reverse()
          series[0].data.reverse()
          break;
        case "3":
          if(entriesModelXAxis.value.key != 0){
            const newDate = dateSearch();
            orderArray.forEach((item)=> {
              let date = new Date(item.fitness_date);
              if (item.yd_40_dash > 0) {
                if(newDate.getTime() < date.getTime()){
                  // if (!dayArray.includes(date.getTime())) {
                  //   dayArray.push(date.getTime());
                  //   categoriesMonths.push(date.toLocaleDateString())
                  //   series[0].data.push(item.yd_40_dash == null || item.yd_40_dash == undefined ? 0 : item.yd_40_dash)
                  // }
                  // dayArray.push(date.getTime());
                  categoriesMonths.push(date.toLocaleDateString())
                  series[0].data.push(item.yd_40_dash == null || item.yd_40_dash == undefined ? 0 : item.yd_40_dash)
                }
              }
            })
          }else{
            orderArray.forEach((item)=> {
              let date = new Date(item.fitness_date);
              if (item.yd_40_dash > 0) {
                // if (!dayArray.includes(date.getTime())) {
                //   dayArray.push(date.getTime());
                //   categoriesMonths.push(date.toLocaleDateString())
                //   series[0].data.push(item.yd_40_dash == null || item.yd_40_dash == undefined ? 0 : item.yd_40_dash)
                // }
                categoriesMonths.push(date.toLocaleDateString())
                series[0].data.push(item.yd_40_dash == null || item.yd_40_dash == undefined ? 0 : item.yd_40_dash)
              }
            })
          }
          categoriesMonths.reverse()
          series[0].data.reverse()
          break;
        case "4":
          if(entriesModelXAxis.value.key != 0){
            const newDate = dateSearch();
            orderArray.forEach((item)=> {
              let date = new Date(item.fitness_date);
              if (item.yd_60_dash > 0) {
                if(newDate.getTime() < date.getTime()){
                  categoriesMonths.push(date.toLocaleDateString())
                  series[0].data.push(item.yd_60_dash == null || item.yd_60_dash == undefined ? 0 : item.yd_60_dash)
                }
              }
            })
          }else{
            orderArray.forEach((item)=> {
              let date = new Date(item.fitness_date);
              if (item.yd_60_dash > 0) {
                categoriesMonths.push(date.toLocaleDateString())
                series[0].data.push(item.yd_60_dash == null || item.yd_60_dash == undefined ? 0 : item.yd_60_dash)
              }
            })
          }
          categoriesMonths.reverse()
          series[0].data.reverse()
          break;
        case "5":
          if(entriesModelXAxis.value.key != 0){
            const newDate = dateSearch();
            orderArray.forEach((item)=> {
              let date = new Date(item.fitness_date);
              if (item.front_squat > 0) {
                if(newDate.getTime() < date.getTime()){
                  categoriesMonths.push(date.toLocaleDateString())
                  series[0].data.push(item.front_squat == null || item.front_squat == undefined ? 0 : item.front_squat)
                }
              }
            })
          }else{
            orderArray.forEach((item)=> {
              let date = new Date(item.fitness_date);
              if (item.front_squat > 0) {
                categoriesMonths.push(date.toLocaleDateString())
                series[0].data.push(item.front_squat == null || item.front_squat == undefined ? 0 : item.front_squat)
              }
            })
          }
          categoriesMonths.reverse()
          series[0].data.reverse()
          break;
        case "6":
          if(entriesModelXAxis.value.key != 0){
            const newDate = dateSearch();
            orderArray.forEach((item)=> {
              let date = new Date(item.fitness_date);
              if (item.back_squat > 0) {
                if(newDate.getTime() < date.getTime()){
                  categoriesMonths.push(date.toLocaleDateString())
                  series[0].data.push(item.back_squat == null || item.back_squat == undefined ? 0 : item.back_squat)
                }
              }
            })
          }else{
            orderArray.forEach((item)=> {
              let date = new Date(item.fitness_date);
              if (item.back_squat > 0) {
                categoriesMonths.push(date.toLocaleDateString())
                series[0].data.push(item.back_squat == null || item.back_squat == undefined ? 0 : item.back_squat)
              }
            })
          }
          categoriesMonths.reverse()
          series[0].data.reverse()
          break;
        case "7":
          if(entriesModelXAxis.value.key != 0){
            const newDate = dateSearch();
            orderArray.forEach((item)=> {
              let date = new Date(item.fitness_date);
              if (item.power_clean > 0) {
                if(newDate.getTime() < date.getTime()){
                  categoriesMonths.push(date.toLocaleDateString())
                  series[0].data.push(item.power_clean == null || item.power_clean == undefined ? 0 : item.power_clean)
                }
              }
            })
          }else{
            orderArray.forEach((item)=> {
              let date = new Date(item.fitness_date);
              if (item.power_clean > 0) {
                categoriesMonths.push(date.toLocaleDateString())
                series[0].data.push(item.power_clean == null || item.power_clean == undefined ? 0 : item.power_clean)
              }
            })
          }
          categoriesMonths.reverse()
          series[0].data.reverse()
          break;
        case "8":
          if(entriesModelXAxis.value.key != 0){
            const newDate = dateSearch();
            orderArray.forEach((item)=> {
              let date = new Date(item.fitness_date);
              if (item.body_weight > 0) {
                if(newDate.getTime() < date.getTime()){
                  categoriesMonths.push(date.toLocaleDateString())
                  series[0].data.push(item.body_weight == null || item.body_weight == undefined ? 0 : item.body_weight)
                }
              }
            })
          }else{
            orderArray.forEach((item)=> {
              let date = new Date(item.fitness_date);
              if (item.body_weight > 0) {
                categoriesMonths.push(date.toLocaleDateString())
                series[0].data.push(item.body_weight == null || item.body_weight == undefined ? 0 : item.body_weight)
              }
            })
          }
          categoriesMonths.reverse()
          series[0].data.reverse()
          break;
        case "9":
          if(entriesModelXAxis.value.key != 0){
            const newDate = dateSearch();
            orderArray.forEach((item)=> {
              let date = new Date(item.fitness_date);
              const score = computeFmtrxStrengthScore(item)
              if (score !== null) {
                if(newDate.getTime() < date.getTime()){
                  categoriesMonths.push(date.toLocaleDateString())
                  series[0].data.push(score)
                }
              }
            })
          }else{
            orderArray.forEach((item)=> {
              let date = new Date(item.fitness_date);
              const score = computeFmtrxStrengthScore(item)
              if (score !== null) {
                categoriesMonths.push(date.toLocaleDateString())
                series[0].data.push(score)
              }
            })
          }
          categoriesMonths.reverse()
          series[0].data.reverse()
          break;

        default:
          toast.fire({
            icon: 'error',
            title: 'Error !!!',
            text: 'Something have error',
          })
          showChart.value = false
          isLoading.status =!isLoading.status;
          break;
      }

      if(series[0].data.length != 0){
        series[0].name = entriesModelYAxis.value.title
        isLoading.status =!isLoading.status;
        showChart.value = true
      }else{
        isLoading.status =!isLoading.status;
        toast.fire({
          icon: 'error',
          title: 'Validation !!!',
          text: "This player don't have data",
        })
      }
    }
  } else{
    isLoading.status =!isLoading.status;
    toast.fire({
      icon: 'error',
      title: 'Validation !!!',
      text: "This player don't have data",
    })
  }
}

const dateSearch = () => {
  const currentDate = new Date();
  let newDate = new Date()
  if (currentDate.getDate() != 1) {
    const date = new Date(
      currentDate.getFullYear(),
      currentDate.getMonth() - entriesModelXAxis.value.key + 1,
      Math.min(currentDate.getDate(), new Date(currentDate.getFullYear(), currentDate.getMonth() + entriesModelXAxis.value.key, + 1, 0).getDate())
    );
    newDate = date
  }else{
    const date = new Date(
      currentDate.getFullYear(),
      currentDate.getMonth() - entriesModelXAxis.value.key,
      Math.min(currentDate.getDate(), new Date(currentDate.getFullYear(), currentDate.getMonth() + entriesModelXAxis.value.key, + 1, 0).getDate())
    );
    newDate = date
  }

  return newDate;
}

const onChange = (event) => {
  showChart.value = false
}

const toNum = (v) => {
  const n = Number(v)
  return Number.isFinite(n) ? n : null
}

const clamp = (v, min, max) => Math.max(min, Math.min(max, v))

const lerp = (x, x0, x1, y0, y1) => {
  if (x1 === x0) return y0
  return y0 + ((x - x0) / (x1 - x0)) * (y1 - y0)
}

const mapHigherBetter = (value, anchors) => {
  if (value === null || value === undefined || !Number.isFinite(value) || value <= 0) return null
  const pts = (anchors || []).filter((p) => Array.isArray(p) && p.length === 2).sort((a, b) => a[0] - b[0])
  if (pts.length === 0) return null
  if (value <= pts[0][0]) return clamp(pts[0][1], 0, 100)
  for (let i = 1; i < pts.length; i++) {
    const [x1, y1] = pts[i]
    const [x0, y0] = pts[i - 1]
    if (value <= x1) return clamp(lerp(value, x0, x1, y0, y1), 0, 100)
  }
  return 100
}

const mapLowerBetter = (value, anchors) => {
  if (value === null || value === undefined || !Number.isFinite(value) || value <= 0) return null
  const pts = (anchors || []).filter((p) => Array.isArray(p) && p.length === 2).sort((a, b) => a[0] - b[0])
  if (pts.length === 0) return null
  if (value <= pts[0][0]) return clamp(pts[0][1], 0, 100)
  for (let i = 1; i < pts.length; i++) {
    const [x1, y1] = pts[i]
    const [x0, y0] = pts[i - 1]
    if (value <= x1) return clamp(lerp(value, x0, x1, y0, y1), 0, 100)
  }
  return clamp(pts[pts.length - 1][1], 0, 100)
}

const weightedAverage = (items = [], fallback = 0) => {
  const valid = items.filter((x) => x && Number.isFinite(x.value))
  if (valid.length === 0) return fallback
  const wSum = valid.reduce((s, x) => s + (x.weight || 0), 0)
  if (!wSum) return fallback
  return valid.reduce((s, x) => s + ((x.value * (x.weight || 0))), 0) / wSum
}

const computeFmtrxStrengthScore = (entry) => {
  const bw = toNum(entry?.body_weight)
  if (!bw || bw <= 0) return null

  const bench = toNum(entry?.bench_press) || 0
  const dead = toNum(entry?.dead_lift) || 0
  const backSq = toNum(entry?.back_squat) || 0
  const frontSq = toNum(entry?.front_squat) || 0
  const clean = toNum(entry?.power_clean) || 0
  const dash40 = toNum(entry?.yd_40_dash)
  const dash60 = toNum(entry?.yd_60_dash)

  const cleanRatio = clean > 0 ? clean / bw : null
  const deadRatio = dead > 0 ? dead / bw : null
  const backRatio = backSq > 0 ? backSq / bw : null
  const frontRatio = frontSq > 0 ? frontSq / bw : null
  const benchRatio = bench > 0 ? bench / bw : null

  const cleanScore = mapHigherBetter(cleanRatio, [[0.8, 30], [1.0, 55], [1.2, 78], [1.35, 90], [1.5, 100]])
  const deadScore = mapHigherBetter(deadRatio, [[1.5, 35], [2.0, 60], [2.5, 85], [3.0, 100]])
  const backScore = mapHigherBetter(backRatio, [[1.2, 35], [1.6, 60], [2.0, 82], [2.5, 100]])
  const frontScore = mapHigherBetter(frontRatio, [[1.0, 40], [1.3, 62], [1.5, 78], [2.0, 100]])
  const benchScore = mapHigherBetter(benchRatio, [[0.9, 40], [1.1, 58], [1.3, 76], [1.5, 90], [1.7, 100]])
  const dash60Score = mapLowerBetter(dash60, [[6.3, 100], [6.5, 92], [6.6, 84], [6.8, 70], [7.4, 30]])
  const dash40Score = mapLowerBetter(dash40, [[4.3, 100], [4.5, 94], [4.7, 84], [4.9, 68], [5.3, 30]])

  const powerScore = weightedAverage([{ value: cleanScore, weight: 1 }], 0)
  const strengthScore = weightedAverage([
    { value: deadScore, weight: 0.35 },
    { value: backScore, weight: 0.30 },
    { value: frontScore, weight: 0.20 },
    { value: benchScore, weight: 0.15 },
  ], 0)
  const speedScore = weightedAverage([
    { value: dash60Score, weight: 0.7 },
    { value: dash40Score, weight: 0.3 },
  ], 0)
  const relativeStrengthScore = weightedAverage([
    { value: cleanScore, weight: 0.6 },
    { value: deadScore, weight: 0.4 },
  ], 0)

  return parseFloat(clamp(
    (powerScore * 0.45) +
    (strengthScore * 0.30) +
    (speedScore * 0.20) +
    (relativeStrengthScore * 0.05),
    0,
    100
  ).toFixed(1))
}
</script>

<template>
  <Loader v-show="!isLoading.status"/>
  <div class="charts-wrap">

    <!-- ── Interactive metric chart ───────────────────────────────────────── -->
    <div class="metric-chart-card">

      <!-- Metric selector buttons -->
      <div class="selector-group">
        <button
          v-for="m in METRICS"
          :key="m.key"
          class="selector-btn"
          :class="{ active: activeMetricKeys.has(m.key) }"
          :style="activeMetricKeys.has(m.key) ? { background: m.color, borderColor: m.color } : {}"
          @click="toggleMetric(m.key)"
        >{{ m.label }}</button>
      </div>

      <!-- Date range buttons -->
      <div class="range-group">
        <button
          v-for="r in DATE_RANGES"
          :key="r.months"
          class="range-btn"
          :class="{ active: activeDateRange === r.months }"
          @click="activeDateRange = r.months"
        >{{ r.label }}</button>
      </div>

      <!-- Per-metric stat rows -->
      <div v-if="hasAnyData" class="stats-block">
        <div v-for="s in metricStats" :key="s.key" class="stat-row">
          <div class="stat-metric-label" :style="{ color: s.color }">
            <span class="stat-dot" :style="{ background: s.color }"></span>
            {{ s.label }}
          </div>
          <div class="stat-pills">
            <div v-if="s.current !== null" class="stat-pill">
              <span class="pill-val">{{ s.current }}{{ s.unit.startsWith('/') ? '' : '&thinsp;' }}{{ s.unit }}</span>
              <span class="pill-lbl">Current</span>
            </div>
            <div v-if="s.change !== null" class="stat-pill" :class="s.good ? 'pill-good' : 'pill-bad'">
              <span class="pill-val">{{ s.change > 0 ? '+' : '' }}{{ s.change }}{{ s.unit.startsWith('/') ? '' : '&thinsp;' }}{{ s.unit }}</span>
              <span class="pill-lbl">Change</span>
            </div>
            <div v-if="s.low !== null" class="stat-pill">
              <span class="pill-val">{{ s.low }}{{ s.unit.startsWith('/') ? '' : '&thinsp;' }}{{ s.unit }}</span>
              <span class="pill-lbl">Low</span>
            </div>
            <div v-if="s.high !== null" class="stat-pill">
              <span class="pill-val">{{ s.high }}{{ s.unit.startsWith('/') ? '' : '&thinsp;' }}{{ s.unit }}</span>
              <span class="pill-lbl">High</span>
            </div>
            <div v-if="s.count" class="stat-pill">
              <span class="pill-val">{{ s.count }}</span>
              <span class="pill-lbl">Points</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Chart -->
      <div v-if="hasAnyData" class="chart-area">
        <apexchart
          width="100%"
          type="line"
          height="260"
          :options="chartOptions"
          :series="chartSeries"
          :key="[...activeMetricKeys].join('_') + '_' + activeDateRange"
        />
      </div>
      <div v-else class="chart-empty">
        <svg class="w-8 h-8 text-white/20 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <p class="text-white/35 text-sm font-semibold">No data for this selection</p>
      </div>
    </div>

    <!-- Divider -->
    <div class="section-divider">
      <span>Custom Metric Chart</span>
    </div>

    <!-- ── Custom metric explorer (existing) ───────────────────────────────── -->
    <div class="w-auto px-1 sm:px-4">
      <div class="grid grid-cols-5 gap-2 sm:gap-4 mt-4">
        <div class="w-full flex flex-col items-center col-span-2">
          <label for="entries">Select Y-axis Data:</label>
          <div class="relative w-full">
          <select class="selectd-form" v-model="entriesModelYAxis" style="z-index: 9" @change="onChange($event)">
            <option class="text-fungo-darkblue" value="" disabled selected>Select your option</option>
            <option class="text-fungo-darkblue" v-for="(item, index) in entriesOptionYAxis" :value="item">{{ item.label }}</option>
          </select>
          <div class="arrow-position"> <ArrowDownIcon color="26364D"/> </div>
        </div>
      </div>
      <div class="w-full flex flex-col items-center col-span-2">
        <label for="entries">Select X-axis Data:</label>
        <div class="relative w-full">
          <select class="selectd-form" v-model="entriesModelXAxis" style="z-index: 9" @change="onChange($event)">
            <option class="text-fungo-darkblue" value="" disabled selected>Select your option</option>
            <option class="text-fungo-darkblue" v-for="(item, index) in entriesOptionXAxis" :value="item">{{ item.label }}</option>
          </select>
          <div class="arrow-position"> <ArrowDownIcon color="26364D"/> </div>
        </div>
      </div>
      <div class="grid items-end justify-items-center w-full">
        <div class="grid items-end justify-items-end w-[50px] h-[50px]">
          <button @click="searchChart" class="bg-fungo-red rounded-lg w-[100%] h-[85%] flex items-center justify-center">
            <SearchIcon />
          </button>
        </div>
      </div>
    </div>

    <div class="w-full my-6" v-if="showChart || tableData.length > 0">
        <hr class="bg-fungo-gray8 h-1 mt-2 mb-5">
        <div>
          <apexchart width="100%" type="line" height="500px" :options="dinamicChartOptionsFitness(entriesModelYAxis.title, categoriesMonths)" :series="series"/>
        </div>
      </div>

    </div><!-- end custom metric explorer -->
  </div><!-- end charts-wrap -->
</template>

<style scoped>

.charts-wrap {
  background: #060b14;
  min-height: 100%;
  padding: 16px;
  color: white;
}

/* ── Main chart card ── */
.metric-chart-card {
  background: linear-gradient(160deg, #0f1a2e 0%, #0b1120 100%);
  border: 1px solid rgba(192,0,0,0.18);
  border-radius: 14px;
  padding: 16px;
  margin-bottom: 4px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* ── Metric selector ── */
.selector-group {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.selector-btn {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  padding: 5px 11px;
  border-radius: 9999px;
  border: 1px solid rgba(255,255,255,0.12);
  background: rgba(255,255,255,0.04);
  color: rgba(255,255,255,0.55);
  cursor: pointer;
  transition: all 0.15s;
  white-space: nowrap;
}
.selector-btn:hover {
  border-color: rgba(192,0,0,0.45);
  color: rgba(255,255,255,0.9);
  background: rgba(192,0,0,0.08);
}
.selector-btn.active {
  background: #C00000;
  border-color: #C00000;
  color: #ffffff;
  box-shadow: 0 0 0 2px rgba(192,0,0,0.25);
}

/* ── Date range selector ── */
.range-group {
  display: flex;
  gap: 5px;
  flex-wrap: wrap;
}
.range-btn {
  font-size: 11px;
  font-weight: 700;
  padding: 4px 12px;
  border-radius: 6px;
  border: 1px solid rgba(255,255,255,0.1);
  background: rgba(255,255,255,0.03);
  color: rgba(255,255,255,0.45);
  cursor: pointer;
  transition: all 0.15s;
}
.range-btn:hover {
  border-color: rgba(192,0,0,0.4);
  color: rgba(255,255,255,0.8);
}
.range-btn.active {
  background: rgba(192,0,0,0.15);
  border-color: rgba(192,0,0,0.55);
  color: #ff6666;
}

/* ── Stat pills ── */
.stats-block {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.stat-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}
.stat-metric-label {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  min-width: 88px;
  flex-shrink: 0;
}
.stat-dot {
  width: 8px;
  height: 8px;
  border-radius: 9999px;
  flex-shrink: 0;
}
.stat-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}
.stat-pill {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 8px;
  padding: 5px 11px;
  min-width: 54px;
}
.stat-pill.pill-good { border-color: rgba(74,222,128,0.3); background: rgba(74,222,128,0.07); }
.stat-pill.pill-bad  { border-color: rgba(248,113,113,0.3); background: rgba(248,113,113,0.07); }
.pill-val {
  font-size: 13px;
  font-weight: 800;
  color: #ffffff;
  line-height: 1;
}
.pill-lbl {
  font-size: 9px;
  font-weight: 700;
  color: rgba(255,255,255,0.35);
  text-transform: uppercase;
  letter-spacing: 0.07em;
  margin-top: 2px;
}

.chart-area {
  margin: 0 -4px;
}

.chart-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 16px;
  background: rgba(255,255,255,0.02);
  border: 1px dashed rgba(255,255,255,0.08);
  border-radius: 10px;
}

/* ── Divider ── */
.section-divider {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 20px 0 12px;
}
.section-divider::before,
.section-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: rgba(255,255,255,0.08);
}
.section-divider span {
  font-size: 10px;
  font-weight: 800;
  color: rgba(255,255,255,0.3);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  white-space: nowrap;
}

label {
  color: rgba(255,255,255,0.6);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 6px;
}

::-webkit-scrollbar {
  width: 4px;
  height: 4px;
}

::-webkit-scrollbar-button {
  width: 0px;
  height: 0px;
}

::-webkit-scrollbar-thumb {
  @apply bg-fungo-red-hover rounded-md;
}

::-webkit-scrollbar-thumb:active {
  @apply bg-fungo-red;
}

::-webkit-scrollbar-track {
  border: 22px solid #918383;
  @apply bg-fungo-dark-gray rounded-md;
}

::-webkit-scrollbar-corner {
  background: transparent;
}

.arrow-position{
  z-index: 0;
  position: absolute;
  top: 0;
  right: 0;
}

.selectd-form{
  @apply bg-white h-10 appearance-none bg-none w-full border border-fungo-darkblue text-fungo-darkblue rounded-[5px]
}
</style>
