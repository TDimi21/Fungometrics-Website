<script setup>
import { Dialog, DialogPanel, TransitionRoot, TransitionChild } from '@headlessui/vue'
import { InputImage, InputBase, LabelField } from '@/components/form'
import { ArrowRightIcon } from '@/components/icons'
import { Tabs, MetricsViews, TableMetric, ChartsFitness } from './index'
import { ref, reactive, computed } from 'vue'
import {toast} from "../../utils/AlertPlugin";
import Loader from "@/components/Loader.vue";
import {useRouter} from "vue-router"

  const props = defineProps({
    isOpen: {
      type: Boolean,
      required: true
    },
    item: {
      type: Object,
      required: true
    },
    response: {
      type: Object,
      required: true,
      default: {}
    },
    score: {
      type: Object,
      required: true,
      default: {}
    },
  })

  const emit = defineEmits(['closeModal'])
  const date = Date.now();
  const fecha = new Date(date);
  const view = ref("home")
  const isLoading = reactive({status: true})
  const router = useRouter()
  const token = JSON.parse(localStorage.getItem('auth')).token
  const api_url = process.env.API_ENDPOINT;
  const limitDate = `${fecha.getFullYear()}-${fecha.getMonth() + 1}-${fecha.getDate()}`

  const tabs = [
    {
      title: "Metrics",
      value: 'metrics',
      icon: '',
      disabled: false,
    },
    {
      title: "Charts",
      value: 'charts',
      icon: '',
      disabled: false,
    },
    {
      title: "Metric Log",
      value: 'metric-log',
      icon: '',
      disabled: false,
    },
    {
      title: "Edit",
      value: 'edit',
      icon: '',
      disabled: false,
    },
  ]

  let player = reactive({
    height: props.item.body.full_height,
    name: props.item.name.full,
    lastName: props.item.name.last,
    email: props.item.email,
    // weight: props.item.body.weight_data,
    avatar: props.item.avatar,
  })

  let dataFitness = reactive({
    body_weight: "",
    bench_press: "",
    front_squat: "",
    back_squat: "",
    power_clean: "",
    dead_lift: "",
    yd_40_dash: "",
    yd_60_dash: "",
    sleep_hours: "",
    sleep_quality_1_to_5: "",
    recovery_score: "",
    mobility_score: "",
    // id: Object.keys(props.response).length === 0 ? "" : props.response[length].id,
    fitness_date: "",
  })

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

  const latestFitnessMetrics = computed(() => {
    if (!Array.isArray(props.response) || props.response.length === 0) return null

    const sorted = props.response
      .slice()
      .sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime())

    const pickLatest = (key) => {
      let value = null
      sorted.forEach((item) => {
        const n = toNum(item?.[key])
        if (n !== null && n > 0) value = n
      })
      return value
    }

    return {
      body_weight: pickLatest('body_weight'),
      bench_press: pickLatest('bench_press'),
      front_squat: pickLatest('front_squat'),
      back_squat: pickLatest('back_squat'),
      power_clean: pickLatest('power_clean'),
      dead_lift: pickLatest('dead_lift'),
      yd_40_dash: pickLatest('yd_40_dash'),
      yd_60_dash: pickLatest('yd_60_dash'),
    }
  })

  const fmtrxStrengthScore = computed(() => {
    const fit = latestFitnessMetrics.value
    if (!fit) return null

    const bw = toNum(fit.body_weight)
    if (!bw || bw <= 0) return null

    const bench = toNum(fit.bench_press) || 0
    const dead = toNum(fit.dead_lift) || 0
    const backSq = toNum(fit.back_squat) || 0
    const frontSq = toNum(fit.front_squat) || 0
    const clean = toNum(fit.power_clean) || 0
    const dash40 = toNum(fit.yd_40_dash)
    const dash60 = toNum(fit.yd_60_dash)

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

    const score = clamp(
      (powerScore * 0.45) +
      (strengthScore * 0.30) +
      (speedScore * 0.20) +
      (relativeStrengthScore * 0.05),
      0,
      100
    )

    const rounded = parseFloat(score.toFixed(1))
    const tier = rounded >= 90 ? 'ELITE' : rounded >= 80 ? 'HIGH' : rounded >= 70 ? 'SOLID' : rounded >= 60 ? 'DEV' : 'NEEDS'
    return { score: rounded, tier }
  })

  const submitEditFitness = async () => {
    isLoading.status =!isLoading.status;
    let dataForm = new FormData();
    if (dataFitness.fitness_date == undefined || dataFitness.fitness_date == null || dataFitness.fitness_date == "") {
      dataForm.append('fitness_date', `${fecha.getMonth() + 1}/${fecha.getDate()}/${fecha.getFullYear()}`)
    } else {
      dataForm.append('fitness_date', dataFitness.fitness_date)
    }
    dataForm.append('user_id', props.item.id)
      // dataForm.append('fitness_date', fecha.toLocaleDateString())
    dataForm.append('bench_press', parseInt(dataFitness.bench_press == "" || dataFitness.bench_press == undefined ? 0 : dataFitness.bench_press))
    dataForm.append('front_squat', parseInt(dataFitness.front_squat == "" || dataFitness.front_squat == undefined ? 0 : dataFitness.front_squat))
    dataForm.append('back_squat', parseInt(dataFitness.back_squat == "" || dataFitness.back_squat == undefined ? 0 : dataFitness.back_squat))
    dataForm.append('power_clean', parseInt(dataFitness.power_clean == "" || dataFitness.power_clean == undefined ? 0 : dataFitness.power_clean))
    dataForm.append('dead_lift', parseInt(dataFitness.dead_lift == "" || dataFitness.dead_lift == undefined ? 0 : dataFitness.dead_lift))
    dataForm.append('yd_40_dash', parseFloat(dataFitness.yd_40_dash == "" || dataFitness.yd_40_dash == undefined ? 0.0 : dataFitness.yd_40_dash))
    dataForm.append('yd_60_dash', parseFloat(dataFitness.yd_60_dash == "" || dataFitness.yd_60_dash == undefined ? 0.0 : dataFitness.yd_60_dash))
    dataForm.append('body_weight', parseFloat(dataFitness.body_weight == "" || dataFitness.body_weight == undefined ? 0.0 : dataFitness.body_weight))
    dataForm.append('sleep_hours', parseFloat(dataFitness.sleep_hours == "" || dataFitness.sleep_hours == undefined ? 0.0 : dataFitness.sleep_hours))
    dataForm.append('sleep_quality_1_to_5', parseInt(dataFitness.sleep_quality_1_to_5 == "" || dataFitness.sleep_quality_1_to_5 == undefined ? 0 : dataFitness.sleep_quality_1_to_5))
    dataForm.append('recovery_score', parseInt(dataFitness.recovery_score == "" || dataFitness.recovery_score == undefined ? 0 : dataFitness.recovery_score))
    dataForm.append('mobility_score', parseInt(dataFitness.mobility_score == "" || dataFitness.mobility_score == undefined ? 0 : dataFitness.mobility_score))
    const config = {
      headers: { Authorization: `Bearer ${token}` }
    };
    console.log("PASO");
    await axios.post(api_url+'player/fitness', dataForm, config).then(async function (response) {
      console.log("PASO");
      let tempResponse = response.data
      toast.fire({
        icon: 'success',
        title: 'Fitness Update',
        text: tempResponse.message,
      })
      isLoading.status =!isLoading.status;
      router.go("/dashboard")
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
          title: 'Team Warning !!!',
          text: errorMessage,
        })
        isLoading.status =!isLoading.status;
      } else {
        await toast.fire({
          icon: 'error',
          title: 'Team Error !!!',
          text: "strike 3 is out, have a internal problem, " +error.response.data.message,
        })
        isLoading.status =!isLoading.status;
      }
    })
  }
