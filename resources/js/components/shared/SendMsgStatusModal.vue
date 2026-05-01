<script setup>
import useSendMsg from '@/composables/useSendMsg.js'
import { Table } from '@/components/dashboard/Table/index.js'
import { BigButtonField } from '@/components/form'

const props = defineProps({
  players: [Array, Object]
})

const { closeMsgWindow, sendMsg } = useSendMsg()

const tableHeaders = [
  {
    title: 'N°.',
    value: 'num',
    slot: 'num'
  },
  {
    title: 'Player Name',
    value: 'name.full',
    slot: 'name',
  },
  {
    title: 'Jersey #',
    value: 'shirt',
    slot: 'shirt',
  },
  {
    title: 'Status',
    value: 'status',
    slot: 'status'
  }
]
</script>
<template>
  <div class="absolute left-0 top-0 h-screen w-full bg-fungo-darkblue-player/70 flex justify-center items-center">
    <section class="bg-white rounded-xl min-w-[calc(100vw-60%)] relative opacity-100">
      <div class="h-20 flex items-center px-4">
        <h2 class="text-fungo-red font-fungo-700 text-xl">Sending SMS verifying screen</h2>
        <span class="absolute top-5 right-5 cursor-pointer" role="button" @click="$emit('closeModal')">
          <img src="@/assets/img/register/cancel.svg" alt="close modal">
        </span>
      </div>
      <div class="bg-fungo-gray2 p-4 max-h-[55vh] overflow-y-auto">
        <Table :header="tableHeaders" :items="props.players">
          <template #num="{ index }">
            <span>{{ index+1 }}</span>
          </template>
          <template #status="{ item }">
            <span
              class="rounded-tr-3xl rounded-b-3xl py-1 px-3"
              :class="{ 'bg-[#98DF78]' : item.status, 'bg-[#F6837F]' : !item.status }"
            >
              {{ item.status ? 'Send SMS' : 'Not sent' }}
            </span>
          </template>
        </Table>
      </div>
      <div class="bg-white h-[100px] flex justify-center items-center rounded-b-xl">
      </div>
    </section>
  </div>
</template>