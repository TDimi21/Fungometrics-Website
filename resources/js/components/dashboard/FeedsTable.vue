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
</script>
<template>
  <section class="bg-white rounded-3xl py-9 px-3 w-full">
    <h2 class="text-fungo-red font-fungo-700 text-3xl">Feeds</h2>
    <div class="grid grid-cols-6">

    </div>
    <tab-group>
      <div class="overflow-x-auto w-auto">
        <tab-list class="flex justify-center items-center py-4 w-max">
          <tab
            as="template"
            v-slot="{ selected }"
            v-for="head in primaryTabHeading"
          >
            <button
              class="outline-none py-2 px-6 !mx-0"
              :class="{ ' text-fungo-red font-fungo-500 border-b-[3px] border-fungo-red': selected, 'text-fungo-darkblue': !selected }"
            >
              {{ head }}
            </button>
          </tab>
        </tab-list>
      </div>
      <tab-panels>
        <!-- batting -->
        <tab-panel>
          <Table :header="firtsHeaderTable" :items="allData.batting">
            <template #created="{item}">
              <span>{{ getFormatterDate(item.date) }}</span>
            </template>
            <template #name="{ item }">
              {{ item.lineup[0].name.full }}
            </template>
            <template #completed="{ item }">
              <button v-if="item.is_completed" disabled>
                <CompleteIcon />
              </button>
              <button v-else @click="resumenTraining(item, 'batting')">
                <TableStart class="[&>path]:fill-fungo-blue"/>
              </button>
            </template>
            <template #stats="{ item }">
              <button
                @click="$router.push({ name: 'training.stats', params: { 'idPractice': item.id, 'type': item.type } })"
              >
                <TableStats />
              </button>
            </template>
            <template #delete="{ item }">
              <button @click="deletePractice(item.id)">
                <TableCancel />
              </button>
            </template>
          </Table>
        </tab-panel>

        <!-- bullpen -->
        <tab-panel>
          <Table :header="firtsHeaderTable" :items="allData.bullpen">
            <template #created="{item}">
              <span>{{ getFormatterDate(item.date) }}</span>
            </template>
            <template #name="{ item }">
              {{ item.lineup[0].name.full }}
            </template>
            <template #completed="{ item }">
              <button v-if="item.is_completed" disabled>
                <CompleteIcon />
              </button>
              <button v-else @click="resumenTraining(item, 'bullpen')">
                <TableStart class="[&>path]:fill-fungo-blue"/>
              </button>
            </template>
            <template #stats="{ item }">
              <button
              @click="$router.push({ name: 'training.stats', params: { 'idPractice': item.id, 'type': item.type } })"
              >
                <TableStats />
              </button>
            </template>
            <template #delete="{ item }">
              <button @click="deletePractice(item.id)">
                <TableCancel />
              </button>
            </template>
          </Table>
        </tab-panel>

        <!-- cage mode -->
        <tab-panel>
          <Table :header="firtsHeaderTable" :items="allData.cage">
            <template #created="{item}">
              <span>{{ getFormatterDate(item.date) }}</span>
            </template>
            <template #name="{ item }">
              {{ item.lineup[0].name.full }}
            </template>
            <template #completed="{ item }">
              <button v-if="item.is_completed" disabled>
                <CompleteIcon />
              </button>
              <button v-else @click="resumeCage(item)">
                <TableStart class="[&>path]:fill-fungo-blue"/>
              </button>
            </template>
            <template #stats="{ item }">
              <button
              @click="$router.push({ name: 'training.statsCage', params: { 'idPractice': item.id, 'mode': item.mode } })"
              >
                <TableStats />
              </button>
            </template>
            <template #delete="{ item }">
              <button @click="deletePractice(item.id)">
                <TableCancel />
              </button>
            </template>
          </Table>
        </tab-panel>

        <!-- live AB -->
        <tab-panel>
          <Table :header="liveHeaderTable" :items="allData.live">
            <template #created="{item}">
              <span>{{ getFormatterDate(item.date) }}</span>
            </template>
            <template #pitcher="{ item }">
              <template v-for="name in item.lineup" :key="name.id">
                {{ name.batting == false ? name.name.full + ', ' : ''}}
                <!-- {{ name.batting }} -->
              </template>
            </template>
            <template #batter="{ item }" class="max-w-[50px]">
              <template v-for="name in item.lineup" :key="name.id">
                {{ name.batting ? name.name.full + ', ' : ''}}
              </template>
            </template>
            <template #completed="{ item }">
              <button v-if="item.is_completed" disabled>
                <CompleteIcon />
              </button>
              <button v-else @click="resumenLive(item)">
                <TableStart class="[&>path]:fill-fungo-blue"/>
              </button>
            </template>
            <template #stats="{ item }">
              <button
                @click="$router.push({ name: 'training.statsLiveAB', params: { 'id': item.id } })"
              >
                <TableStats />
              </button>
            </template>
            <template #delete="{ item }">
              <button @click="deletePractice(item.id)">
                <TableCancel />
              </button>
            </template>
          </Table>
        </tab-panel>

        <!-- exit velocity -->
        <tab-panel>
          <Table :header="firtsHeaderTable" :items="allData.exit_velocity">
            <template #created="{item}">
              <span>{{ getFormatterDate(item.date) }}</span>
            </template>
            <template #name="{ item }">
              {{ item.lineup[0].name.full }}
            </template>
            <template #completed="{ item }">
              <button v-if="item.is_completed" disabled>
                <CompleteIcon />
              </button>
              <button v-else @click="resumeTrainingMode(item, 'EV')">
                <TableStart class="[&>path]:fill-fungo-blue"/>
              </button>
            </template>
            <template #stats="{ item }">
              <button
                @click="$router.push({ name: 'training.statsMode', params: { 'idPractice': item.id, 'mode': 'EV' } })"
              >
                <TableStats />
              </button>
            </template>
            <template #delete="{ item }">
              <button @click="deletePractice(item.id)">
                <TableCancel />
              </button>
            </template>
          </Table>
        </tab-panel>

        <!-- long toss -->
        <tab-panel>
          <Table :header="firtsHeaderTable" :items="allData.long_toss">
            <template #created="{item}">
              <span>{{ getFormatterDate(item.date) }}</span>
            </template>
            <template #name="{ item }">
              {{ item.lineup[0].name.full }}
            </template>
            <template #completed="{ item }">
              <button v-if="item.is_completed" disabled>
                <CompleteIcon />
              </button>
              <button v-else @click="resumeTrainingMode(item, 'LT')">
                <TableStart class="[&>path]:fill-fungo-blue"/>
              </button>
            </template>
            <template #stats="{ item }">
              <button
                @click="$router.push({ name: 'training.statsMode', params: { 'idPractice': item.id, 'mode': 'LT' } })"
              >
                <TableStats />
              </button>
            </template>
            <template #delete="{ item }">
              <button @click="deletePractice(item.id)">
                <TableCancel />
              </button>
            </template>
          </Table>
        </tab-panel>

        <!-- weight ball -->
        <tab-panel>
          <Table :header="firtsHeaderTable" :items="allData.weight_ball">
            <template #created="{item}">
              <span>{{ getFormatterDate(item.date) }}</span>
            </template>
            <template #name="{ item }">
              {{ item.lineup[0].name.full }}
            </template>
            <template #completed="{ item }">
              <button v-if="item.is_completed" disabled>
                <CompleteIcon />
              </button>
              <button v-else @click="resumeTrainingMode(item, 'WB')">
                <TableStart class="[&>path]:fill-fungo-blue"/>
              </button>
            </template>
            <template #stats="{ item }">
              <button
                @click="$router.push({ name: 'training.statsMode', params: { 'idPractice': item.id, 'mode': 'WB' } })"
              >
                <TableStats />
              </button>
            </template>
            <template #delete="{ item }">
              <button @click="deletePractice(item.id)">
                <TableCancel />
              </button>
            </template>
          </Table>
        </tab-panel>
      </tab-panels>
    </tab-group>
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
