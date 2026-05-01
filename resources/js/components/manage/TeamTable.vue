<script setup>
import { ref, onMounted, defineEmits} from 'vue'
import { storeToRefs } from 'pinia'
import { TableCancel, TableEdit, TableHappyFace } from '@/components/icons'
import { useRouter } from 'vue-router'
import { Modal } from '@/components/shared'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { toast } from "@/utils/AlertPlugin"
import { useTeamStore } from '@/store/team.js'

const teamStore = useTeamStore()

const { teams, team } = storeToRefs(teamStore)

const { axiosGet, axiosDelete } = useAxiosAuth()

defineProps({
  tableData: {
    type: Object,
    required: true
  },
  palyers: {
    type: String,
    required: false
  },
  isLoading: {
    type: Boolean,
    required: true
  }
})
const emit = defineEmits(['updateTable'])

const tableHeadings = ref([
  "ID", "TEAM LOGO", "TEAM NAME", "COUNTRY", "STATE", "TEAM PLAYERS", "ZIP CODE", "TEAM INFO", "PLAYER TEAM"
])

const teamToDelte = ref('')

const temporalPlayers = ref([])
let playersOfTeam = ref([])

const isLoadPlayers = ref(false)

const deleteTeam = (id) => {
  teamToDelte.value = id
  isOpenDelteModal.value = true
}

const isOpenPlayerModal = ref(false)
const isOpenDelteModal = ref(false)

const confirmDelete = () => {
  let teamtoDelete = temporalPlayers.value.find((item)=> {
      return item.id_team == teamToDelte.value
  })

  if(team.value.id != teamtoDelete.id_team) {
    console.log('borrando team', teamtoDelete.id);
    teamStore.removeTeam(teamtoDelete)
    axiosDelete('coach/remove/team/', teamtoDelete.id).then((response)=>{
      toast.fire({
        icon: 'success',
        title: 'Removed',
        text: 'Team already delete',
      })
      emit('updateTable', true)
    })
    isOpenDelteModal.value = false
    emit('updateTable', false)
  }else {
    isOpenDelteModal.value = false
    toast.fire({
      icon: 'warning',
      title: 'Invalid Action',
      text: 'Cannot delete current team',
    })
    emit('updateTable', false)
  }

}

const router = useRouter()

const getTeamsWithPalyers = async() => {
  try {
    isLoadPlayers.value = true
    // const { data } = await axiosGet('coach/teams')
    temporalPlayers.value = await teamStore.getTeamsFromApi()
  } catch (error) {
    console.log(error);
  } finally {
    isLoadPlayers.value = false
  }
}

const showPlayersOfTeam = (teamId) => {
  isOpenPlayerModal.value = true
  temporalPlayers.value.forEach(element => {
    if (element.id_team == teamId) {
      playersOfTeam.value = element
    }
  });
}

onMounted(() => {
  getTeamsWithPalyers()
})
</script>

<template>
  <section class="px-[10%] md:px-[5%] mt-[350px] lg:mt-[200px] overflow-x-auto">
    <table class="w-full border-separate space-y-6 text-fungo-darkblue">

      <thead class="bg-fungo-lightblue">
        <tr class="divide-x divide-[#000]">
          <th
            v-for="(heading, index) in tableHeadings"
            :key="index"
            class="py-3 font-fungo-500"
          >
            {{ heading }}
          </th>
          <th
            class="py-3 font-fungo-500"
          >
            ACTION
          </th>
        </tr>
      </thead>

      <tbody>
        <tr v-if="isLoadPlayers" class="w-full">
          <td colspan="9" class="text-fungo-darkblue text-3xl text-center">Loading data...</td>
        </tr>
        <tr v-else-if="!tableData.length > 0">
          <td colspan="9" class="text-fungo-darkblue text-3xl text-center">No found data</td>
        </tr>
        <tr
          v-else
          v-for="(item, index) in tableData"
          :key="index"
          class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative"
        >

          <td class="w-[200px] max-w-[200px] font-fungo-700">
            <!-- {{ item.id }} -->
            {{ Number.parseInt(index, 10) + 1 }}
          </td>

          <td class="w-[200px] max-w-[200px]">
            <img :src="item.logo" alt="Team logo" class="h-full object-center object-cover mx-auto"/>
          </td>


          <td class="w-[400px] max-w-[400px] font-fungo-700">
            {{ item.name }}
          </td>

          <td class="w-[200px] max-w-[200px] font-fungo-700">
            {{ item.country ?? "USA" }}
          </td>

          <td class="w-[200px] max-w-[200px] font-fungo-700">
            {{ item.state }}
          </td>

          <td class="w-[200px] max-w-[200px] font-fungo-700">
            <template
              v-for="(player, indx) in temporalPlayers"
              :key="indx"
            >
              <span v-if="player.id_team == item.id">
                {{ player.num_players }}
              </span>
            </template>
          </td>

          <td class="w-[200px] max-w-[200px] font-fungo-700">
            {{ item.zip }}
          </td>

          <!-- <td class="w-[200px] max-w-[200px] font-fungo-700">
            {{ item.created ?? 0 }}
          </td> -->

          <td class="w-[80px] max-w[80px]">
            <button
              @click="router.push({name: 'manage.team.update', params: { id: item.id } })"
            >
              <TableEdit />
            </button>
          </td>

          <td class="w-[80px] max-w[80px]">
            <button
              @click="showPlayersOfTeam(item.id)"
            >
              <TableHappyFace />
            </button>
          </td>

          <td class="w-[80px] max-w[80px]">
            <button
              @click="deleteTeam(item.id)"
            >
              <TableCancel />
            </button>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- modal for show players -->
    <Modal
      modalTitle="players"
      :isOpen="isOpenPlayerModal"
    >
      <template #content>
        <section class="space-y-5 grid grid-cols-2 md:grid-cols-3">
          <div v-if="playersOfTeam.players.length <= 0">
            <p>This team has no players</p>
          </div>
          <ul
            v-else
            v-for="player in playersOfTeam.players"
            class="flex flex-row flex-nowrap items-center space-x-4">
            <div>
              <li>
                <img :src="player.avatar" :alt="`Photo of ${player.name.full}`" class="w-14 h-14 rounded-full">
              </li>
              <li>{{ player.name.full }}</li>
            </div>
          </ul>
        </section>
      </template>

      <template #actions>
        <button
          type="button"
          class="inline-flex justify-center rounded-md bg-fungo-lightblue px-4 py-1"
          @click="isOpenPlayerModal = false"
        >
          Close
        </button>
      </template>
    </Modal>

    <!-- modal for delete team -->
    <Modal
      modalTitle="delete team"
      :isOpen="isOpenDelteModal"
    >
      <template #content>
        <section>
          <p>Are you sure delete this team?</p>
        </section>
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
            @click=" isOpenDelteModal = false"
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
table{
  border-spacing: 0 10px;
}
table tbody tr td {
  @apply text-center py-4 px-1 2xl:px-5;
}

table tbody tr::after{
  content: '';
  position: absolute;
  left: -1px;
  top: 0;
  height: 100%;
  width: 3px;
  background-color: #ADE8F4;
}
table tbody tr:nth-child(even)::after{
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
