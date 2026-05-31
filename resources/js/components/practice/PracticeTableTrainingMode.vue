<script setup>
import { ref } from 'vue'
import { TableStats, TableCancel, TableStart } from '@/components/icons'
import { useRouter } from 'vue-router'
import {useTrainingStore} from "../../store/training";
import {Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot} from '@headlessui/vue'
import { Modal } from '@/components/shared'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import {toast} from "@/utils/AlertPlugin"
import { usePlayerResume } from '@/composables/usePlayerResume.js'
import defaultPlayerLogo from '@/assets/img/login/assteslogin/updatedlogo.png'

const activeTraining = useTrainingStore()

const props = defineProps({
  tableData: {
    type: Object,
    required: true
  },
  teamData: {
    type: Object,
    required: true
  },
  isLoading: {
    type: Boolean,
    required: true
  },
  typeUser: {
    type: String,
    required: false,
    default: 'c'
  }
})

const { resumenModePlayer } = usePlayerResume()

const emit = defineEmits(["updateList"]);

const { axiosDelete,axiosGet } = useAxiosAuth()

const practiceToDelte = ref(null)
const isOpenModal = ref(false)

const tableHeadings = ref([
  "ID", "TEAM LOGO", "TEAM NAME", "BATTERS", "NOTES", "MODE TYPE", "STATUS/CREATED AT", "START/RESUME", "STATS", "DELETE"
])

const tableHeadingsPlayer = ref([
  "ID", "TEAM / PLAYER", "Mode", "NOTES", "STATUS/CREATED AT", "START/RESUME", "STATS", "DELETE"
])

const isOpen = ref(false)

const router = useRouter()

const sessionTitle = (item) => {
  const mode = String(item?.mode ?? item?.modes ?? '').toUpperCase()
  if (mode === 'EV') return 'Exit Velocity Practice'
  if (mode === 'LT') return 'Long Toss Practice'
  if (mode === 'WB') return 'Weighted Balls Practice'
  return 'Training Mode Practice'
}

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
    (Array.isArray(item?.long_toss) ? item.long_toss.length : null) ??
    (Array.isArray(item?.longToss) ? item.longToss.length : null) ??
    (Array.isArray(item?.exit_velocity) ? item.exit_velocity.length : null) ??
    (Array.isArray(item?.exitVelocity) ? item.exitVelocity.length : null) ??
    (Array.isArray(item?.weight_ball) ? item.weight_ball.length : null) ??
    (Array.isArray(item?.weightBall) ? item.weightBall.length : null) ??
    item?.result_count ??
    item?.balls ??
    item?.total_balls ??
    item?.count ??
    item?.pitches ??
    item?.total_pitches ??
    0
  )
  return Number.isFinite(count) ? count : 0
}

const playerNames = (item) => {
  if (!Array.isArray(item?.lineup) || item.lineup.length === 0) return 'No players assigned'
  return item.lineup.map((player) => player?.player?.name?.full).filter(Boolean).join(', ')
}

const playerAvatars = (item) => {
  const lineup = Array.isArray(item?.lineup) ? item.lineup : []
  return lineup.map((row, idx) => ({
    id: row?.player?.id ?? `p-${idx}`,
    name: row?.player?.name?.full ?? 'Player',
    picture: row?.player?.picture ?? row?.player?.profile?.picture ?? '',
  }))
}

const resumePractice = async (players) => {
  if (props.typeUser == 'p') return resumenModePlayer(players)
  
  let data = {
    id: players.id,
    is_completed: players.is_completed,
    players: players.lineup.map((item) => item.player),
    mode: players.mode,
    note: players.note,
    start: players.start,
    team: players.team,
    type: players.type
  }

  let modes = {
    'EV' : 'exitvelocity',
    'LT': 'longtoss',
    'WB': 'weightball'
  }

  await activeTraining.setDataTraining(data)


  axiosGet('statistics/'+players.id+"/"+modes[data.mode]).then((response)=>{
    let playersStats = response.data.data.by_player
    activeTraining.cleanListPlayer()
    /* TODO: Logica para identifcar el set mas alto por jugador
    for (const key in playersStats) {
      if (Object.hasOwnProperty.call(playersStats, key)) {
        const element = playersStats[key];
        activeTraining.addPLayerInfo(key, {
          'balls': element.length,
        })
      }
    }*/
    data.players.forEach(item => {
      let data = {
        "balls": 0,
        "bxs": 0,
        "set": 1
      }
      activeTraining.countThrowArray[item.id] = data
    })
    router.push({
      path: '/track/training-mode/' + data.mode,
    });
  }).catch((error)=>{
    activeTraining.cleanListPlayer()
    data.players.forEach(item => {
      let data = {
        "throw": 0,
        "throwForSet": 0,
        "set": 1
      }
      activeTraining.countThrowArray[item.id] = data
    })
    router.push({
      path: '/track/training-mode/' + data.mode,
    });
  })
}

