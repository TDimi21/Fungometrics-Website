<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from "axios"
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { Table } from './Table/index'
import { Modal } from '@/components/shared'
import { useTeamStore } from '@/store/team.js'
import useChart from '@/composables/useChart.js'
import { TableStart, CompleteIcon, TableStats, TableCancel } from '@/components/icons'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import {toast} from "@/utils/AlertPlugin"
import { useTrainingStore } from "@/store/training";
import { useLiveABStore } from '@/store/liveAB.js'
import { storeToRefs } from 'pinia'


const { axiosDelete, axiosGet } = useAxiosAuth()
const { team } = useTeamStore()
const { getFormatterDate } = useChart()
const router = useRouter()
const activeTraining = useTrainingStore();
const apiBaseUrl = process.env.API_ENDPOINT
const token = JSON.parse(localStorage.getItem('auth')).token
const { livePitches } = storeToRefs(activeTraining)

const primaryTabHeading = ['Batting','Bullpen','Cage', 'Live AB','Velocity','Toss','Balls']

const firtsHeaderTable = [
  {
    title: 'Created At',
    value: 'date',
    slot: 'created',
  },
  {
    title: 'Player Name',
    value: 'lineup[0]',
    slot: 'name',
  },
  {
    title: 'Balls',
    value: 'balls',
    slot: 'balls'
  },
  {
    title: 'Completed',
    value: 'is_completed',
    slot: 'completed'
  },
  {
    title: 'Statistics',
    value: 'id',
    slot: 'stats'
  },
  {
    title: 'Delete',
    value: 'id',
    slot: 'delete'
  }
]

const liveHeaderTable = [
  {
    title: 'Created At',
    value: 'date',
    slot: 'created',
  },
  {
    title: 'Pitcher',
    value: 'lineup[0]',
    slot: 'pitcher',
  },
  {
    title: 'Batter',
    value: 'lineup[0]',
    slot: 'batter',
  },
  {
    title: 'Balls',
    value: 'balls',
    slot: 'balls'
  },
  {
    title: 'Completed',
    value: 'is_completed',
    slot: 'completed'
  },
  {
    title: 'Statistics',
    value: 'id',
    slot: 'stats'
  },
  {
    title: 'Delete',
    value: 'id',
    slot: 'delete'
  }
]

const allData = ref([])
const practiceToDelte = ref(null)
const isOpenModal = ref(false)
const { setStatusPlayers, resetStatusPlayer } = useLiveABStore()

const cageWidth = ref({
  ft: 14,
  inch: 0
})
const cageHeight = ref({
  ft: 14,
  inch: 0
})
const cageLength = ref({
  ft: 65,
  inch: 0
})

const getFeeds = async() => {

  const { data } = await axios.get(apiBaseUrl+'coach/sessions/lasts/'+team.id,{
    headers: {
      "Authorization": `Bearer ${token}`,
      "Content-Type": "application/json",
    },

  })
  allData.value = await data.data
}

const deletePractice = (id) => {
  isOpenModal.value = true
  practiceToDelte.value = id
}

const confirmDelete = async() => {
  try {
    await axiosDelete('training/', practiceToDelte.value).then(async(response) => {
      if (response.data) {

        toast.fire({
          icon: 'success',
          title: 'Practice deleted',
          text: 'Practice successfully deleted',
        })

        getFeeds()
      }
    })
  } catch (error) {
    await toast.fire({
      icon: 'warning',
      title: 'Practice not deleted',
      text: 'Could not remove practice',
    })
  }

  isOpenModal.value = false

}

const resumenTraining = (training, type) => {
  let newActiveTraining = training
  let players = []
  newActiveTraining.lineup.forEach(player => {
    players.push(player)
  });
  newActiveTraining.players = players
  activeTraining.setDataTraining(newActiveTraining);
  router.push('/track/' + type)
}

