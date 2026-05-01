<script setup>
import { ref } from 'vue'
import { TableStats, TableCancel, TableStart } from '@/components/icons'

const props = defineProps({
  tableData: {
    type: Object,
    required: true
  },
  isPrimary: {
    type: Boolean,
    required: false,
    default: false
  },
  primaryHeaders: {
    type: [Object, Array],
    required: false
  },
  headings: {
    type: Array,
    required: true,
    default: []
  }
})

</script>

<template>
  <section class="px-[10%] md:px-[5%] mt-4 overflow-x-auto">
    <table class="w-full border-separate space-y-6 text-fungo-darkblue">

      <thead class="bg-fungo-lightblue">
        <!-- <tr class="divide-x divide-[#000] capitalize" v-if="props.isPrimary">
          <th colspan="4">
            Location
          </th>
          <th colspan="4">
            quality of contact
          </th>
          <th colspan="4">
            trajectory
          </th>
          <th colspan="4">
            Direction
          </th>
        </tr> -->

        <template v-if="props.isPrimary">
          <tr class="divide-x divide-[#000] capitalize">
            <th
              v-for="head in primaryHeaders" :key="head.name"
              :colspan="head.colspan"
            >
              {{ head.name }}
            </th>
          </tr>
        </template>

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
        <tr v-if="!tableData.length > 0">
          <td colspan="16" class="text-fungo-darkblue text-3xl text-center">No found data</td>
        </tr>
        <tr
          v-else
          v-for="(item, index) in tableData"
          :key="index"
          class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative"
        >
          <td>{{ Number.parseInt(index, 10) + 1 }}</td>

          <td>
            <img :src="item.team.logo" alt="" class="w-16 h-full object-center object-cover mx-auto rounded-full">
          </td>

          <td class="font-fungo-700">
            {{ item.team.name }}
          </td>

          <td class="group relative">
            <p class="truncate">
              <span v-for="(player, playerIndex) in item.lineup" :key="playerIndex">
                {{
                  item.lineup.length === (playerIndex + 1) ? player.player.name.full : player.player.name.full + ', '
                }}
              </span>

              <!-- tooltip player -->
              <span class="tooltip">
                <label v-for="(player, playerIndex) in item.lineup" :key="playerIndex">
                  {{
                    item.lineup.length === (playerIndex + 1) ? player.player.name.full : player.player.name.full + ',‍‍‍‍‍ㅤ'
                  }}
                </label>
              </span>
              <!-- end tooltip player -->
            </p>
          </td>

          <td class="w-[270px] max-w-[270px] group relative">
            <p class="truncate">{{ item.note }}</p>
            <!-- tooltip note -->
            <span class="tooltip w-[300px] max-w-[300px]">
              {{ item.note }}
            </span>
            <!-- end tooltip note -->
          </td>

          <td class="w-[200px] max-w-[200px]">
            <progress
              max="100"
              :value="item.is_completed ? 100 : 50"
              class="rounded overflow-hidden h-[7px]"
              :class="{ 'in-proress' : !item.is_completed, 'completed' : item.is_completed }"
            >
            </progress>
          </td>

          <td class="w-[150px] max-w-[150px]">
            <button>
              <TableStart />
            </button>
          </td>

          <td class="w-[80px] max-w[80px]">
            <button>
              <TableStats />
            </button>
          </td>

          <td class="w-[80px] max-w[80px]">
            <button>
              <TableCancel />
            </button>
          </td>

        </tr>
      </tbody>
    </table>
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
/* progress bar */
progress.in-proress::-webkit-progress-value {
  background: #FFB457;
}
progress.completed::-webkit-progress-value {
  background: #35A800;
}
progress::-webkit-progress-bar {
  background: #DBDFF1;
}
/* end progress bar */

.tooltip {
  @apply absolute hidden group-hover:flex -left-5 -top-2 -translate-y-[60%] w-max px-2 py-1 bg-fungo-darkblue rounded-lg text-center text-white text-sm after:content-[''] after:absolute after:left-1/2 after:top-[100%] after:-translate-x-1/2 after:border-8 after:border-x-transparent after:border-b-transparent after:border-t-fungo-darkblue
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