const deleteTeam = (id) => {
  isOpenModal.value = true
  practiceToDelte.value = id
}

const getTypeMode = (mode) => {
  let typeMode = ""
  switch (mode) {
    case "EV":
      typeMode = "Exit Velocity"
      break;
    case "LT":
      typeMode = "Long Toss"
      break;
    case "WB":
      typeMode = "Weighted Balls"
      break;
    default:
      typeMode = "Training Mode"
      break;
  }

  return typeMode
}

const confirmDelete = async() => {
  try {
    await axiosDelete('training/', practiceToDelte.value).then(async(response) => {
      if (response.data) {
        emit('updateList')
        isOpenModal.value = false
        await toast.fire({
          icon: 'success',
          title: 'Practice deleted',
          text: 'Practice successfully deleted',
        })
      }
    })
  } catch (error) {
    isOpenModal.value = false
    await toast.fire({
      icon: 'warning',
      title: 'Practice not deleted',
      text: 'Could not remove practice',
    })
  }
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
          <h3 class="practice-title">{{ sessionTitle(item) }}</h3>

          <div v-if="playerAvatars(item).length" class="practice-avatar-strip">
            <div
              v-for="(p, avatarIndex) in playerAvatars(item).slice(0, 5)"
              :key="`${p.id}-${avatarIndex}`"
              class="player-chip"
            >
              <img
                :src="p.picture || defaultPlayerLogo"
                :alt="p.name"
                :title="p.name"
                class="practice-avatar"
              />
              <p class="player-chip-name">{{ p.name }}</p>
            </div>
            <span v-if="playerAvatars(item).length > 5" class="practice-avatar-more">+{{ playerAvatars(item).length - 5 }}</span>
          </div>

          <p v-else class="practice-subtitle">{{ props.typeUser == 'c' ? playerNames(item) : (item?.team?.name ?? 'Personal practice') }}</p>

          <div class="practice-meta-row">
            <div class="ball-badge">Total: {{ pitchCount(item) }} pitches</div>
          </div>
        </div>

        <div class="practice-card-right">
          <span class="status-dot" :class="{ completed: item.is_completed }"></span>
          <p class="practice-date">{{ formatDateTime(createdAtValue(item)).date }}</p>
          <p class="practice-time">{{ formatDateTime(createdAtValue(item)).time }}</p>

          <div class="card-actions">
            <button
              v-if="!item.is_completed"
              @click.prevent="resumePractice(item)"
              class="card-action-btn"
              title="Resume"
            >
              <TableStart />
            </button>

            <button
              v-if="(item.mode ?? item.modes) != 'HP'"
              @click="router.push({ name: 'training.statsMode', params: { 'idPractice': item.id, 'mode': item.mode ?? item.modes, 'isComplete': item.is_completed } })"
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
  </section>
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
  grid-template-columns: 1fr 230px;
  gap: 1rem;
  align-items: flex-start;
  border-radius: 16px;
  border: 1px solid rgba(208, 220, 255, 0.26);
  background: rgba(57, 63, 111, 0.58);
  padding: 1rem;
}

.practice-card-left {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.6rem;
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

.practice-avatar-strip {
  display: flex;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 0.6rem;
}

.practice-avatar {
  width: 90px;
  height: 90px;
  border-radius: 999px;
  border: 2px solid rgba(255, 255, 255, 0.65);
  object-fit: cover;
  background: rgba(7, 12, 25, 0.95);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.45);
}

.player-chip {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  max-width: 90px;
}

.player-chip-name {
  color: #d7dff6;
  font-size: 0.72rem;
  line-height: 1.2;
  text-align: center;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  width: 100%;
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
