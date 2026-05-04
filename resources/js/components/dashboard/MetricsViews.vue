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

  // Returns { raw, pct, dir: 'good'|'bad'|'same' } over last 6 months
  // lowerBetter: weight, dash times
  const getChange = (key, lowerBetter = false) => {
    if (!props.data?.length) return null
    const sixMonthsAgo = new Date()
    sixMonthsAgo.setMonth(sixMonthsAgo.getMonth() - 6)
    const valid = props.data
      .filter(r => r[key] != null && parseFloat(r[key]) > 0)
      .slice()
      .sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
    const inWindow = valid.filter(r => new Date(r.created_at) >= sixMonthsAgo)
    if (inWindow.length < 2) return null
    const oldest = parseFloat(inWindow[0][key])
    const latest = parseFloat(inWindow[inWindow.length - 1][key])
    if (oldest === 0) return null
    const raw = latest - oldest
    const pct = Math.abs(raw / oldest * 100).toFixed(1)
    const improved = lowerBetter ? raw < 0 : raw > 0
    return { raw, pct, dir: raw === 0 ? 'same' : (improved ? 'good' : 'bad') }
  }
</script>

<template>
  <!-- METRICS TAB -->
  <div v-if="view == 'home'" class="metrics-container">

    <!-- Metrics grid -->
    <div class="metrics-grid">
      <div class="metric-card" v-for="m in [
        { key:'body_weight',  label:'Weight',      unit:'lb', lowerBetter:true  },
        { key:'front_squat',  label:'Front Squat', unit:'lb', lowerBetter:false },
        { key:'bench_press',  label:'Bench Press', unit:'lb', lowerBetter:false },
        { key:'dead_lift',    label:'Deadlift',    unit:'lb', lowerBetter:false },
        { key:'back_squat',   label:'Back Squat',  unit:'lb', lowerBetter:false },
        { key:'power_clean',  label:'Power Clean', unit:'lb', lowerBetter:false },
        { key:'yd_40_dash',   label:'40 Time',     unit:'s',  lowerBetter:true  },
        { key:'yd_60_dash',   label:'60 Time',     unit:'s',  lowerBetter:true  },
      ]" :key="m.key">
        <div class="metric-label">{{ m.label }}</div>
        <div class="metric-date" v-if="data?.length > 0">
          Updated: {{ new Date(data.filter(r=>r[m.key]>0).slice().sort((a,b)=>new Date(b.created_at)-new Date(a.created_at))[0]?.created_at ?? data[data.length-1]?.created_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) }}
        </div>
        <div class="metric-value">{{ dataFitness[m.key] || '—' }} <span v-if="dataFitness[m.key]" class="metric-unit">{{ m.unit }}</span></div>
        <div v-if="getChange(m.key, m.lowerBetter)" class="metric-change" :class="'change-' + getChange(m.key, m.lowerBetter).dir">
          {{ getChange(m.key, m.lowerBetter).raw > 0 ? '+' : '' }}{{ getChange(m.key, m.lowerBetter).raw.toFixed(1) }}
        </div>
        <div v-else-if="data?.length > 1" class="metric-change change-same">—</div>
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
  background: #001440;
  min-height: 100%;
  padding: 12px;
  color: white;
}
.player-header {
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  align-items: center;
  background: #002060;
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
  color: #C00000;
  cursor: pointer;
  margin-top: 4px;
}
.score-box {
  background: #004080;
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
  color: #a0c0e0;
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
  background: #002060;
  border-radius: 8px;
  padding: 10px 12px;
}
.metric-label {
  font-size: 11px;
  font-weight: 700;
  color: #ffffff;
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
  background: #e8eef6;
  color: #001440;
  border-radius: 6px;
  padding: 8px 10px;
  font-size: 15px;
  font-weight: 600;
}
.metric-unit {
  font-size: 11px;
  font-weight: 500;
  color: #6b7280;
}
.metric-change {
  font-size: 13px;
  font-weight: 700;
  margin-top: 5px;
  text-align: center;
}
.change-good { color: #4ade80; }
.change-bad  { color: #f87171; }
.change-same { color: #6b7280; }
</style>