</script>

<template>
  <Loader v-show="!isLoading.status"/>
  <TransitionRoot appear :show="isOpen" as="template">
    <Dialog as="div" @close="emit('closeModal')" class="relative z-10">
      <TransitionChild
        as="template"
        enter="duration-300 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-200 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-black/55 backdrop-blur-[2px]" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto">
        <div
          class="flex items-center justify-center h-full p-4 text-center"
        >
          <TransitionChild
            as="template"
            enter="duration-300 ease-out"
            enter-from="opacity-0 scale-95"
            enter-to="opacity-100 scale-100"
            leave="duration-200 ease-in"
            leave-from="opacity-100 scale-100"
            leave-to="opacity-0 scale-95"
          >
            <DialogPanel
              class="modal-panel w-full h-[88vh] transform rounded-xl
              bg-[#060b14] text-left align-middle shadow-xl transition-all flex flex-row overflow-hidden">

              <!-- LEFT SIDEBAR: player profile -->
              <div class="player-sidebar flex flex-col items-center gap-4 p-5 overflow-y-auto border-r border-white/10">
                <!-- Close button -->
                <div class="self-end cursor-pointer w-[24px] h-[24px]" @click="emit('closeModal')">
                  <img alt="Icon close view" src="../../assets/img/register/cancel.svg">
                </div>
                <!-- Avatar -->
                <div class="rounded-full ring-4 ring-[#C00000] overflow-hidden w-[110px] h-[110px] flex-shrink-0">
                  <img v-if="player.avatar" :src="player.avatar" alt="" class="w-full h-full object-cover">
                  <img v-else src="../../assets/img/layout/logofungo-nav.png" alt="" class="w-full h-full object-cover">
                </div>
                <!-- Name -->
                <div class="text-center">
                  <p class="text-white font-bold text-base leading-tight">{{ player.name }}</p>
                  <p v-if="item.number_in_shirt" class="text-[#C00000] font-bold text-lg">#{{ item.number_in_shirt }}</p>
                </div>
                <!-- Edit Profile link -->
                <div class="edit-profile-link" @click="() => { emit('closeModal'); router.push(`/roster/player/${item.id}`) }">Edit Profile</div>
                <!-- Divider -->
                <div class="w-full border-t border-white/10"></div>
                <!-- Info rows -->
                <div class="w-full flex flex-col gap-3">
                  <div v-if="item.number_in_shirt" class="sidebar-row">
                    <span class="sidebar-label">Jersey #</span>
                    <span class="sidebar-value">{{ item.number_in_shirt }}</span>
                  </div>
                  <div v-if="item.throw_side" class="sidebar-row">
                    <span class="sidebar-label">Throws</span>
                    <span class="sidebar-value">{{ item.throw_side }}</span>
                  </div>
                  <div v-if="item.hit_side" class="sidebar-row">
                    <span class="sidebar-label">Bats</span>
                    <span class="sidebar-value">{{ item.hit_side }}</span>
                  </div>
                  <div v-if="player.height" class="sidebar-row">
                    <span class="sidebar-label">Height</span>
                    <span class="sidebar-value">{{ player.height }} ft</span>
                  </div>
                  <div v-if="item.born?.age" class="sidebar-row">
                    <span class="sidebar-label">Age</span>
                    <span class="sidebar-value">{{ item.born.age }}</span>
                  </div>
                  <div v-if="item.email" class="sidebar-row">
                    <span class="sidebar-label">Email</span>
                    <span class="sidebar-value text-xs truncate">{{ item.email }}</span>
                  </div>
                  <div v-if="item.positions?.length" class="sidebar-row">
                    <span class="sidebar-label">Position</span>
                    <span class="sidebar-value">{{ item.positions.join(', ') }}</span>
                  </div>
                </div>
                <!-- Score box -->
                <template v-if="score">
                  <div class="w-full border-t border-white/10"></div>
                  <div class="score-badge">
                    <div class="score-badge-value">{{ fmtrxStrengthScore?.score ?? '—' }}</div>
                    <div class="score-badge-label">{{ fmtrxStrengthScore?.tier ?? 'FMTRX SCORE' }}</div>
                  </div>
                  <div class="w-full flex flex-col gap-3 mt-1">
                    <div v-if="score.velo" class="sidebar-row">
                      <span class="sidebar-label">Velo</span>
                      <span class="sidebar-value">{{ score.velo }}</span>
                    </div>
                    <div v-if="score.ev" class="sidebar-row">
                      <span class="sidebar-label">EV</span>
                      <span class="sidebar-value">{{ score.ev }}</span>
                    </div>
                  </div>
                </template>
              </div>

              <!-- RIGHT SECTION: tabs -->
              <div class="flex-1 flex flex-col overflow-hidden">
                <Tabs :tabs="tabs" active="tabs">
                  <template #content="{active}">

                    <template v-if="active == 'metrics'">
                      <MetricsViews :item="item" :view="view" :data="response" :score="score"></MetricsViews>
                    </template>

                    <template v-if="active == 'charts'">
                      <ChartsFitness :item="item" :response="response"></ChartsFitness>
                    </template>

                    <template v-if="active == 'metric-log'">
                      <TableMetric :tableData="response"></TableMetric>
                    </template>

                    <template v-if="active == 'edit'">
                      <div class="w-auto mt-2 px-3 pb-4">
                        <div class="grid grid-cols-1 gap-2 mt-5">
                          <div class="justify-self-center mt-1 w-full rounded-xl border border-white/10 bg-white/[0.03] p-3 md:p-4">
                            <h1 class="text-lg md:text-xl capitalize font-semibold p-3 leading-6 text-white/90 bg-white/5 rounded-lg text-center font-fungo-800">
                              Current Information
                            </h1>
                            <div class="edit-fitness-form grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-5 truncate px-1 md:px-2">
                              <div class="text-white/85">
                                <LabelField text="Date" :required="false"/>
                                <InputBase v-model="dataFitness.fitness_date" inputType="date" :maxValue="limitDate" :minValue="'1970-12-31'" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="Weight" :required="false"/>
                                <InputBase v-model="dataFitness.body_weight" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="Front squat" :required="false"/>
                                <InputBase v-model="dataFitness.front_squat" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="Bench press" :required="false"/>
                                <InputBase v-model="dataFitness.bench_press" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="Back squat" :required="false"/>
                                <InputBase v-model="dataFitness.back_squat" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="DeadLift" :required="false"/>
                                <InputBase v-model="dataFitness.dead_lift" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="Clean" :required="false"/>
                                <InputBase v-model="dataFitness.power_clean" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="40 Time" :required="false"/>
                                <InputBase v-model="dataFitness.yd_40_dash" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="60 Time" :required="false"/>
                                <InputBase v-model="dataFitness.yd_60_dash" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="Sleep Hours" :required="false"/>
                                <InputBase v-model="dataFitness.sleep_hours" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="Sleep Quality (1-5)" :required="false"/>
                                <InputBase v-model="dataFitness.sleep_quality_1_to_5" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="Recovery Score (0-100)" :required="false"/>
                                <InputBase v-model="dataFitness.recovery_score" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="Mobility Score (0-100)" :required="false"/>
                                <InputBase v-model="dataFitness.mobility_score" inputType="number" class="max-w-[100%]"/>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="w-full flex justify-center mt-4">
                          <div class="w-[100%] flex justify-center px-4 my-4">
                            <button class="btn-edit rounded-button-right" type="submit" @click="submitEditFitness()">
                              <img alt="ball" class="w-6 h-6 md:w-8 md:h-8 mx-2 md:mx-0"
                                src="../../assets/img/login/assteslogin/ballbutton.svg">
                              <span class="mx-2">Save</span>
                              <div class="text-white mx-2 animate-bounce-r"><ArrowRightIcon color="ffffff" w="50" h="50"/></div>
                            </button>
                          </div>
                        </div>
                      </div>
                    </template>
                  </template>
                </Tabs>
              </div>

            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<style scoped>

/* Modal panel is 2/3 of screen width */
.modal-panel {
  width: min(1080px, 94vw);
}

/* Left sidebar = 1/3 of modal */
.player-sidebar {
  width: 33.333%;
  min-width: 200px;
  background: linear-gradient(180deg, #121a29 0%, #0b1320 100%);
}

/* Sidebar info rows */
.sidebar-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.sidebar-label {
  font-size: 10px;
  font-weight: 700;
  color: rgba(255,255,255,0.5);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  white-space: nowrap;
}
.sidebar-value {
  font-size: 12px;
  font-weight: 600;
  color: rgba(255,255,255,0.92);
  text-align: right;
}

.edit-profile-link {
  font-size: 11px;
  font-weight: 600;
  color: #C00000;
  cursor: pointer;
  border: 1px solid rgba(192, 0, 0, 0.4);
  border-radius: 9999px;
  padding: 6px 10px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.edit-profile-link:hover {
  color: #ff6666;
  border-color: rgba(255, 102, 102, 0.65);
  background: rgba(192, 0, 0, 0.08);
}

/* Override child Tabs component to match dark theme */
:deep(.tabs-header) {
  background-color: #060b14 !important;
  border-bottom: 1px solid rgba(255,255,255,0.1) !important;
}
:deep(.tab) {
  background-color: #060b14 !important;
  color: rgba(255,255,255,0.55) !important;
}
:deep(.tab.active) {
  color: #C00000 !important;
  border-bottom: 2px solid #C00000;
  background: rgba(255, 255, 255, 0.03) !important;
}
:deep(.encabezado) {
  max-width: 100% !important;
}
:deep(.tabs-content) {
  overflow-y: auto;
  max-height: calc(88vh - 55px);
}

/* Score badge in sidebar */
.score-badge {
  width: 100%;
  background: #090f19;
  border: 1px solid rgba(192,0,0,0.4);
  border-radius: 10px;
  padding: 12px 10px;
  text-align: center;
}
.score-badge-value {
  font-size: 28px;
  font-weight: 900;
  color: #ffffff;
  line-height: 1;
}
.score-badge-label {
  font-size: 11px;
  font-weight: 700;
  color: #C00000;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  margin-top: 4px;
}

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

.btn-edit{
  @apply grid place-items-center grid-flow-col flex-row w-[250px] lg:w-[300px] rounded-t-[30px] rounded-r-[10px] rounded-b-[10px] rounded-l-[30px]
    px-2 py-1 text-xl md:text-[16px] lg:text-[20px] bg-fungo-red text-white hover:bg-fungo-red-hover
}

/* Edit form contrast fixes */
.edit-fitness-form :deep(label) {
  color: rgba(226, 232, 240, 0.95) !important;
  font-size: 14px !important;
  font-weight: 600 !important;
  margin-bottom: 6px;
}

.edit-fitness-form :deep(input) {
  background: #f8fafc !important;
  color: #0f172a !important;
  border: 1px solid rgba(148, 163, 184, 0.4) !important;
  padding-left: 10px;
  padding-right: 10px;
}

.edit-fitness-form :deep(input::placeholder) {
  color: #64748b !important;
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
  background: #C00000;
  border: 0px none #ffffff;
  border-radius: 5px;
}
::-webkit-scrollbar-thumb:hover {
  background: #ffffff;
}
::-webkit-scrollbar-thumb:active {
  background: #000000;
}
::-webkit-scrollbar-track {
  background: #060b14;
  border: 22px solid #121a29;
  border-radius: 4px;
}
::-webkit-scrollbar-track:hover {
  background: #C00000;
  background: #333333;
}
::-webkit-scrollbar-corner {
  background: transparent;
}

@media (max-width: 1024px) {
  .modal-panel {
    width: 96vw;
    height: 92vh;
  }

  .player-sidebar {
    width: 36%;
    min-width: 180px;
  }
}

@media (max-width: 768px) {
  .modal-panel {
    width: 98vw;
    height: 94vh;
    flex-direction: column;
  }

  .player-sidebar {
    width: 100%;
    min-width: 0;
    border-right: 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }
}
</style>
