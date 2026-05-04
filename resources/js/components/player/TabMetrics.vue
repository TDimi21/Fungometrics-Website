<script setup>
import { ref, reactive, onMounted } from 'vue'
import router from "../../../router";
import {useUserStore} from "../../store/user";
import { ArrowRightIcon } from '@/components/icons'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { InputBase, LabelField, SelectField, BigButtonField} from '@/components/form'
import {toast} from "../../utils/AlertPlugin";
import Loader from "@/components/Loader.vue";

const { axiosGet } = useAxiosAuth()
const {userData} = useUserStore();
const isLoading = ref(false)
const isOpenModal = ref(false)
const dataMetric = ref({});
const tableHeadings = ref([
  "DATE", "BODY WEIGHT", "BENCH PRESS", "FRONT SQUAT", "BACK SQUAT", "POWER CLEAN", "DEAD LIFT", "40 YD DASH", "60 YD DASH"
])
const token = JSON.parse(localStorage.getItem('auth')).token
const api_url = process.env.API_ENDPOINT;
const isLoad = reactive({status: true})
let dataFitness = reactive({
  id: "",
  body_weight: "",
  bench_press: "",
  front_squat: "",
  back_squat: "",
  power_clean: "",
  dead_lift: "",
  yd_40_dash: "",
  yd_60_dash: "",
  fitness_date: "",
})

const getFitnessPlayer = async() => {
  try {
    isLoading.value = true
    const { data } = await axiosGet(`player/fitness/${userData.id}`)
    dataMetric.value = data.data
  } catch (error) {
    console.log(error);
  } finally {
    isLoading.value= false
  }
}

const submitAddFitness = async () => {
  isLoad.status =!isLoad.status;
  const date = Date.now();
      const fecha = new Date(date);
      let dataForm = new FormData();
      dataForm.append('user_id', userData.id)
      if (dataFitness.fitness_date == undefined || dataFitness.fitness_date == null || dataFitness.fitness_date == "" ) {
        dataForm.append('fitness_date', `${fecha.getMonth() + 1}/${fecha.getDate()}/${fecha.getFullYear()}`)
      } else {
        dataForm.append('fitness_date', dataFitness.fitness_date)
      }
      dataForm.append('bench_press', parseInt(dataFitness.bench_press == "" || dataFitness.bench_press == undefined ? 0 : dataFitness.bench_press))
      dataForm.append('front_squat', parseInt(dataFitness.front_squat == "" || dataFitness.front_squat == undefined ? 0 : dataFitness.front_squat))
      dataForm.append('back_squat', parseInt(dataFitness.back_squat == "" || dataFitness.back_squat == undefined ? 0 : dataFitness.back_squat))
      dataForm.append('power_clean', parseInt(dataFitness.power_clean == "" || dataFitness.power_clean == undefined ? 0 : dataFitness.power_clean))
      dataForm.append('dead_lift', parseInt(dataFitness.dead_lift == "" || dataFitness.dead_lift == undefined ? 0 : dataFitness.dead_lift))
      dataForm.append('yd_40_dash', parseFloat(dataFitness.yd_40_dash == "" || dataFitness.yd_40_dash == undefined ? 0.0 : dataFitness.yd_40_dash))
      dataForm.append('yd_60_dash', parseFloat(dataFitness.yd_60_dash == "" || dataFitness.yd_60_dash == undefined ? 0.0 : dataFitness.yd_60_dash))
      dataForm.append('body_weight', parseFloat(dataFitness.body_weight == "" || dataFitness.body_weight == undefined ? 0.0 : dataFitness.body_weight))

      const config = {
        headers: { Authorization: `Bearer ${token}` }
      };
      await axios.post(api_url+'player/fitness', dataForm, config).then(async function (response) {
        console.log(response.data);
        let tempResponse = response.data
        toast.fire({
          icon: 'success',
          title: 'Fitness Update',
          text: tempResponse.message,
        })
        isLoad.status =!isLoad.status;
        router.go("/player-dashboard")
      }).catch(async function (error){
        console.log(error.response);
        if (error.response.data.code === '001V' || error.response.status === 422 ) {
          const errorsObject = error.response.data.data.errors
          let errorMessage = ''
          let isAllow = false
          for (const [key, value] of Object.entries(errorsObject)) {
            if(!isAllow){
              isAllow = true
              errorMessage = value
            }
          }
          await toast.fire({
            icon: 'warning',
            title: 'Player Warning !!!',
            text: errorMessage,
          })
          isLoad.status =!isLoad.status;
        } else {
          await toast.fire({
            icon: 'error',
            title: 'Player Error !!!',
            text: "strike 3 is out, have a internal problem, " +error.response.data.message,
          })
          isLoad.status =!isLoad.status;
        }
      })
}

