<script setup>
import { ref } from 'vue'
import { TableCancel, TableEdit } from '@/components/icons'
import { useRouter } from 'vue-router'
import { Modal } from '@/components/shared'
import {toast} from "../../utils/AlertPlugin";
import axios from "axios";
import { storeToRefs } from 'pinia'
import { usePlayerStore } from '@/store/players.js'

const props = defineProps({
  tableData: {
    type: Object,
    required: true
  },
  idTeam: {
    type: String,
    required: true
  },
  isLoading: {
    type: Boolean,
    required: true
  },
  playerLinks: {
    type: [Array, Object],
    required: false,
    default: []
  }
})

const tableHeadings = ref([
  "PLAYER", "FIRST/LAST NAME", "POSITIONS", "JERSEY #", "EMAIL/MOBILE", "HOP FT", "HOP INCH", "EDIT", "DELETE"
])
const isOpenDelteModal = ref(false)
const router = useRouter()
const token = JSON.parse(localStorage.getItem('auth')).token
const api_url = process.env.API_ENDPOINT;
const playerStore = usePlayerStore()
const { players, setPlayers } = storeToRefs(playerStore)
const playerToDelete = ref('')

const deleteTeam = (id) => {
  playerToDelete.value = id
  isOpenDelteModal.value = true
}

const submitDelete = async () => {
  let dataForm = new FormData();
  dataForm.append('player', playerToDelete.value)
  dataForm.append('team', props.idTeam)
  const config = {
    headers: { Authorization: `Bearer ${token}` }
  };
  await axios.post(api_url+'coach/remove/players', dataForm, config).then(async function (response) {
    console.log(response.data);
    toast.fire({
      icon: 'success',
      title: 'Player remove sucess',
      text: response.data.message,
    })
    const filteredPlayers = players.value.filter((item) => item.id !== playerToDelete.value)
    // await setPlayers(filteredPlayers);
    players.value = filteredPlayers;
    await router.replace("/roster")
    isOpenDelteModal.value = false
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
          title: 'Player Warning !!!',
          text: errorMessage,
        })
    } else {
      toast.fire({
        icon: 'error',
        title: 'Player Error !!!',
        text: "strike 3 is out, have a internal problem, " +error.response.data.message,
      })
    }
    isOpenDelteModal.value = false
  })
}
</script>

<template>
  <section class="px-[10%] md:px-[5%] mt-[550px] lg:mt-[250px] overflow-x-auto">
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
        </tr>
      </thead>

      <tbody>
        <tr v-if="isLoading" class="w-full">
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
          <td class="w-[140px] max-w-[140px]">
            <template v-if="item.avatar != null">
                <img :src="item.avatar" alt="" class="h-full object-center object-cover mx-auto rounded-full">
              </template>
              <img v-else src="../../assets/img/layout/logofungo-nav.png" alt="Avatar Player" class="h-full object-center object-cover mx-auto rounded-full">
          </td>


          <td class="w-[400px] max-w-[400px] font-fungo-700">
            {{ item.name.full ?? ""}}
          </td>

          <td class="w-[200px] max-w-[200px] font-fungo-700">
            <label v-for="(position, poistionIndex) in item.positions" :key="poistionIndex">
              {{
                item.positions.length === (poistionIndex + 1) ? position.position : position.position + ',‍‍‍‍‍ㅤ'
              }}
            </label>
            <!-- {{ item.positions[0].position }} -->
          </td>
          <td class="w-[200px] max-w-[200px] font-fungo-700">
            {{ item.shirt_number }}
          </td>
          <td class="w-[400px] max-w-[400px] font-fungo-700 text-sm">
            <span class="flex justify-center items-center">
              {{ item.email }} / {{ item.phone }}
            </span>
          </td>
          <td class="w-[200px] max-w-[200px] font-fungo-700">
            {{ item.body.ft ?? "0000" }}
          </td>
          <td class="w-[200px] max-w-[200px] font-fungo-700">
            {{ item.body.inch ?? "0000" }}
          </td>
          <td class="w-[80px] max-w[80px]">
            <router-link :to="{ path:`/roster/player/${item.id}`, params: {playerData: item}}">
              <TableEdit />
            </router-link>
          </td>
          <td class="w-[80px] max-w[80px]">
            <button @click="deleteTeam(item.id)">
              <TableCancel />
            </button>
          </td>
        </tr>
      </tbody>
    </table>
    <Modal modalTitle="delete player" :isOpen="isOpenDelteModal">
      <template #content>
        <section>
          <p>Are you sure delete this player?</p>
        </section>
      </template>

      <template #actions>
        <div class="flex justify-between items-center w-90% mx-auto">
          <button @click="async () => {
            await submitDelete()
            $emit('update-table', true)
          }" class="bg-red-500 text-white px-4 py-1 rounded-md">
            Yes, delete
          </button>
          <button @click=" isOpenDelteModal = false" class="bg-fungo-lightblue px-4 py-1 rounded-md">
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
.pagination button:first-child {
  border-radius: 10px 0 0 10px;
}
.pagination button:last-child {
  border-radius: 0 10px 10px 0;
}
</style>