const resumeCage = async (players) => {
  const newData = await axiosGet('training/'+ players.id)
  let data = {
    id: players.id,
    is_completed: players.is_completed,
    players: newData.data.data.players,
    mode: players.mode,
    note: players.note,
    start: players.start,
    team: players.team,
    type: players.type
  }
  await activeTraining.setDataTraining(data)
  const params = ref({})
  if (newData.data.data.cage_data == "" || newData.data.data.cage_data == undefined) {
    params.value = {
      cageHeight: cageHeight.value.ft,
      lengthCage: cageLength.value.ft,
      widthCage: cageWidth.value.ft,
    }
  } else {
    params.value = {
      cageHeight: newData.data.data.cage_data.height.ft,
      lengthCage: newData.data.data.cage_data.length.ft,
      widthCage: newData.data.data.cage_data.width.ft,
    }
  }
  // console.log(params.value);
  router.push({
    name: "track.trainingCage",
    params: params.value
  });
}

const resumeTrainingMode = async (players, mode) => {
  let data = {
    id: players.id,
    is_completed: players.is_completed,
    players: players.lineup,
    mode: mode,
    note: players.note,
    start: players.start,
    team: players.team,
    type: players.type
  }

  await activeTraining.setDataTraining(data)
  router.push({
    path: '/track/training-mode/' + data.mode,
  });
}

const resumenLive = async(item) => {

  let newActiveTraining = item
  let playersBatters = []
  let playersPitchers = []
  // return
  axiosGet('statistics/'+item.id+'/liveab').then((response)=>{
    resetStatusPlayer()

    // return
    let playersStats = response.data.data.ball_x_ball
    livePitches.value = response.data.data.count

    newActiveTraining.lineup.forEach(player => {
      if (player.batting) {
        playersBatters.push(player)
      } else {
        playersPitchers.push(player)
      }
    })

    activeTraining.cleanListPlayer()

    // return
    const idCount = {}
    const matchingElements = [];

    playersStats.forEach(obj => {
      const batterId = obj.batting.batter_id;
      const pitcherId = obj.pitching.pitcher_id;
      const idPair = `${batterId}-${pitcherId}`;

      if (idCount[idPair]) {
        idCount[idPair]++;
      } else {
        idCount[idPair] = 1;
      }

      matchingElements.push(obj)
    });

    matchingElements.forEach(obj => {
        const batterId = obj.batting.batter_id;
        const pitcherId = obj.pitching.pitcher_id;
        const idPair = `${batterId}-${pitcherId}`;
        const count = idCount[idPair];
        setStatusPlayers( pitcherId, batterId, count)
    });

    /* remove posible counter state of ball and strile */
    localStorage.removeItem('countBall')
    localStorage.removeItem('countStrike')

    newActiveTraining = {
      players: {
        batters: playersBatters,
        pitchers: playersPitchers
      },
      teams: [
        { id: playersStats[0].pitching.team_id },
        { id: playersStats[0].batting.team_id },
      ],
      ...newActiveTraining
    }

    activeTraining.setDataTraining(newActiveTraining)
    router.push('/track/live')
  })
}
getFeeds()

