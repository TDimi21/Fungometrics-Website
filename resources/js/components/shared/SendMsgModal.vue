<script setup>
import { ref } from 'vue'
import useSendMsg from '@/composables/useSendMsg.js'
import { Table } from '@/components/dashboard/Table/index.js'
import { BigButtonField, InputField } from '@/components/form'

const props = defineProps({
  players: [Array, Object]
})

const { playersToSend } = useSendMsg()
const playersChecked = ref([])

const tableHeaders = [
  {
    title: 'Select',
    value: 'id',
    slot: 'idPlayer'
  },
  {
    title: 'Player',
    value: 'avatar',
    slot: 'avatar'
  },
  {
    title: 'Player Name',
    value: 'name',
    slot: 'name',
  },
  {
    title: 'Jersey #',
    value: 'number_in_shirt',
    slot: 'shirt',
  },
  {
    title: 'Mobile number',
    value: 'phone',
    slot: 'phone'
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
          <template #idPlayer="{ item }">
            <input type="checkbox" v-model="item.isChecked" :value="!item.isChecked">
            <!-- <span>{{ item.id }}</span> -->
          </template>
          <template #avatar="{ item }">
            <img :src="item.avatar" :alt="item.name" class="w-14 h-14 rounded-full mx-auto">
          </template>
          <template #phone="{ item }">
            <InputField v-model="item.phone" class="w-[200px]"/>
          </template>
        </Table>
      </div>
      <div class="bg-white h-[100px] flex justify-center items-center rounded-b-xl">
        <form @submit.prevent="$emit('sendMessage')">
          <BigButtonField color="red" label="Send Stats" type="submit"/>
        </form>
      </div>
    </section>
  </div>
</template>