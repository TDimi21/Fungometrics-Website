<script setup>
import { ref, onMounted } from 'vue'
import { TableStats, TableCancel, TableStart } from '@/components/icons'
import { useRouter } from 'vue-router'
import { Modal } from '@/components/shared'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import {toast} from "@/utils/AlertPlugin"
import { useTrainingStore } from "@/store/training";
import { storeToRefs } from 'pinia'
import { useLiveABStore } from '@/store/liveAB.js'
import liveABCardLogo from '@/assets/img/training/liveabbglogo.svg'
import defaultPlayerLogo from '@/assets/img/login/assteslogin/updatedlogo.png'

const props = defineProps({
  tableData: {
    type: Object,
    required: true
  },
  isLoading: {
    type: Boolean,
    required: true
  },
  typeTrainig: {
    type: String,
    required: true
  }
})

const emit = defineEmits(["updateList"]);
const trainingStore = useTrainingStore()

const { axiosDelete, axiosGet } = useAxiosAuth()
const activeTraining = useTrainingStore()
const { livePitches } = storeToRefs(trainingStore)
const { setStatusPlayers, resetStatusPlayer } = useLiveABStore()

const practiceToDelte = ref(null)
const isOpenModal = ref(false)
// const newActiveLiveAB = ref()

const tableHeadings = ref([
  "ID", "PITCHER / NAME TEAM", "pitchers", "batter / NAME TEAM", "batters", "status", "START/RESUME", "STATS", "DELETE"
])

const deleteTeam = (id) => {
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

        emit('updateList')
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

const router = useRouter()

const sessionTitle = () => 'Live AB Practice'

const formatDateTime = (value) => {
  if (!value) return { date: '-', time: '-' }
  const normalized = typeof value === 'string' ? value.replace(' ', 'T') : value
  const parsed = new Date(normalized)
  if (Number.isNaN(parsed.getTime())) return { date: '-', time: '-' }

  return {
    date: parsed.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
    time: parsed.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }),
  }
}

const createdAtValue = (item) => item?.created_at ?? item?.createdAt ?? item?.date_created ?? item?.started ?? item?.start

const pitchCount = (item) => {
  const count = Number(
    (Array.isArray(item?.live) ? item.live.length : null) ??
    item?.result_count ??
    item?.count ??
    item?.balls ??
    item?.total_balls ??
    item?.pitches ??
    item?.total_pitches ??
    0
  )
  return Number.isFinite(count) ? count : 0
}

const nameList = (arr) => {
  if (!Array.isArray(arr) || arr.length === 0) return '—'
  return arr.map((p) => p?.player?.name?.full).filter(Boolean).join(', ')
}

const liveSubtitle = (item) => {
  const pitchers = nameList(item?.players?.pitchers)
  const batters = nameList(item?.players?.batters)
  return `P: ${pitchers} · B: ${batters}`
}

const liveAvatars = (item) => {
  const pitchers = Array.isArray(item?.players?.pitchers) ? item.players.pitchers : []
  const batters = Array.isArray(item?.players?.batters) ? item.players.batters : []
  const all = [...pitchers, ...batters]
  return all.map((row, idx) => ({
    id: row?.player?.id ?? `p-${idx}`,
    name: row?.player?.name?.full ?? 'Player',
    picture: row?.player?.picture ?? row?.player?.profile?.picture ?? '',
  }))
}

// const resumenPractice = async(practiceId) => {

//   let newActiveLiveAB = props.tableData.find(item => item.id == practiceId)

//   let battings = []
//   let pitchers = []

//   newActiveLiveAB.players.batters.forEach(batter => {
//     batter.player.sort = batter.sort
//     battings.push(batter.player)
//   })

//   newActiveLiveAB.players.pitchers.forEach(pitcher => {
//     pitcher.player.sort = pitcher.sort
//     pitchers.push(pitcher.player)
//   })

//   router.push('/track/live')
//   newActiveLiveAB.players['batters'] = await battings
//   newActiveLiveAB.players['pitchers'] = await pitchers


//   await activeTraining.setDataTraining(newActiveLiveAB);
// }
const resumenPractice = (training) => {
  let newActiveTraining = training
  let playersBatters = []
  let playersPitchers = []
  // console.log(livePitches);
  // console.log('dd', newActiveTraining);
  axiosGet('statistics/'+training.id+'/liveab').then((response)=>{
    resetStatusPlayer()

    let playersStats = response.data.data.ball_x_ball
    livePitches.value = response.data.data.count
    // return
    newActiveTraining.players.batters.forEach(player => {
      playersBatters.push(player.player)
    });
    newActiveTraining.players.pitchers.forEach(player => {
      playersPitchers.push(player.player)
    });

    activeTraining.cleanListPlayer()

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

    newActiveTraining.players['batters'] = playersBatters
    newActiveTraining.players['pitchers'] = playersPitchers
    // delete newActiveTraining.lineup
    activeTraining.setDataTraining(newActiveTraining);
    router.push('/track/live')
  })
}

const statsLiveAB = (practiceId) => {
  let newActiveLiveAB = props.tableData.find(item => item.id == practiceId)

  activeTraining.setDataTraining(newActiveLiveAB);
  router.push({ name: 'training.statsLiveAB', params: { 'id': newActiveLiveAB.id } })
}
</script>

