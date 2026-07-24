<script setup>
import { ref } from 'vue'
import { TableStats, TableCancel, TableStart } from '@/components/icons'
import { useRouter, useRoute } from 'vue-router'
import { Modal } from '@/components/shared'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import {toast} from "@/utils/AlertPlugin"
import { useTrainingStore } from "@/store/training";
import { usePlayerResume } from '@/composables/usePlayerResume.js'
import { useUserTimezone } from '@/composables/useUserTimezone.js'
import battingCardLogo from '@/assets/img/training/battingbglogo.svg'
import bullpenCardLogo from '@/assets/img/training/bullpenbglogo.svg'
import liveABCardLogo from '@/assets/img/training/liveabbglogo.svg'
import defaultPlayerLogo from '@/assets/img/login/assteslogin/updatedlogo.webp'

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

const emit = defineEmits(["updateList"]);

const { axiosDelete, axiosGet } = useAxiosAuth()
const activeTraining = useTrainingStore();
const {training} = useTrainingStore();
const { resumenBattingPlayer, resumenBullpenPlayer } = usePlayerResume()

const practiceToDelte = ref(null)
const isOpenModal = ref(false)

const tableHeadings = ref([
  "ID", "TEAM LOGO", "TEAM NAME", "BATTERS", "NOTES", "STATUS/CREATED AT", "START/RESUME", "STATS", "DELETE"
])
const tableHeadingsPlayer = ref([
  "ID", "TEAM / PLAYER", "NOTES", "STATUS/CREATED AT", "START/RESUME", "STATS", "DELETE"
])

const deleteTeam = (id) => {
  isOpenModal.value = true
  practiceToDelte.value = id
}

const confirmDelete = async() => {
  try {
    await axiosDelete('training/', practiceToDelte.value).then(async(response) => {
      if (response.data) {

        await toast.fire({
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
const route = useRoute()

const sessionTitle = () => {
  const slug = route.params.slug
  if (slug === 'bullpen') return 'Bullpen Practice'
  if (slug === 'batting') return 'Batting Practice'
  return 'Practice Session'
}

const playerNames = (item) => {
  if (!Array.isArray(item?.lineup) || item.lineup.length === 0) return 'No players assigned'
  return item.lineup.map((player) => player?.player?.name?.full).filter(Boolean).join(', ')
}

const { timezone, zoneLabel } = useUserTimezone()

const formatDateTime = (value) => {
  if (!value) return { date: '-', time: '-' }
  const normalized = typeof value === 'string' ? value.replace(' ', 'T') : value
  const parsed = new Date(normalized)
  if (Number.isNaN(parsed.getTime())) return { date: '-', time: '-' }

  // Show in the user's timezone (from their ZIP; Eastern default), not the browser's.
  return {
    date: parsed.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: timezone.value }),
    time: `${parsed.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', timeZone: timezone.value })} ${zoneLabel.value}`,
  }
}

const createdAtValue = (item) => item?.created_at ?? item?.createdAt ?? item?.date_created ?? item?.started ?? item?.start

const relationLength = (value) => Array.isArray(value) ? value.length : null

const sessionMetric = (item) => {
  const count = Number(
    relationLength(item?.batting) ??
    relationLength(item?.bullpen) ??
    item?.result_count ??
    item?.balls ??
    item?.total_balls ??
    item?.count ??
    item?.pitches ??
    item?.total_pitches ??
    0
  )

  const slug = route.params.slug
  const label = slug === 'batting' ? 'swings' : 'pitches'
  return { label, count: Number.isFinite(count) ? count : 0 }
}

const playerAvatars = (item) => {
  const lineup = Array.isArray(item?.lineup) ? item.lineup : []
  return lineup
    .map((row, idx) => ({
      id: row?.player?.id ?? `p-${idx}`,
      name: row?.player?.name?.full ?? 'Player',
      picture: row?.player?.picture ?? row?.player?.profile?.picture ?? '',
    }))
}

const cardLogoBySlug = () => {
  const slug = route.params.slug
  if (slug === 'bullpen') return bullpenCardLogo
  if (slug === 'live') return liveABCardLogo
  return battingCardLogo
}

const resumenTraining = (training) => {
  if (props.typeUser == 'p'){
    if (route.params.slug == 'batting') return resumenBattingPlayer(training)
    if (route.params.slug == 'bullpen') return resumenBullpenPlayer(training)
  } else {
  
    let newActiveTraining = training
    let players = []

    switch (route.params.slug) {
      case 'bullpen':
          axiosGet('statistics/'+training.id+'/bullpen').then((response)=>{
            let playersStats = response.data.data.by_player
            newActiveTraining.lineup.forEach(player => {
              players.push(player.player)
            });
            activeTraining.cleanListPlayer()
            for (const key in playersStats) {
              if (Object.hasOwnProperty.call(playersStats, key)) {
                const element = playersStats[key];
                activeTraining.addPLayerInfo(key, {
                  'pitch': element.length,
                })
              }
            }
            newActiveTraining.players = players
            activeTraining.setDataTraining(newActiveTraining);
            router.push('/track/' + route.params.slug)
          }).catch(function(error) {
            if (error.response.status == 404) {
              newActiveTraining.lineup.forEach(player => {
                players.push(player.player)
              });
              activeTraining.cleanListPlayer()
              newActiveTraining.players = players
              activeTraining.setDataTraining(newActiveTraining);
              router.push('/track/' + route.params.slug)
            }
          });
        break;
      case 'batting':
        axiosGet('statistics/'+training.id+'/batting').then((response)=>{
          let playersStats = response.data.data.by_player
          newActiveTraining.lineup.forEach(player => {
            players.push(player.player)
          });
          activeTraining.cleanListPlayer()
          for (const key in playersStats) {
            if (Object.hasOwnProperty.call(playersStats, key)) {
              const element = playersStats[key];
              activeTraining.addPLayerInfo(key, {
                'balls': element.length,
              })
            }
          }
          newActiveTraining.players = players
          activeTraining.setDataTraining(newActiveTraining);
          router.push('/track/' + route.params.slug)
        }).catch(function(error) {
          console.log(error);
          if (error.response.status == 404) {
            newActiveTraining.lineup.forEach(player => {
              players.push(player.player)
            });
            activeTraining.cleanListPlayer()
            newActiveTraining.players = players
            activeTraining.setDataTraining(newActiveTraining);
            router.push('/track/' + route.params.slug)
          }
        });
      break;

      default:
          newActiveTraining.lineup.forEach(player => {
            players.push(player.player)
          });
          newActiveTraining.players = players
          activeTraining.setDataTraining(newActiveTraining);
          router.push('/track/' + route.params.slug)
        break;
    }
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
          <h3 class="practice-title">{{ sessionTitle() }}</h3>

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

          <p v-else class="practice-subtitle">{{ playerNames(item) }}</p>

          <div class="ball-badge">Total: {{ sessionMetric(item).count }} {{ sessionMetric(item).label }}</div>
        </div>

        <div class="practice-card-right">
          <span class="status-dot" :class="{ completed: item.is_completed }"></span>
          <p class="practice-date">{{ formatDateTime(createdAtValue(item)).date }}</p>
          <p class="practice-time">{{ formatDateTime(createdAtValue(item)).time }}</p>

          <div class="card-actions">
            <button
              v-if="!item.is_completed"
              @click.prevent="resumenTraining(item)"
              class="card-action-btn"
              title="Resume"
            >
              <TableStart />
            </button>

            <button
              @click="router.push({ name: 'training.stats', params: { 'idPractice': item.id, 'type': item.type, 'isComplete': item.is_completed } })"
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