const typeConfig = {
  batting:        { label: 'Batting',       color: '#1a0a2e', accent: '#C00000', emoji: '🏏' },
  bullpen:        { label: 'Bullpen',        color: '#3a0a0a', accent: '#C00000', emoji: '⚾' },
  cage:           { label: 'Cage',           color: '#0a2a0a', accent: '#16a34a', emoji: '🏟️' },
  live:           { label: 'Live AB',        color: '#2a1a00', accent: '#d97706', emoji: '🔴' },
  exit_velocity:  { label: 'Exit Velocity',  color: '#1a002a', accent: '#9333ea', emoji: '💥' },
  long_toss:      { label: 'Long Toss',      color: '#001a2a', accent: '#0ea5e9', emoji: '🎯' },
  weight_ball:    { label: 'Weight Ball',    color: '#1a1a00', accent: '#eab308', emoji: '🏋️' },
}
const getConfig = (type) => typeConfig[type] ?? { label: type, color: '#002060', accent: '#C00000', emoji: '⚾' }
const formatTime = (dateStr) => {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
}
</script>
<template>
  <section class="w-full">
    <tab-group>
      <!-- Tab Pills -->
      <div class="overflow-x-auto mb-4">
        <tab-list class="flex gap-1 p-1 bg-[#001030]/60 rounded-xl w-max">
          <tab as="template" v-slot="{ selected }" v-for="head in primaryTabHeading" :key="head">
            <button
              class="px-4 py-1.5 text-xs font-black uppercase tracking-wider rounded-lg transition-all outline-none"
              :class="selected
                ? 'bg-[#C00000] text-white shadow'
                : 'text-white/50 hover:text-white'"
            >{{ head }}</button>
          </tab>
        </tab-list>
      </div>

      <tab-panels>
        <!-- BATTING -->
        <tab-panel>
          <div class="space-y-3 max-h-[420px] overflow-y-auto pr-1">
            <div v-if="!allData.batting?.length" class="text-white/30 text-sm text-center py-8">No sessions yet</div>
            <div
              v-for="item in allData.batting" :key="item.id"
              class="session-card"
            >
              <div class="session-icon" :style="{ background: getConfig('batting').color }">
                <span class="text-2xl">{{ getConfig('batting').emoji }}</span>
                <span class="session-type-label">{{ getConfig('batting').label }}</span>
              </div>
              <div class="session-body">
                <div class="session-top">
                  <div>
                    <div class="session-title">Batting Practice</div>
                    <div class="session-player">{{ item.lineup?.[0]?.name?.full ?? '—' }}<span v-if="item.lineup?.length > 1"> (+{{ item.lineup.length - 1 }})</span></div>
                  </div>
                  <div class="session-date-block">
                    <div class="session-date">{{ getFormatterDate(item.date) }}</div>
                    <div class="session-time">{{ formatTime(item.date) }}</div>
                  </div>
                </div>
                <div class="session-bottom">
                  <span class="session-balls">Total: {{ item.balls ?? 0 }} balls</span>
                  <div class="session-actions">
                    <button v-if="!item.is_completed" @click="resumenTraining(item, 'batting')" class="action-btn action-resume" title="Resume">▶</button>
                    <button @click="$router.push({ name: 'training.stats', params: { idPractice: item.id, type: item.type } })" class="action-btn action-stats" title="Stats">📊</button>
                    <button @click="deletePractice(item.id)" class="action-btn action-delete" title="Delete">🗑</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </tab-panel>

        <!-- BULLPEN -->
        <tab-panel>
          <div class="space-y-3 max-h-[420px] overflow-y-auto pr-1">
            <div v-if="!allData.bullpen?.length" class="text-white/30 text-sm text-center py-8">No sessions yet</div>
            <div v-for="item in allData.bullpen" :key="item.id" class="session-card">
              <div class="session-icon" :style="{ background: getConfig('bullpen').color }">
                <span class="text-2xl">{{ getConfig('bullpen').emoji }}</span>
                <span class="session-type-label">{{ getConfig('bullpen').label }}</span>
              </div>
              <div class="session-body">
                <div class="session-top">
                  <div>
                    <div class="session-title">Bullpen Practice</div>
                    <div class="session-player">{{ item.lineup?.[0]?.name?.full ?? '—' }}<span v-if="item.lineup?.length > 1"> (+{{ item.lineup.length - 1 }})</span></div>
                  </div>
                  <div class="session-date-block">
                    <div class="session-date">{{ getFormatterDate(item.date) }}</div>
                    <div class="session-time">{{ formatTime(item.date) }}</div>
                  </div>
                </div>
                <div class="session-bottom">
                  <span class="session-balls">Total: {{ item.balls ?? 0 }} balls</span>
                  <div class="session-actions">
                    <button v-if="!item.is_completed" @click="resumenTraining(item, 'bullpen')" class="action-btn action-resume" title="Resume">▶</button>
                    <button @click="$router.push({ name: 'training.stats', params: { idPractice: item.id, type: item.type } })" class="action-btn action-stats" title="Stats">📊</button>
                    <button @click="deletePractice(item.id)" class="action-btn action-delete" title="Delete">🗑</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </tab-panel>

        <!-- CAGE -->
        <tab-panel>
          <div class="space-y-3 max-h-[420px] overflow-y-auto pr-1">
            <div v-if="!allData.cage?.length" class="text-white/30 text-sm text-center py-8">No sessions yet</div>
            <div v-for="item in allData.cage" :key="item.id" class="session-card">
              <div class="session-icon" :style="{ background: getConfig('cage').color }">
                <span class="text-2xl">{{ getConfig('cage').emoji }}</span>
                <span class="session-type-label">{{ getConfig('cage').label }}</span>
              </div>
              <div class="session-body">
                <div class="session-top">
                  <div>
                    <div class="session-title">Cage Session</div>
                    <div class="session-player">{{ item.lineup?.[0]?.name?.full ?? '—' }}<span v-if="item.lineup?.length > 1"> (+{{ item.lineup.length - 1 }})</span></div>
                  </div>
                  <div class="session-date-block">
                    <div class="session-date">{{ getFormatterDate(item.date) }}</div>
                    <div class="session-time">{{ formatTime(item.date) }}</div>
                  </div>
                </div>
                <div class="session-bottom">
                  <span class="session-balls">Total: {{ item.balls ?? 0 }} balls</span>
                  <div class="session-actions">
                    <button v-if="!item.is_completed" @click="resumeCage(item)" class="action-btn action-resume" title="Resume">▶</button>
                    <button @click="$router.push({ name: 'training.statsCage', params: { idPractice: item.id, mode: item.mode } })" class="action-btn action-stats" title="Stats">📊</button>
                    <button @click="deletePractice(item.id)" class="action-btn action-delete" title="Delete">🗑</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </tab-panel>

        <!-- LIVE AB -->
        <tab-panel>
          <div class="space-y-3 max-h-[420px] overflow-y-auto pr-1">
            <div v-if="!allData.live?.length" class="text-white/30 text-sm text-center py-8">No sessions yet</div>
            <div v-for="item in allData.live" :key="item.id" class="session-card">
              <div class="session-icon" :style="{ background: getConfig('live').color }">
                <span class="text-2xl">{{ getConfig('live').emoji }}</span>
                <span class="session-type-label">{{ getConfig('live').label }}</span>
              </div>
              <div class="session-body">
                <div class="session-top">
                  <div>
                    <div class="session-title">Live AB Session</div>
                    <div class="session-player">
                      <template v-for="p in item.lineup" :key="p.id">
                        <span v-if="!p.batting">{{ p.name.full }} </span>
                      </template>
                    </div>
                  </div>
                  <div class="session-date-block">
                    <div class="session-date">{{ getFormatterDate(item.date) }}</div>
                    <div class="session-time">{{ formatTime(item.date) }}</div>
                  </div>
                </div>
                <div class="session-bottom">
                  <span class="session-balls">Total: {{ item.balls ?? 0 }} balls</span>
                  <div class="session-actions">
                    <button v-if="!item.is_completed" @click="resumenLive(item)" class="action-btn action-resume" title="Resume">▶</button>
                    <button @click="$router.push({ name: 'training.statsLiveAB', params: { id: item.id } })" class="action-btn action-stats" title="Stats">📊</button>
                    <button @click="deletePractice(item.id)" class="action-btn action-delete" title="Delete">🗑</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </tab-panel>

        <!-- EXIT VELOCITY -->
        <tab-panel>
          <div class="space-y-3 max-h-[420px] overflow-y-auto pr-1">
            <div v-if="!allData.exit_velocity?.length" class="text-white/30 text-sm text-center py-8">No sessions yet</div>
            <div v-for="item in allData.exit_velocity" :key="item.id" class="session-card">
              <div class="session-icon" :style="{ background: getConfig('exit_velocity').color }">
                <span class="text-2xl">{{ getConfig('exit_velocity').emoji }}</span>
                <span class="session-type-label">{{ getConfig('exit_velocity').label }}</span>
              </div>
              <div class="session-body">
                <div class="session-top">
                  <div>
                    <div class="session-title">Exit Velocity</div>
                    <div class="session-player">{{ item.lineup?.[0]?.name?.full ?? '—' }}<span v-if="item.lineup?.length > 1"> (+{{ item.lineup.length - 1 }})</span></div>
                  </div>
                  <div class="session-date-block">
                    <div class="session-date">{{ getFormatterDate(item.date) }}</div>
                    <div class="session-time">{{ formatTime(item.date) }}</div>
                  </div>
                </div>
                <div class="session-bottom">
                  <span class="session-balls">Total: {{ item.balls ?? 0 }} balls</span>
                  <div class="session-actions">
                    <button v-if="!item.is_completed" @click="resumeTrainingMode(item, 'EV')" class="action-btn action-resume" title="Resume">▶</button>
                    <button @click="$router.push({ name: 'training.statsMode', params: { idPractice: item.id, mode: 'EV' } })" class="action-btn action-stats" title="Stats">📊</button>
                    <button @click="deletePractice(item.id)" class="action-btn action-delete" title="Delete">🗑</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </tab-panel>

        <!-- LONG TOSS -->
        <tab-panel>
          <div class="space-y-3 max-h-[420px] overflow-y-auto pr-1">
            <div v-if="!allData.long_toss?.length" class="text-white/30 text-sm text-center py-8">No sessions yet</div>
            <div v-for="item in allData.long_toss" :key="item.id" class="session-card">
              <div class="session-icon" :style="{ background: getConfig('long_toss').color }">
                <span class="text-2xl">{{ getConfig('long_toss').emoji }}</span>
                <span class="session-type-label">{{ getConfig('long_toss').label }}</span>
              </div>
              <div class="session-body">
                <div class="session-top">
                  <div>
                    <div class="session-title">Long Toss</div>
                    <div class="session-player">{{ item.lineup?.[0]?.name?.full ?? '—' }}<span v-if="item.lineup?.length > 1"> (+{{ item.lineup.length - 1 }})</span></div>
                  </div>
                  <div class="session-date-block">
                    <div class="session-date">{{ getFormatterDate(item.date) }}</div>
                    <div class="session-time">{{ formatTime(item.date) }}</div>
                  </div>
                </div>
                <div class="session-bottom">
                  <span class="session-balls">Total: {{ item.balls ?? 0 }} balls</span>
                  <div class="session-actions">
                    <button v-if="!item.is_completed" @click="resumeTrainingMode(item, 'LT')" class="action-btn action-resume" title="Resume">▶</button>
                    <button @click="$router.push({ name: 'training.statsMode', params: { idPractice: item.id, mode: 'LT' } })" class="action-btn action-stats" title="Stats">📊</button>
                    <button @click="deletePractice(item.id)" class="action-btn action-delete" title="Delete">🗑</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </tab-panel>

        <!-- WEIGHT BALL -->
        <tab-panel>
          <div class="space-y-3 max-h-[420px] overflow-y-auto pr-1">
            <div v-if="!allData.weight_ball?.length" class="text-white/30 text-sm text-center py-8">No sessions yet</div>
            <div v-for="item in allData.weight_ball" :key="item.id" class="session-card">
              <div class="session-icon" :style="{ background: getConfig('weight_ball').color }">
                <span class="text-2xl">{{ getConfig('weight_ball').emoji }}</span>
                <span class="session-type-label">{{ getConfig('weight_ball').label }}</span>
              </div>
              <div class="session-body">
                <div class="session-top">
                  <div>
                    <div class="session-title">Weight Ball</div>
                    <div class="session-player">{{ item.lineup?.[0]?.name?.full ?? '—' }}<span v-if="item.lineup?.length > 1"> (+{{ item.lineup.length - 1 }})</span></div>
                  </div>
                  <div class="session-date-block">
                    <div class="session-date">{{ getFormatterDate(item.date) }}</div>
                    <div class="session-time">{{ formatTime(item.date) }}</div>
                  </div>
                </div>
                <div class="session-bottom">
                  <span class="session-balls">Total: {{ item.balls ?? 0 }} balls</span>
                  <div class="session-actions">
                    <button v-if="!item.is_completed" @click="resumeTrainingMode(item, 'WB')" class="action-btn action-resume" title="Resume">▶</button>
                    <button @click="$router.push({ name: 'training.statsMode', params: { idPractice: item.id, mode: 'WB' } })" class="action-btn action-stats" title="Stats">📊</button>
                    <button @click="deletePractice(item.id)" class="action-btn action-delete" title="Delete">🗑</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </tab-panel>
      </tab-panels>
    </tab-group>
  </section>

  <Modal modalTitle="Confirm delete" :isOpen="isOpenModal">
    <template #content>
      <div><p>Are you sure to delete this training?</p></div>
    </template>
    <template #actions>
      <div class="flex justify-between items-center w-90% mx-auto">
        <button @click="confirmDelete" class="bg-red-500 text-white px-4 py-1 rounded-md">Yes, delete</button>
        <button @click="isOpenModal = false" class="bg-fungo-lightblue px-4 py-1 rounded-md">Cancel</button>
      </div>
    </template>
  </Modal>
