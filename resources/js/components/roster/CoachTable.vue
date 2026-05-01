<script setup>
import { ref, defineEmits } from 'vue'
import { TableCancel } from '@/components/icons'
import { Modal } from '@/components/shared'
import { useUserStore } from '@/store/user'
import {useAxiosAuth} from '@/composables/axios-auth.js'
import {toast} from "@/utils/AlertPlugin"

const {axiosGet} = useAxiosAuth()
const {userData} = useUserStore();

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
  }
})

const tableHeadings = ref([
  "COACH", "FIRST/LAST NAME", "STATE", "ZIP CODE", "EMAIL/MOBILE", "DELETE"
])
const isOpenDelteModal = ref(false)

defineEmits(['removeItem'])

const deleteItem = ref({})

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
        </tr>
      </thead>

      <tbody>
        <tr v-if="isLoading" class="w-full">
          <td colspan="9" class="text-fungo-darkblue text-3xl text-center">Loading data...</td>
        </tr>
        <tr v-else-if="!tableData.length > 0">
          <td colspan="9" class="text-fungo-darkblue text-3xl text-center">No found data</td>
        </tr>
        <template v-else
          v-for="(item, keyCoach) in tableData"
          :key="keyCoach">
          <tr
          v-if="item.team_associate == idTeam || userData.id == item.id"
          class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative"
        >
          <td class="w-[200px] max-w-[200px]">
            <img alt="Avatar Coach" class="w-16 h-full object-center object-cover mx-auto rounded-full" src="../../assets/img/login/assteslogin/ballbutton.svg">
          </td>


          <td class="w-[400px] max-w-[400px] font-fungo-700">
            {{ item.name.full }}
          </td>

          <td class="w-[200px] max-w-[200px] font-fungo-700">
            {{ item.state }}
          </td>

          <td class="w-[200px] max-w-[200px] font-fungo-700">
            {{ item.zip }}
          </td>

          <td class="w-[400px] max-w-[400px] font-fungo-700">
            {{ item.email }}
          </td>

          <td class="w-[80px] max-w[80px]">
            <div v-if="userData.id != item.id">
              <button @click="() => {
                isOpenDelteModal = true
                deleteItem = item
              }">
              <TableCancel />
            </button>
            </div>
          </td>
        </tr>
        </template>
      </tbody>
    </table>
    <Modal modalTitle="delete coach" :isOpen="isOpenDelteModal">
      <template #content>
        <section>
          <p>Are you sure delete this coach?</p>
        </section>
      </template>

      <template #actions>
        <div class="flex justify-between items-center w-90% mx-auto">
          <button @click="()=>{
            $emit('removeItem', deleteItem)
            isOpenDelteModal = false
          }" class="bg-red-500 text-white px-4 py-1 rounded-md">
            Yes, delete
          </button>
          <button @click="isOpenDelteModal = !isOpenDelteModal" class="bg-fungo-lightblue px-4 py-1 rounded-md">
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
