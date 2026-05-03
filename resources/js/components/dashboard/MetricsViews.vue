<script setup>
import { InputBase, LabelField} from '@/components/form'
import { ref, reactive, onMounted } from 'vue'
import { TableTop, TableTotal } from './index'

  const props = defineProps({
    item: {
      type: Object,
      required: true
    },
    data: {
      type: Object,
      required: true,
      default: {}
    },
    score: {
      type: Object,
      required: true,
      default: {}
    },
    view: {
      type: String,
      required: true,
      default: 'home'
    },
  })

  onMounted(() => {
    updateInfo()
  })

  let player = reactive({
    avatar: props.item.avatar,
    type: [],
    heightFt: props.item.body.full_height,
    heightInch: 0,
    name: props.item.name.full,
    lastName: props.item.name.last,
    born: '',
    email: '',
    password: '',
    // weight: props.item.body.weight_data,
    mobileNumber: '',
  })

  let dataFitness = ref({
    body_weight: "",
    bench_press: "",
    front_squat: "",
    back_squat: "",
    power_clean: "",
    dead_lift: "",
    yd_40_dash: "",
    yd_60_dash: "",
    id: "",
  })


  const updateInfo = () =>{
    let valueWeight = 0
    let valueBench = 0
    let valueFront = 0
    let valueBack = 0
    let valuePower = 0
    let valueDead = 0
    let value40 = 0
    let value60 = 0

    if (Object.keys(props.data).length != 0) {
      const orderArray = props.data.slice().sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime())
      orderArray.forEach(item => {
        if (item) {
          valueWeight =  item.body_weight == 0 || item.body_weight == undefined ? valueWeight : item.body_weight
          valueBench = item.bench_press == 0 || item.bench_press == undefined ? valueBench : item.bench_press
          valueFront = item.front_squat == 0 || item.front_squat == undefined ? valueFront : item.front_squat
          valueBack = item.back_squat == 0 || item.back_squat == undefined ? valueBack : item.back_squat
          valuePower = item.power_clean == 0 || item.power_clean == undefined ? valuePower : item.power_clean
          valueDead = item.dead_lift == 0 || item.dead_lift == undefined ? valueDead : item.dead_lift
          value40 = item.yd_40_dash == 0 || item.yd_40_dash == undefined ? value40 : item.yd_40_dash
          value60 = item.yd_60_dash == 0 || item.yd_60_dash == undefined ? value60 : item.yd_60_dash
        }
      })

      dataFitness.value = {
        body_weight: valueWeight,
        bench_press: valueBench,
        front_squat: valueFront,
        back_squat: valueBack,
        power_clean: valuePower,
        dead_lift: valueDead,
        yd_40_dash: value40,
        yd_60_dash: value60,
      }
    }
  }

  const boolTop = ref(false);
  const boolTotal = ref(false);
</script>

