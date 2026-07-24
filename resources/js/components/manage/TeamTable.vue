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
  <section class="mt-6 overflow-x-auto">
    <table class="w-full border-separate space-y-6 text-white/90">

      <thead class="bg-[#0a1020]">
        <tr class="divide-x divide-white/20">
          <th
            v-for="(heading, index) in tableHeadings"
            :key="index"
            class="py-3 font-fungo-500 text-white"
          >
            {{ heading }}
          </th>
          <th
            class="py-3 font-fungo-500 text-white"
          >
            ACTION
          </th>
        </tr>
      </thead>

      <tbody>
        <tr v-if="isLoadPlayers" class="w-full">
          <td colspan="9" class="text-white/80 text-3xl text-center">Loading data...</td>
        </tr>
        <tr v-else-if="!tableData.length > 0">
          <td colspan="9" class="text-white/80 text-3xl text-center">No found data</td>
        </tr>
        <tr
          v-else
          v-for="(item, index) in tableData"
          :key="index"
          class="bg-[#101A34] even:bg-[#0f172a] border-l border-white/20 relative"
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
      modalTitle="Team Players"
      :isOpen="isOpenPlayerModal"
      variant="dark"
      @close="isOpenPlayerModal = false"
    >
      <template #content>
        <section>
          <div class="mb-5 flex items-center justify-between gap-4">
            <div>
              <p class="text-xs font-black uppercase tracking-[.22em] text-red-400">Roster</p>
              <p class="mt-1 text-sm text-white/55">
                {{ playersOfTeam.name || 'Selected team' }} · {{ playersOfTeam.players?.length || 0 }} players
              </p>
            </div>
          </div>
          <div v-if="!playersOfTeam.players?.length" class="rounded-2xl border border-dashed border-white/15 bg-white/[.03] px-6 py-12 text-center">
            <div class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-full bg-white/[.06] text-2xl">⚾</div>
            <p class="font-bold text-white">No players on this team yet</p>
            <p class="mt-1 text-sm text-white/50">Players added to this team will appear here.</p>
          </div>
          <ul v-else class="grid gap-3 sm:grid-cols-2">
            <li
              v-for="player in playersOfTeam.players"
              :key="player.id"
              class="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/[.045] p-4"
            >
              <img :src="player.avatar" :alt="`Photo of ${player.name.full}`" class="h-14 w-14 rounded-full border border-white/15 object-cover">
              <div class="min-w-0">
                <p class="truncate font-black text-white">{{ player.name.full }}</p>
                <p class="text-xs uppercase tracking-wider text-white/45">Player</p>
              </div>
            </li>
          </ul>
        </section>
      </template>

      <template #actions>
        <button
          type="button"
          class="inline-flex min-h-[44px] justify-center rounded-xl border border-white/15 bg-white/[.06] px-6 py-3 font-black text-white transition hover:bg-white/10"
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
      variant="dark"
      @close="isOpenDelteModal = false"
    >
      <template #content>
        <section>
          <p class="text-white/70">Are you sure you want to permanently delete this team?</p>
        </section>
      </template>

      <template #actions>
        <div class="flex justify-between items-center w-90% mx-auto">
          <button
            @click="confirmDelete"
            class="min-h-[44px] rounded-xl bg-red-600 px-5 py-3 font-black text-white hover:bg-red-500"
          >
            Yes, delete
          </button>

          <button
            @click=" isOpenDelteModal = false"
            class="min-h-[44px] rounded-xl border border-white/15 bg-white/[.06] px-5 py-3 font-black text-white hover:bg-white/10"
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
  background-color: #002060;
}
table tbody tr:nth-child(even)::after{
  background-color: #1d4ed8;
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
