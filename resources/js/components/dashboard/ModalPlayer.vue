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
  const token = (() => {
    try {
      const auth = JSON.parse(localStorage.getItem('auth') || '{}')
      return auth?.token ?? ''
    } catch (_) {
      return ''
    }
  })()
  const api_url = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || '';
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
    height: props.item.body?.full_height ?? null,
    name: props.item.name?.full ?? props.item.name ?? '—',
    lastName: props.item.name?.last ?? '',
    email: props.item.email ?? null,
    avatar: props.item.avatar ?? null,
  })

  let dataFitness = reactive({
    body_weight: "",
    bench_press: "",
    front_squat: "",
    back_squat: "",
    power_clean: "",
    hand_strength: "",
    dead_lift: "",
    push_ups: "",
    pull_ups: "",
    vertical_jump: "",
    broad_jump: "",
    med_ball_rotational_throw: "",
    sprint_10yd: "",
    exit_velo: "",
    bat_speed: "",
    throwing_velo: "",
    pitch_velo: "",
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
      hand_strength: pickLatest('hand_strength'),
      dead_lift: pickLatest('dead_lift'),
      push_ups: pickLatest('push_ups'),
      pull_ups: pickLatest('pull_ups'),
      vertical_jump: pickLatest('vertical_jump'),
      broad_jump: pickLatest('broad_jump'),
      med_ball_rotational_throw: pickLatest('med_ball_rotational_throw'),
      sprint_10yd: pickLatest('sprint_10yd'),
      exit_velo: pickLatest('exit_velo'),
      bat_speed: pickLatest('bat_speed'),
      throwing_velo: pickLatest('throwing_velo'),
      pitch_velo: pickLatest('pitch_velo'),
      yd_40_dash: pickLatest('yd_40_dash'),
      yd_60_dash: pickLatest('yd_60_dash'),
    }
  })

  const strengthScoreBadge = computed(() => {
    const fit = latestFitnessMetrics.value
    if (!fit) return null

    const bw = toNum(fit.body_weight)
    if (!bw || bw <= 0) return null

    const bench = toNum(fit.bench_press) || 0
    const dead = toNum(fit.dead_lift) || 0
    const backSq = toNum(fit.back_squat) || 0
    const frontSq = toNum(fit.front_squat) || 0
    const clean = toNum(fit.power_clean) || 0
    const deadRatio = dead > 0 ? dead / bw : null
    const backRatio = backSq > 0 ? backSq / bw : null
    const frontRatio = frontSq > 0 ? frontSq / bw : null
    const benchRatio = bench > 0 ? bench / bw : null

    const deadScore = mapHigherBetter(deadRatio, [[1.5, 35], [2.0, 60], [2.5, 85], [3.0, 100]])
    const backScore = mapHigherBetter(backRatio, [[1.2, 35], [1.6, 60], [2.0, 82], [2.5, 100]])
    const frontScore = mapHigherBetter(frontRatio, [[1.0, 40], [1.3, 62], [1.5, 78], [2.0, 100]])
    const benchScore = mapHigherBetter(benchRatio, [[0.9, 40], [1.1, 58], [1.3, 76], [1.5, 90], [1.7, 100]])

    const strengthScore = weightedAverage([
      { value: deadScore, weight: 0.35 },
      { value: backScore, weight: 0.30 },
      { value: frontScore, weight: 0.20 },
      { value: benchScore, weight: 0.15 },
    ], 0)

    const rounded = parseFloat(clamp(strengthScore, 0, 100).toFixed(1))
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
    dataForm.append('hand_strength', parseFloat(dataFitness.hand_strength == "" || dataFitness.hand_strength == undefined ? 0.0 : dataFitness.hand_strength))
    dataForm.append('dead_lift', parseInt(dataFitness.dead_lift == "" || dataFitness.dead_lift == undefined ? 0 : dataFitness.dead_lift))
    dataForm.append('push_ups', parseInt(dataFitness.push_ups == "" || dataFitness.push_ups == undefined ? 0 : dataFitness.push_ups))
    dataForm.append('pull_ups', parseInt(dataFitness.pull_ups == "" || dataFitness.pull_ups == undefined ? 0 : dataFitness.pull_ups))
    dataForm.append('vertical_jump', parseFloat(dataFitness.vertical_jump == "" || dataFitness.vertical_jump == undefined ? 0.0 : dataFitness.vertical_jump))
    dataForm.append('broad_jump', parseFloat(dataFitness.broad_jump == "" || dataFitness.broad_jump == undefined ? 0.0 : dataFitness.broad_jump))
    dataForm.append('med_ball_rotational_throw', parseFloat(dataFitness.med_ball_rotational_throw == "" || dataFitness.med_ball_rotational_throw == undefined ? 0.0 : dataFitness.med_ball_rotational_throw))
    dataForm.append('sprint_10yd', parseFloat(dataFitness.sprint_10yd == "" || dataFitness.sprint_10yd == undefined ? 0.0 : dataFitness.sprint_10yd))
    dataForm.append('exit_velo', parseFloat(dataFitness.exit_velo == "" || dataFitness.exit_velo == undefined ? 0.0 : dataFitness.exit_velo))
    dataForm.append('bat_speed', parseFloat(dataFitness.bat_speed == "" || dataFitness.bat_speed == undefined ? 0.0 : dataFitness.bat_speed))
    dataForm.append('throwing_velo', parseFloat(dataFitness.throwing_velo == "" || dataFitness.throwing_velo == undefined ? 0.0 : dataFitness.throwing_velo))
    dataForm.append('pitch_velo', parseFloat(dataFitness.pitch_velo == "" || dataFitness.pitch_velo == undefined ? 0.0 : dataFitness.pitch_velo))
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
    <Dialog as="div" @close="emit('closeModal')" class="relative z-50">
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
              <div class="player-sidebar flex flex-col overflow-y-auto border-r border-white/10">

                <!-- HERO HEADER -->
                <div class="sidebar-hero relative flex-shrink-0">
                  <!-- Blurred background -->
                  <div class="sidebar-hero-bg">
                    <img v-if="player.avatar" :src="player.avatar" alt="" class="w-full h-full object-cover">
                    <div v-else class="w-full h-full bg-gradient-to-br from-[#1a1f3a] to-[#0b0f1e]"></div>
                  </div>
                  <div class="sidebar-hero-overlay"></div>

                  <!-- Close button -->
                  <button class="sidebar-close" @click="emit('closeModal')">
                    <img alt="close" src="../../assets/img/register/cancel.svg" class="w-4 h-4 invert opacity-80">
                  </button>

                  <!-- Avatar + name -->
                  <div class="sidebar-hero-content">
                    <div class="sidebar-avatar-ring">
                      <img v-if="player.avatar" :src="player.avatar" alt="" class="w-full h-full object-cover">
                      <img v-else src="../../assets/img/layout/logofungo-nav.png" alt="" class="w-full h-full object-cover">
                    </div>
                    <div class="text-center mt-3">
                      <p class="text-white font-bold text-base leading-tight drop-shadow">{{ player.name }}</p>
                      <p v-if="item.shirt_number ?? item.number_in_shirt" class="text-[#ff4444] font-black text-xl leading-tight">#{{ item.shirt_number ?? item.number_in_shirt }}</p>
                    </div>
                    <!-- Position pills -->
                    <div v-if="item.positions?.length" class="flex flex-wrap justify-center gap-1 mt-2">
                      <span
                        v-for="pos in item.positions" :key="typeof pos === 'object' ? pos.id : pos"
                        class="position-pill">
                        {{ typeof pos === 'object' ? pos.position : pos }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- BODY -->
                <div class="flex flex-col gap-4 p-4">
                  <!-- Edit Profile -->
                  <button class="edit-profile-link" @click="() => { emit('closeModal'); router.push(`/roster/player/${item.id}`) }">
                    Edit Profile
                  </button>

                  <!-- Info rows -->
                  <div class="w-full flex flex-col gap-0 rounded-xl overflow-hidden border border-white/8">
                    <div v-if="item.shirt_number ?? item.number_in_shirt" class="info-row">
                    <span class="info-label">Jersey</span>
                    <span class="info-value">#{{ item.shirt_number ?? item.number_in_shirt }}</span>
                  </div>
                  <div v-if="item.throw_side" class="info-row">
                      <span class="info-label">Throws</span>
                      <span class="info-value">{{ item.throw_side }}</span>
                    </div>
                    <div v-if="item.hit_side" class="info-row">
                      <span class="info-label">Bats</span>
                      <span class="info-value">{{ item.hit_side }}</span>
                    </div>
                    <div v-if="player.height" class="info-row">
                      <span class="info-label">Height</span>
                      <span class="info-value">{{ player.height }} ft</span>
                    </div>
                    <div v-if="item.born?.age" class="info-row">
                      <span class="info-label">Age</span>
                      <span class="info-value">{{ item.born?.age }}</span>
                    </div>
                    <div v-if="item.email" class="info-row">
                      <span class="info-label">Email</span>
                      <span class="info-value text-[10px] truncate max-w-[130px]">{{ item.email }}</span>
                    </div>
                  </div>

                  <!-- Score cards -->
                  <template v-if="strengthScoreBadge">
                    <div class="score-card">
                      <div class="score-card-top">
                        <span class="score-card-label">Strength Score</span>
                        <span class="score-card-tier" :class="`tier-${strengthScoreBadge.tier}`">{{ strengthScoreBadge.tier }}</span>
                      </div>
                      <div class="score-card-value">{{ strengthScoreBadge.score }}</div>
                      <!-- mini bar -->
                      <div class="score-bar-track">
                        <div class="score-bar-fill" :style="{ width: strengthScoreBadge.score + '%' }"></div>
                      </div>
                    </div>
                  </template>

                  <!-- Velo / EV quick stats -->
                  <template v-if="score && (score.velo || score.ev)">
                    <div class="flex gap-2">
                      <div v-if="score.velo" class="quick-stat flex-1">
                        <span class="quick-stat-val">{{ score.velo }}</span>
                        <span class="quick-stat-lbl">Velo</span>
                      </div>
                      <div v-if="score.ev" class="quick-stat flex-1">
                        <span class="quick-stat-val">{{ score.ev }}</span>
                        <span class="quick-stat-lbl">EV</span>
                      </div>
                    </div>
                  </template>
                </div>
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
                                <LabelField text="Hand Strength (lbs)" :required="false"/>
                                <InputBase v-model="dataFitness.hand_strength" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="Push Ups (reps)" :required="false"/>
                                <InputBase v-model="dataFitness.push_ups" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="Pull-Ups (reps)" :required="false"/>
                                <InputBase v-model="dataFitness.pull_ups" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="Vertical Jump (in)" :required="false"/>
                                <InputBase v-model="dataFitness.vertical_jump" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="Broad Jump (in)" :required="false"/>
                                <InputBase v-model="dataFitness.broad_jump" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="Med Ball Rot Throw (ft)" :required="false"/>
                                <InputBase v-model="dataFitness.med_ball_rotational_throw" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="10 Yard (sec)" :required="false"/>
                                <InputBase v-model="dataFitness.sprint_10yd" inputType="number" class="max-w-[100%]"/>
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
                                <LabelField text="Exit Velo (mph)" :required="false"/>
                                <InputBase v-model="dataFitness.exit_velo" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="Bat Speed (mph)" :required="false"/>
                                <InputBase v-model="dataFitness.bat_speed" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="Throwing Velo (mph)" :required="false"/>
                                <InputBase v-model="dataFitness.throwing_velo" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-white/85">
                                <LabelField text="Pitch Velo (mph)" :required="false"/>
                                <InputBase v-model="dataFitness.pitch_velo" inputType="number" class="max-w-[100%]"/>
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

/* ── Modal shell ── */
.modal-panel {
  width: min(1100px, 95vw);
}

/* ── Sidebar ── */
.player-sidebar {
  width: 260px;
  min-width: 220px;
  flex-shrink: 0;
  background: #0b1120;
}

/* ── Hero header ── */
.sidebar-hero {
  height: 200px;
  overflow: hidden;
  position: relative;
}
.sidebar-hero-bg {
  position: absolute;
  inset: 0;
  filter: blur(18px) brightness(0.35) saturate(1.4);
  transform: scale(1.1);
}
.sidebar-hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, rgba(6,11,20,0.2) 0%, rgba(6,11,20,0.85) 85%, #0b1120 100%);
}
.sidebar-close {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 10;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 9999px;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.15s;
}
.sidebar-close:hover { background: rgba(192,0,0,0.3); }
.sidebar-hero-content {
  position: relative;
  z-index: 2;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  padding-bottom: 16px;
}
.sidebar-avatar-ring {
  width: 88px;
  height: 88px;
  border-radius: 9999px;
  border: 3px solid #C00000;
  box-shadow: 0 0 0 3px rgba(192,0,0,0.25), 0 4px 20px rgba(0,0,0,0.6);
  overflow: hidden;
  flex-shrink: 0;
}
.position-pill {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: #C00000;
  background: rgba(192,0,0,0.12);
  border: 1px solid rgba(192,0,0,0.35);
  border-radius: 9999px;
  padding: 2px 8px;
}

/* ── Info rows ── */
.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  border-bottom: 1px solid rgba(255,255,255,0.05);
  background: rgba(255,255,255,0.02);
}
.info-row:last-child { border-bottom: none; }
.info-row:nth-child(odd) { background: rgba(255,255,255,0.035); }
.info-label {
  font-size: 10px;
  font-weight: 700;
  color: rgba(255,255,255,0.4);
  text-transform: uppercase;
  letter-spacing: 0.08em;
}
.info-value {
  font-size: 12px;
  font-weight: 600;
  color: rgba(255,255,255,0.9);
}

/* ── Edit Profile button ── */
.edit-profile-link {
  width: 100%;
  font-size: 11px;
  font-weight: 700;
  color: rgba(255,255,255,0.75);
  cursor: pointer;
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 8px;
  padding: 8px 12px;
  text-align: center;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  background: rgba(255,255,255,0.04);
  transition: all 0.15s;
}
.edit-profile-link:hover {
  color: #ffffff;
  border-color: rgba(192,0,0,0.6);
  background: rgba(192,0,0,0.1);
}

/* ── Score card ── */
.score-card {
  background: linear-gradient(135deg, rgba(192,0,0,0.12) 0%, rgba(6,11,20,0.8) 100%);
  border: 1px solid rgba(192,0,0,0.3);
  border-radius: 12px;
  padding: 14px;
}
.score-card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}
.score-card-label {
  font-size: 10px;
  font-weight: 700;
  color: rgba(255,255,255,0.45);
  text-transform: uppercase;
  letter-spacing: 0.08em;
}
.score-card-tier {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  padding: 2px 7px;
  border-radius: 9999px;
}
.tier-ELITE { color: #22d3ee; background: rgba(34,211,238,0.12); border: 1px solid rgba(34,211,238,0.3); }
.tier-HIGH  { color: #4ade80; background: rgba(74,222,128,0.12); border: 1px solid rgba(74,222,128,0.3); }
.tier-SOLID { color: #facc15; background: rgba(250,204,21,0.12); border: 1px solid rgba(250,204,21,0.3); }
.tier-DEV   { color: #fb923c; background: rgba(251,146,60,0.12); border: 1px solid rgba(251,146,60,0.3); }
.tier-NEEDS { color: #f87171; background: rgba(248,113,113,0.12); border: 1px solid rgba(248,113,113,0.3); }
.score-card-value {
  font-size: 36px;
  font-weight: 900;
  color: #ffffff;
  line-height: 1;
  margin-bottom: 8px;
}
.score-bar-track {
  height: 4px;
  background: rgba(255,255,255,0.08);
  border-radius: 9999px;
  overflow: hidden;
}
.score-bar-fill {
  height: 100%;
  background: linear-gradient(to right, #C00000, #ff6666);
  border-radius: 9999px;
  transition: width 0.6s ease;
}

/* ── Quick stat pills ── */
.quick-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.09);
  border-radius: 10px;
  padding: 10px 8px;
}
.quick-stat-val {
  font-size: 18px;
  font-weight: 800;
  color: #ffffff;
  line-height: 1;
}
.quick-stat-lbl {
  font-size: 9px;
  font-weight: 700;
  color: rgba(255,255,255,0.4);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-top: 3px;
}

/* ── Tabs ── */
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