<template>
  <!-- METRICS TAB -->
  <div v-if="view == 'home'" class="metrics-container">

    <!-- Player header card -->
    <div class="player-header">
      <div class="player-header-left">
        <div class="avatar-wrap">
          <template v-if="item.avatar != null">
            <img :src="player.avatar" alt="" class="avatar-img">
          </template>
          <img v-else src="../../assets/img/layout/logofungo-nav.png" alt="" class="avatar-img">
        </div>
        <div class="player-info">
          <div class="player-name">{{ player.name }}</div>
          <div class="player-detail">Positions: <span class="detail-value">{{ item.positions?.length > 0 ? item.positions.map(p => p.position).join(', ') : '-' }}</span></div>
          <div class="player-detail">Jersey #: <span class="detail-value">{{ item.shirt_number ?? '-' }}</span></div>
          <div class="player-detail">Pitch: <span class="detail-value">{{ item.throw_side ?? item.player?.throw_side ?? '-' }}</span></div>
          <div class="player-detail">Hits: <span class="detail-value">{{ item.hit_side ?? item.player?.hit_side ?? '-' }}</span></div>
          <div class="edit-profile-link" @click="$emit('goEdit')">✏️ Edit Profile</div>
        </div>
      </div>
      <div class="score-box" v-if="score">
        <div class="score-value">{{ score.overall ?? '—' }}</div>
        <div class="score-label">{{ score.level ?? 'DEVELOPING' }}</div>
      </div>
    </div>

    <!-- Last updated -->
    <div class="last-updated" v-if="data?.length > 0">
      Last updated: {{ new Date(data[data.length-1]?.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) }}
    </div>

    <!-- Metrics grid -->
    <div class="metrics-grid">
      <div class="metric-card">
        <div class="metric-label">WEIGHT</div>
        <div class="metric-date" v-if="data?.length > 0">Updated: {{ new Date(data[data.length-1]?.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) }}</div>
        <div class="metric-value">{{ dataFitness.body_weight || '—' }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">FRONT SQUAT</div>
        <div class="metric-date" v-if="data?.length > 0">Updated: {{ new Date(data[data.length-1]?.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) }}</div>
        <div class="metric-value">{{ dataFitness.front_squat || '—' }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">BENCH PRESS</div>
        <div class="metric-date" v-if="data?.length > 0">Updated: {{ new Date(data[data.length-1]?.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) }}</div>
        <div class="metric-value">{{ dataFitness.bench_press || '—' }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">DEADLIFT</div>
        <div class="metric-date" v-if="data?.length > 0">Updated: {{ new Date(data[data.length-1]?.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) }}</div>
        <div class="metric-value">{{ dataFitness.dead_lift || '—' }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">BACK SQUAT</div>
        <div class="metric-date" v-if="data?.length > 0">Updated: {{ new Date(data[data.length-1]?.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) }}</div>
        <div class="metric-value">{{ dataFitness.back_squat || '—' }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">POWER CLEAN</div>
        <div class="metric-date" v-if="data?.length > 0">Updated: {{ new Date(data[data.length-1]?.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) }}</div>
        <div class="metric-value">{{ dataFitness.power_clean || '—' }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">40 TIME</div>
        <div class="metric-date" v-if="data?.length > 0">Updated: {{ new Date(data[data.length-1]?.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) }}</div>
        <div class="metric-value">{{ dataFitness.yd_40_dash || '—' }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">60 TIME</div>
        <div class="metric-date" v-if="data?.length > 0">Updated: {{ new Date(data[data.length-1]?.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) }}</div>
        <div class="metric-value">{{ dataFitness.yd_60_dash || '—' }}</div>
      </div>
    </div>
  </div>

  <div v-if="view == 'top'">
    <div class="flex justify-center">
      <div class="grid grid-cols-3 gap-4 mt-5 max-w-[40%]">
        <button @click="view = 'home'" class="bg-white text-fungo-darkblue border border-fungo-darkblue px-4 py-2 rounded-md">Home</button>
        <button @click="view = 'top'" class="bg-fungo-red text-white border border-fungo-darkblue px-4 py-2 rounded-md">Top 10</button>
        <button @click="view = 'totals'" class="bg-white text-fungo-darkblue border border-fungo-darkblue px-4 py-2 rounded-md">Totals</button>
      </div>
    </div>
    <h1 class="text-2xl capitalize font-medium leading-6 text-fungo-darkblue p-3 text-center font-fungo-800 bg-fungo-gray4 mx-10 mt-5">Top 10 Matrix Log</h1>
    <TableTop :tableData="score" :isLoading="boolTop"></TableTop>
  </div>

  <div v-if="view == 'totals'">
    <div class="flex justify-center">
      <div class="grid grid-cols-3 gap-4 mt-5 max-w-[40%]">
        <button @click="view = 'home'" class="bg-white text-fungo-darkblue border border-fungo-darkblue px-4 py-2 rounded-md">Home</button>
        <button @click="view = 'top'" class="bg-white text-fungo-darkblue border border-fungo-darkblue px-4 py-2 rounded-md">Top 10</button>
        <button @click="view = 'totals'" class="bg-fungo-red text-white border border-fungo-darkblue px-4 py-2 rounded-md">Totals</button>
      </div>
    </div>
    <h1 class="text-2xl capitalize font-medium leading-6 text-fungo-darkblue p-3 text-center font-fungo-800 bg-fungo-gray4 mx-10 mt-5">Totals Matrix Log</h1>
    <TableTotal :tableData="data" :isLoading="boolTotal"></TableTotal>
  </div>
</template>

<style scoped>
.metrics-container {
  background: #111827;
  min-height: 100%;
  padding: 12px;
  color: white;
}
.player-header {
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  align-items: center;
  background: #1f2937;
  border-radius: 10px;
  padding: 12px;
  margin-bottom: 14px;
}
.player-header-left {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 12px;
}
.avatar-img {
  width: 72px;
  height: 72px;
  border-radius: 8px;
  object-fit: cover;
}
.player-name {
  font-size: 18px;
  font-weight: 800;
  color: white;
  margin-bottom: 4px;
}
.player-detail {
  font-size: 12px;
  color: #9ca3af;
  line-height: 1.6;
}
.detail-value {
  color: white;
  font-weight: 600;
}
.edit-profile-link {
  font-size: 12px;
  color: #e10600;
  cursor: pointer;
  margin-top: 4px;
}
.score-box {
  background: #3b82f6;
  border-radius: 8px;
  padding: 12px 16px;
  text-align: center;
  min-width: 70px;
}
.score-value {
  font-size: 28px;
  font-weight: 800;
  color: white;
  line-height: 1;
}
.score-label {
  font-size: 10px;
  color: #dbeafe;
  margin-top: 4px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.last-updated {
  font-size: 12px;
  font-weight: 700;
  color: white;
  margin-bottom: 10px;
  padding: 0 2px;
}
.metrics-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.metric-card {
  background: #1f2937;
  border-radius: 8px;
  padding: 10px 12px;
}
.metric-label {
  font-size: 11px;
  font-weight: 700;
  color: #e10600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 2px;
}
.metric-date {
  font-size: 10px;
  color: #6b7280;
  margin-bottom: 6px;
}
.metric-value {
  background: white;
  color: #111827;
  border-radius: 6px;
  padding: 8px 10px;
  font-size: 15px;
  font-weight: 600;
}
</style>