const translateDate = (date) => {
  const fecha = new Date(date);
  return date == null ? '' : `${fecha.getMonth() + 1}/${fecha.getDate()}/${fecha.getFullYear()}`;
}

// Metric card definitions: field key, display label, unit, lower-is-better (for colour logic)
const metricCards = [
  { key: 'body_weight',  label: 'Weight',      unit: 'lb',  lowerBetter: true  },
  { key: 'bench_press',  label: 'Bench Press', unit: 'lb',  lowerBetter: false },
  { key: 'front_squat',  label: 'Front Squat', unit: 'lb',  lowerBetter: false },
  { key: 'back_squat',   label: 'Back Squat',  unit: 'lb',  lowerBetter: false },
  { key: 'power_clean',  label: 'Power Clean', unit: 'lb',  lowerBetter: false },
  { key: 'dead_lift',    label: 'Dead Lift',   unit: 'lb',  lowerBetter: false },
  { key: 'yd_40_dash',   label: '40 Yd Dash',  unit: 's',   lowerBetter: true  },
  { key: 'yd_60_dash',   label: '60 Yd Dash',  unit: 's',   lowerBetter: true  },
]

// Returns { latest, latestDate, change, changeType: 'gain'|'loss'|'same'|'none' }
function getMetricStat(key, lowerBetter) {
  if (!dataMetric.value || !dataMetric.value.length) return null
  // Sort by date desc so index 0 is most recent
  const sorted = [...dataMetric.value]
    .filter(r => r[key] != null && parseFloat(r[key]) > 0)
    .sort((a, b) => new Date(b.fitness_date) - new Date(a.fitness_date))
  if (!sorted.length) return null

  const latest = parseFloat(sorted[0][key])
  const latestDate = sorted[0].fitness_date

  // Find oldest entry within the last 6 months
  const sixMonthsAgo = new Date()
  sixMonthsAgo.setMonth(sixMonthsAgo.getMonth() - 6)
  const inWindow = sorted.filter(r => new Date(r.fitness_date) >= sixMonthsAgo)
  // Need at least 2 entries to show change
  if (inWindow.length < 2) return { latest, latestDate, change: null, changeType: 'none' }

  const oldest = parseFloat(inWindow[inWindow.length - 1][key])
  if (oldest === 0) return { latest, latestDate, change: null, changeType: 'none' }

  const diff = latest - oldest
  const pct  = Math.abs(diff / oldest * 100)
  const improved = lowerBetter ? diff < 0 : diff > 0
  return {
    latest,
    latestDate,
    change: pct.toFixed(1),
    raw: diff,
    changeType: diff === 0 ? 'same' : (improved ? 'good' : 'bad'),
  }
}

const showModal = async () => {
  isOpenModal.value = !isOpenModal.value;
};

onMounted(() => {
  getFitnessPlayer()
})
</script>