<template>
  <section class="practice-list-container mt-6">
    <div v-if="props.isLoading" class="list-state">Loading data...</div>
    <div v-else-if="!props.tableData.length > 0" class="list-state">No found data</div>

    <div v-else class="practice-card-list">
      <article
        v-for="(item, index) in props.tableData"
        :key="index"
        class="practice-card"
      >
        <div class="practice-card-left">
          <div v-if="liveAvatars(item).length" class="practice-avatar-strip">
            <img
              v-for="(p, avatarIndex) in liveAvatars(item).slice(0, 5)"
              :key="`${p.id}-${avatarIndex}`"
              :src="p.picture || defaultPlayerLogo"
              :alt="p.name"
              :title="p.name"
              class="practice-avatar"
            />
            <span v-if="liveAvatars(item).length > 5" class="practice-avatar-more">+{{ liveAvatars(item).length - 5 }}</span>
          </div>
          <div class="practice-card-body">
            <h3 class="practice-title">{{ sessionTitle() }}</h3>
            <div class="practice-meta-row">
              <p class="practice-subtitle">{{ liveSubtitle(item) }}</p>
              <div class="ball-badge">Total: {{ pitchCount(item) }} pitches</div>
            </div>
            <p class="practice-note">
              {{ item?.teams?.[0]?.name ?? 'Team A' }} vs {{ item?.teams?.[1]?.name ?? 'Team B' }}
            </p>
          </div>
          <img :src="liveABCardLogo" alt="Practice" class="practice-thumb">
        </div>

        <div class="practice-card-right">
          <span class="status-dot" :class="{ completed: item.is_completed }"></span>
          <p class="practice-date">{{ formatDateTime(createdAtValue(item)).date }}</p>
          <p class="practice-time">{{ formatDateTime(createdAtValue(item)).time }}</p>

          <div class="card-actions">
            <button
              v-if="!item.is_completed"
              @click.prevent="resumenPractice(item)"
              class="card-action-btn"
              title="Resume"
            >
              <TableStart />
            </button>

            <button
              @click.prevent="statsLiveAB(item.id)"
              class="card-action-btn"
              title="Stats"
            >
              <TableStats />
            </button>

            <button
              @click="deleteTeam(item.id)"
              class="card-action-btn"
              title="Delete"
            >
              <TableCancel />
            </button>
          </div>
        </div>
      </article>
    </div>
  </section>

  <Modal
    modalTitle="Confirm delete"
    :isOpen="isOpenModal"
  >
    <template #content>
      <div>
        <p>Are you sure to delete this training?</p>
      </div>
    </template>
    <template #actions>
        <div class="flex justify-between items-center w-90% mx-auto">
          <button
            @click="confirmDelete"
            class="bg-red-500 text-white px-4 py-1 rounded-md"
          >
            Yes, delete
          </button>

          <button
            @click=" isOpenModal = false"
            class="bg-fungo-lightblue px-4 py-1 rounded-md"
          >
            Cancel
          </button>

        </div>
      </template>
  </Modal>
</template>

<style scoped>
.practice-list-container {
  width: 100%;
}

.list-state {
  color: #dce5ff;
  text-align: center;
  font-size: 1.15rem;
  padding: 2rem 0;
}

.practice-card-list {
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

.practice-card {
  position: relative;
  display: grid;
  grid-template-columns: 170px 1fr 230px;
  gap: 1rem;
  align-items: center;
  border-radius: 16px;
  border: 1px solid rgba(208, 220, 255, 0.26);
  background: rgba(57, 63, 111, 0.58);
  padding: 1rem;
}

.practice-card-left {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.45rem;
}

.practice-thumb {
  width: 160px;
  height: 80px;
  border-radius: 10px;
  object-fit: cover;
}

.ball-badge {
  background: rgba(27, 35, 65, 0.9);
  color: #f0f4ff;
  border-radius: 999px;
  padding: 0.25rem 0.8rem;
  font-size: 0.95rem;
  font-weight: 600;
  white-space: nowrap;
}

.practice-meta-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
}

.practice-title {
  color: #ffffff;
  font-size: 2rem;
  font-weight: 700;
  line-height: 1.2;
  text-align: left;
}

.practice-subtitle {
  color: #c7d0ea;
  font-size: 1.35rem;
  line-height: 1.35;
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.practice-note {
  color: #9aaad1;
  margin-top: 0.25rem;
}

.practice-avatar-strip {
  display: flex;
  align-items: center;
  margin-bottom: 0.35rem;
}

.practice-avatar {
  width: 90px;
  height: 90px;
  border-radius: 999px;
  border: 2px solid rgba(255, 255, 255, 0.65);
  object-fit: cover;
  margin-left: -10px;
  background: rgba(7, 12, 25, 0.95);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.45);
}

.practice-avatar:first-child {
  margin-left: 0;
}

.practice-avatar-more {
  margin-left: 8px;
  color: #c7d0ea;
  font-size: 0.8rem;
  font-weight: 700;
}

.practice-card-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.15rem;
}

.status-dot {
  width: 12px;
  height: 12px;
  border-radius: 999px;
  background: #ff4b4b;
}

.status-dot.completed {
  background: #39d98a;
}

.practice-date {
  color: #f3f6ff;
  font-size: 1.35rem;
  font-weight: 700;
}

.practice-time {
  color: #d1d8ef;
  font-size: 1.2rem;
}

.card-actions {
  margin-top: 0.5rem;
  display: flex;
  gap: 0.5rem;
}

.card-action-btn {
  border-radius: 999px;
  padding: 0.35rem;
  background: rgba(255, 255, 255, 0.1);
}

@media (max-width: 1024px) {
  .practice-card {
    grid-template-columns: 1fr;
    align-items: flex-start;
  }

  .practice-card-right {
    align-items: flex-start;
  }

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
  @apply bg-fungo-darkblue-hover rounded-md;
}

::-webkit-scrollbar-thumb:active {
  @apply bg-fungo-darkblue;
}
::-webkit-scrollbar-track {
  border: 22px solid #918383;
  @apply bg-fungo-dark-gray rounded-md;
}
::-webkit-scrollbar-corner {
  background: transparent;
}
</style>
