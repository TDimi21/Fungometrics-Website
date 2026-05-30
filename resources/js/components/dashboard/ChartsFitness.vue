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
  <div class="w-auto mt-2 px-1 sm:px-6">
    <div class="grid grid-cols-5 gap-2 sm:gap-4 mt-8">
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

    <div class="w-full my-10" v-if="showChart || tableData.length > 0">
      <hr class="bg-fungo-gray8 h-1 mt-2 mb-5">
      <div>
        <apexchart width="100%" type="line" height="500px" :options="dinamicChartOptionsFitness(entriesModelYAxis.title, categoriesMonths)" :series="series"/>
      </div>
    </div>

  </div>
</template>

<style scoped>

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
