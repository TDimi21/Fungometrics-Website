<script setup>
import { computed } from 'vue'

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
  }
})

const normalizedPlayers = computed(() => {
  if (Array.isArray(props.data?.players)) return props.data.players
  if (props.data?.players && typeof props.data.players === 'object') {
    return Object.values(props.data.players)
  }
  return []
})
</script>
<template>
  <section class="px-[2%] md:px-[3%] mt-4 overflow-x-auto">
  <table class="w-full text-white text-sm border-separate border-spacing-0 rounded-xl overflow-hidden">
    <thead>
      <tr class="bg-[#a7deec] text-[#12233f] uppercase text-xs tracking-wider divide-x divide-[#31465f]">
        <th
          v-for="(heading, index) in props.headings"
          :key="index"
          class="py-3 px-3 font-bold text-center"
        >
          {{ heading }}
        </th>
      </tr>
    </thead>
    <tbody>
      <tr v-if="props.data.team != null" class="bg-[#16243a] border-b border-white/10">
        <td class="text-center py-2 px-3 font-semibold text-[#9fd7ff]">
          Total Players
          <!-- <img :src="item.player.avatar" alt="" class="w-16 h-full object-center object-cover mx-auto rounded-full"/> -->
        </td>
        <td v-for="key in props.keyTeam" class="text-center py-2 px-3 text-white">
          <span v-if="key !== '45 to <'">{{ props.data.team[key] ?? "?"}}</span>
          <span v-else>{{ Object.values(props.data.team).pop()}}</span>
        </td>
      </tr>
      <tr v-if="normalizedPlayers.length === 0">
        <td colspan="16" class="py-6 text-center text-2xl text-white/60 bg-white/5">No found data</td>
      </tr>
      <tr
        v-else
        v-for="(item, index) in normalizedPlayers"
        :key="index"
        class="border-b border-white/10"
        :class="index % 2 === 0 ? 'bg-white/5' : 'bg-white/10'"
      >
        <td v-for="key in props.keyPlayer" class="text-center py-2 px-3 text-white">
          <span v-if="key !== '45 to <'">{{ item[key] ?? "?"}}</span>
          <span v-else>{{ Object.values(item).pop()}}</span>
        </td>
      </tr>
    </tbody>
  </table>
  </section>
</template>