</template>

<style scoped>
.session-card {
  display: flex;
  align-items: stretch;
  background: linear-gradient(135deg, #001a40 0%, #001030 100%);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 12px;
  overflow: hidden;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.session-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(0,0,0,0.4);
}
.session-icon {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-width: 72px;
  padding: 12px 8px;
  gap: 4px;
}
.session-type-label {
  font-size: 9px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: rgba(255,255,255,0.7);
  text-align: center;
}
.session-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 10px 12px;
  gap: 8px;
}
.session-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
}
.session-title {
  font-size: 14px;
  font-weight: 800;
  color: white;
  line-height: 1.2;
}
.session-player {
  font-size: 12px;
  color: rgba(255,255,255,0.5);
  margin-top: 2px;
}
.session-date-block {
  text-align: right;
  flex-shrink: 0;
}
.session-date {
  font-size: 11px;
  font-weight: 700;
  color: rgba(255,255,255,0.75);
  white-space: nowrap;
}
.session-time {
  font-size: 10px;
  color: rgba(255,255,255,0.4);
  white-space: nowrap;
}
.session-bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.session-balls {
  font-size: 11px;
  font-weight: 700;
  color: rgba(255,255,255,0.5);
}
.session-actions {
  display: flex;
  gap: 6px;
}
.action-btn {
  font-size: 13px;
  padding: 3px 8px;
  border-radius: 6px;
  border: none;
  cursor: pointer;
  transition: opacity 0.15s;
  background: rgba(255,255,255,0.08);
}
.action-btn:hover { opacity: 0.75; }
.action-resume { background: rgba(0, 160, 80, 0.25); }
.action-stats  { background: rgba(0, 64, 128, 0.35); }
.action-delete { background: rgba(192, 0, 0, 0.25); }

/* scrollbar */
::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); border-radius: 4px; }
::-webkit-scrollbar-thumb { background: #C00000; border-radius: 4px; }
</style>
