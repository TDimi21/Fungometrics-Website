<script setup>
const props = defineProps({
  data: {
    type: Object,
    default: () => {}
  },
  headings: {
    type: Array,
    default: () => []
  },
  keyTeam: {
    type: Array,
    default: () => []
  },
  keyPlayer: {
    type: Array,
    default: () => []
  },
  isSelected: {
    type: Boolean,
    default: () => false
  },
  selectedRow: {
    type: Object,
    default: () => {}
  }
})
</script>
<template>
  <section class="px-[10%] md:px-[5%] mt-4 overflow-x-auto">
  <table class="w-full border-collapse space-y-6 text-fungo-darkblue">
    <thead class="bg-fungo-lightblue">
      <tr class="divide-x divide-[#000]">
        <th
          v-for="(heading, index) in props.headings"
          :key="index"
          class="py-3 font-fungo-500 uppercase"
        >
          {{ heading }}
        </th>
      </tr>
    </thead>
    <tbody>
      <tr v-if="props.data.team != null" @click="$emit('selectedPlayer', props.data.team)" :class="isSelected ? 'cursor-pointer':''">
        <td class="text-center">
          Total Players
          <!-- <img :src="item.player.avatar" alt="" class="w-16 h-full object-center object-cover mx-auto rounded-full"/> -->
        </td>
        <td v-for="key in props.keyTeam" class="text-center">{{ props.data.team[key] ?? '?'}}</td>
      </tr>
      <tr v-if="props.data.players == null">
        <td colspan="16" class="text-fungo-darkblue text-3xl text-center">No found data</td>
      </tr>
      <tr v-else v-for="(item, index) in props.data.players" @click="$emit('selectedPlayer', item)">
        <td v-if="props.isSelected == false" v-for="key in props.keyPlayer"  class="text-center">{{ item[key] ?? '?'}}</td>
        <td v-else v-for="key in props.keyPlayer" :class="{
          'cursor-pointer text-center': props.selectedRow.player != item['player'],
          'cursor-pointer text-center bg-fungo-blue2 text-white': props.selectedRow.player == item['player'],
        }">
          {{ item[key] ?? '?'}}
        </td>
      </tr>
    </tbody>
  </table>
  </section>
</template>