<template>
  <Loader v-show="!isLoad.status"/>
  <div class="flex items-center justify-center w-full mt-4 py-5">
      <BigButtonField color="dark" label="Add Metrics" @click="showModal()"/>
    </div>
  <section class="mt-4 overflow-x-auto">

    <!-- Metric summary cards -->
    <div v-if="!isLoading && dataMetric.length > 0" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-6 px-1">
      <div
        v-for="m in metricCards"
        :key="m.key"
        class="metric-card"
      >
        <span class="metric-card-label">{{ m.label }}</span>
        <template v-if="getMetricStat(m.key, m.lowerBetter)">
          <span class="metric-card-value">
            {{ getMetricStat(m.key, m.lowerBetter).latest }} <span class="metric-card-unit">{{ m.unit }}</span>
          </span>
          <span class="metric-card-date">{{ translateDate(getMetricStat(m.key, m.lowerBetter).latestDate) }}</span>
          <span
            v-if="getMetricStat(m.key, m.lowerBetter).change !== null"
            class="metric-card-change"
            :class="{
              'change-good': getMetricStat(m.key, m.lowerBetter).changeType === 'good',
              'change-bad':  getMetricStat(m.key, m.lowerBetter).changeType === 'bad',
              'change-same': getMetricStat(m.key, m.lowerBetter).changeType === 'same',
            }"
          >
            {{ getMetricStat(m.key, m.lowerBetter).raw > 0 ? '+' : '' }}{{ getMetricStat(m.key, m.lowerBetter).raw.toFixed(1) }}
            ({{ getMetricStat(m.key, m.lowerBetter).change }}%)
          </span>
          <span v-else class="metric-card-change change-same">— no change data</span>
        </template>
        <span v-else class="metric-card-value text-gray-300">—</span>
      </div>
    </div>

    <table class="w-full border-separate space-y-6 text-fungo-darkblue">

      <thead class="bg-fungo-lightblue">
        <tr class="divide-x divide-[#000]">
          <th v-for="(heading, index) in tableHeadings"
            :key="index" class="py-3 font-fungo-500 text-white"
          >
            {{ heading }}
          </th>
        </tr>
      </thead>

      <tbody>
        <tr v-if="isLoading" class="w-full">
          <td colspan="11" class="text-fungo-darkblue text-3xl text-center">Loading data...</td>
        </tr>
        <tr v-else-if="!dataMetric.length > 0" class="w-full">
          <td colspan="11" class="text-fungo-darkblue text-3xl text-center">There is no data</td>
        </tr>
        <tr v-else v-for="(item, index) in dataMetric" :key="index" class="bg-white even:bg-fungo-gray4 border-fungo-lightblue border-l relative">
          <td>
            {{ translateDate(item.fitness_date) ?? "" }}
            <!-- {{ item.fitness_date ?? ""}} -->
          </td>
          <td>
            {{ item.body_weight ?? "-" }}
          </td>
          <td>
            {{ item.bench_press ?? "-" }}
          </td>
          <td>
            {{ item.front_squat ?? "-" }}
          </td>
          <td>
            {{ item.back_squat ?? "-" }}
          </td>
          <td>
            {{ item.power_clean ?? "-" }}
          </td>
          <td>
            {{ item.dead_lift ?? "-" }}
          </td>
          <td>
            {{ item.yd_40_dash ?? "-" }}
          </td>
          <td>
            {{ item.yd_60_dash ?? "-" }}
          </td>
        </tr>
      </tbody>
    </table>
  </section>

  <div v-if="isOpenModal">
    <div class="fixed inset-0 z-50 flex justify-center items-center">
      <div class="flex flex-col max-w-5xl rounded-lg shadow-xl overflow-y-auto bg-white border pt-2 drop-shadow-xl min-h-[45%] max-h-[55%]
        lg:min-h-[50%] lg:max-h-[58%] w-[85%] md:w-[100%] ml-3 lg:ml-0">
        <div class="flex flex-row w-[100%] items-center mb-3 px-4 ">
          <h1 class="text-lg lg:text-2xl text-fungo-red font-fungo-700 my-5">Player fitness</h1>
          <div class="absolute right-2 md:right-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]" @click="showModal()">
            <img alt="Icon close view" src="../../assets/img/register/cancel.svg">
          </div>
        </div>
        <div class="bg-fungo-gray2 py-10 px-[1%] md:px-[3%]">
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4 md:gap-2 lg:gap-1 truncate">
            <div class="input-metrics">
              <LabelField text="Date" :required="false"/>
              <InputBase v-model="dataFitness.fitness_date" inputType="date" class="max-w-[100%]"/>
            </div>
            <div class="input-metrics">
              <LabelField text="Body Weight" :required="false"/>
              <InputBase v-model="dataFitness.body_weight" :inputType="'number'" class="max-w-[100%]"/>
            </div>
            <div class="input-metrics">
              <LabelField text="Bench press" :required="false"/>
              <InputBase v-model="dataFitness.bench_press" :inputType="'number'" class="max-w-[100%]"/>
            </div>
            <div class="input-metrics">
              <LabelField text="Front squat" :required="false"/>
              <InputBase v-model="dataFitness.front_squat" :inputType="'number'" class="max-w-[100%]"/>
            </div>
            <div class="input-metrics">
              <LabelField text="Back squat" :required="false"/>
              <InputBase v-model="dataFitness.back_squat" :inputType="'number'" class="max-w-[100%]"/>
            </div>
            <div class="input-metrics">
              <LabelField text="Power clean" :required="false"/>
              <InputBase v-model="dataFitness.power_clean" :inputType="'number'" class="max-w-[100%]"/>
            </div>
            <div class="input-metrics">
              <LabelField text="DeadLift" :required="false"/>
              <InputBase v-model="dataFitness.dead_lift" :inputType="'number'" class="max-w-[100%]"/>
            </div>
            <div class="input-metrics">
              <LabelField text="40 Time" :required="false"/>
              <InputBase v-model="dataFitness.yd_40_dash" :inputType="'number'" class="max-w-[100%]"/>
            </div>
            <div class="input-metrics">
              <LabelField text="60 Time" :required="false"/>
              <InputBase v-model="dataFitness.yd_60_dash" :inputType="'number'" class="max-w-[100%]"/>
            </div>
            <div class="w-full flex mt-1 col-span-2 md:col-span-1 justify-center">
              <div class="my-4">
                <button class="btn-add rounded-button-right" type="submit" @click="submitAddFitness()">
                  <img alt="ball" class="w-6 h-6 md:w-8 md:h-8 mx-2 md:mx-0"
                  src="../../assets/img/login/assteslogin/ballbutton.svg">
                  <span class="mx-2">Save</span>
                  <div class="text-white mx-2 animate-bounce-r"><ArrowRightIcon color="ffffff" w="50" h="50"/></div>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="opacity-70 fixed inset-0 z-40 bg-fungo-darkblue"></div>
  </div>
</template>

<style scoped>

@keyframes bounce {
  0%, 100% {
    transform: translateX(-25%);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  50% {
    transform: none;
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
}

.animate-bounce-r {
  animation: bounce 1s infinite;
}

.btn-add{
  @apply grid place-items-center grid-flow-col flex-row w-[200px] lg:w-[250px] rounded-t-[30px] rounded-r-[10px] rounded-b-[10px] rounded-l-[30px]
    py-1 text-xl md:text-[16px] lg:text-[20px] bg-fungo-red text-white hover:bg-fungo-red-hover
}

.input-metrics{
  @apply text-fungo-darkblue
}

/* Metric summary cards */
.metric-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  background: #fff;
  border-radius: 14px;
  padding: 14px 10px 12px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.07);
  text-align: center;
}
.metric-card-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #6b7280;
}
.metric-card-value {
  font-size: 22px;
  font-weight: 900;
  color: #E10600;
  line-height: 1.1;
  margin-top: 4px;
}
.metric-card-unit {
  font-size: 13px;
  font-weight: 600;
  color: #9ca3af;
}
.metric-card-date {
  font-size: 10px;
  color: #9ca3af;
  margin-top: 1px;
}
.metric-card-change {
  font-size: 12px;
  font-weight: 700;
  margin-top: 4px;
}
.change-good { color: #16a34a; }
.change-bad  { color: #dc2626; }
.change-same { color: #9ca3af; }

table {
  border-spacing: 0 10px;
}

table tbody tr td {
  @apply text-center py-4 px-1 2xl:px-5;
}

table tbody tr::after {
  content: '';
  position: absolute;
  left: -1px;
  top: 0;
  height: 100%;
  width: 3px;
  background-color: #ADE8F4;
}

table tbody tr:nth-child(even)::after {
  background-color: #DADADA;
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
</style>
