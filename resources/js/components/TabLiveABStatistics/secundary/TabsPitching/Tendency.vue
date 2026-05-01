<script setup>
import { ref, onBeforeMount } from 'vue';
import { BigButtonField } from '@/components/form'
import { usePlayerStore } from '@/store/players.js'

const props = defineProps({
  tableData: {
    type: [Array, Object],
    required: true,
    default: []
  },
  teamData: {
    type: [Array, Object],
    required: true,
    default: []
  }
})
const { players } = usePlayerStore()

const tableHeadings = ['count', 'fb', 'ch', 'cv', 'sl', 'other', 'total']
const counterHeaderTable = ['', '0-0', '0-1', '0-2', '1-0', '1-1', '1-2', '2-0', '2-1', '2-2', '3-0', '3-1', '3-2', 'Total']

const playersOptions = players.filter(playr => {
  let toSet = Object.keys(props.tableData.pitch['b-s'])
  if (toSet.includes(playr.id)) {
    return playr
  }
})

const currentPlayer = ref(playersOptions)


const onChangeOption = ($event) => {
  if ($event.target.value !== 'team') return currentPlayer.value = players.find(item => item.id === $event.target.value)

  currentPlayer.value = $event.target.value
}

onBeforeMount(() => {
  currentPlayer.value = 'team'
})


</script>
<template>
  <section class="mt-4 overflow-x-auto">
    <div class="flex flex-row flex-wrap justify-center items-center space-x-3">
      <label for="select-batter">Select hitter</label>
      <select @change="onChangeOption($event)">
        <option selected disabled hidden>Select Hitter</option>
        <option v-for="hit in playersOptions" :value="hit.id">{{ hit.name.first }}</option>
        <option value="team">Team</option>
      </select>
    </div>
    <table class="w-full border-separate space-y-6 text-fungo-darkblue">

      <thead class="bg-fungo-lightblue">
        <tr class="divide-x divide-[#000]">
          <th v-for="(heading, index) in tableHeadings" :key="index" class="py-3 px-2 md:px-0 font-fungo-500 uppercase w-min">
            {{ heading }}
          </th>
        </tr>
      </thead>

      <tbody>
        <tr
          v-for="(item, index) in (currentPlayer == 'team' ? props.teamData.pitch['b-s'] : props.tableData.pitch['b-s'][currentPlayer.id])"
          :key="index"
          class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative"
        >
          <td>{{ index }}</td>
          <td>{{ item.FB }}</td>
          <td>{{ item.CH }}</td>
          <td>{{ item.CB }}</td>
          <td>{{ item.SL }}</td>
          <td>{{ item.OTHER }}</td>
          <td>{{ item.total }}</td>
        </tr>
      </tbody>
    </table>
  </section>
  <section class="my-5 py-4">
    <div class="mx-auto w-[20%] mb-5">
      <BigButtonField label="Total Pitch per AB" color="red" />
    </div>
    <table class="w-full border-separate space-y-6">
      <thead class="bg-fungo-lightblue">
        <tr>
          <th colspan="7" class="py-3">TOTAL PITCH PER BAT</th>
        </tr>
      </thead>

      <tbody>
        <tr class="bg-white">
          <td class="bg-fungo-lightblue">PITCHES</td>
          <td>1</td>
          <td>2</td>
          <td>3</td>
          <td>4</td>
          <td>5</td>
          <td>6+</td>
        </tr>
        <tr class="bg-fungo-gray4">
          <td class="bg-fungo-lightblue">TOTAL AB</td>
          <template v-for="(item, index) in (currentPlayer == 'team' ? props.teamData.ab : props.tableData.ab[currentPlayer.id])" :key="index">
            <td v-if="index !== 'count' && index !== 'sumpitches'">{{ item.count }}</td>
          </template>
        </tr>
      </tbody>

      <tfoot class="bg-fungo-lightblue text-center">
        <tr class="divide-x divide-[#000]">
          <td class="py-3 px-2 md:px-0 font-fungo-500 uppercase w-min">
            %
          </td>
          <template
            v-for="(item, index) in (currentPlayer == 'team' ? props.teamData.ab : props.tableData.ab[currentPlayer.id])"
            :key="index"
          >
            <td v-if="index !== 'count' && index !== 'sumpitches'">{{ item.average }}%</td>
          </template>
        </tr>
      </tfoot>

    </table>

    <table class="mt-7 w-full border-separate space-y-6 counters">
      <thead>
        <tr class="bg-fungo-lightblue">
          <th
            v-for="(heading, index) in counterHeaderTable"
            :key="index"
            class="first:bg-fungo-gray2 py-2"
          >
            {{ heading }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="bg-fungo-lightblue">#Of Weak</td>
          <template v-for="(contact, index) in (currentPlayer == 'team' ? props.teamData.contact['b-s'] : props.tableData.contact['b-s'][currentPlayer.id])">
            <td>{{ contact.Weak }}</td>
          </template>
        </tr>
        <tr>
          <td class="bg-fungo-lightblue">#of average</td>
          <template v-for="(contact, index) in (currentPlayer == 'team' ? props.teamData.contact['b-s'] : props.tableData.contact['b-s'][currentPlayer.id])">
            <td>{{ contact.Average }}</td>
          </template>
        </tr>

        <tr>
          <td class="bg-fungo-lightblue">#of hard</td>
          <template v-for="(contact, index) in (currentPlayer == 'team' ? props.teamData.contact['b-s'] : props.tableData.contact['b-s'][currentPlayer.id])">
            <td>{{ contact.Hard }}</td>
          </template>
        </tr>
        <tr>
          <td class="bg-fungo-lightblue">#of gb</td>
          <template v-for="(contact, index) in (currentPlayer == 'team' ? props.teamData.trajectory['b-s'] : props.tableData.trajectory['b-s'][currentPlayer.id])">
            <td>{{ contact.GB }}</td>
          </template>
        </tr>

        <tr>
          <td class="bg-fungo-lightblue">#of ld</td>
          <template v-for="(contact, index) in (currentPlayer == 'team' ? props.teamData.trajectory['b-s'] : props.tableData.trajectory['b-s'][currentPlayer.id])">
            <td>{{ contact.LD }}</td>
          </template>
        </tr>

        <tr>
          <td class="bg-fungo-lightblue">#of fly</td>
          <template v-for="(contact, index) in (currentPlayer == 'team' ? props.teamData.trajectory['b-s'] : props.tableData.trajectory['b-s'][currentPlayer.id])">
            <td>{{ contact.Fly }}</td>
          </template>
        </tr>

        <tr>
          <td class="bg-fungo-lightblue">1b</td>
          <template v-for="(contact, index) in (currentPlayer == 'team' ? props.teamData['1b']['b-s'] : props.tableData['1b'][currentPlayer.id])">
            <td>{{ contact }}</td>
          </template>
        </tr>

        <tr>
          <td class="bg-fungo-lightblue">#sw / miss</td>
          <template v-for="(contact, index) in (currentPlayer == 'team' ? props.teamData.trajectory['b-s'] : props.tableData.trajectory['b-s'][currentPlayer.id])">
            <td>{{ contact.SW }}</td>
          </template>
        </tr>

        <tr>
          <td class="bg-fungo-lightblue">xbh</td>
          <template v-for="(contact, index) in (currentPlayer == 'team' ? props.teamData.XBH['b-s'] : props.tableData.XHB[currentPlayer.id])">
            <td>{{ contact }}</td>
          </template>
        </tr>

        <tr>
          <td class="bg-fungo-lightblue">ba. avg.</td>
          <template v-for="(contact, index) in (currentPlayer == 'team' ? props.teamData.bat_avg['b-s'] : props.tableData.bat_avg[currentPlayer.id])">
            <td>{{ contact }}</td>
          </template>
        </tr>

        <tr>
          <td class="bg-fungo-lightblue">slg%</td>
          <template v-for="(contact, index) in (currentPlayer == 'team' ? props.teamData['SLG%']['b-s'] : props.tableData.SLG[currentPlayer.id])">
            <td>{{ contact }}</td>
          </template>
        </tr>
      </tbody>
    </table>
  </section>
</template>

<style scoped>
table {
  border-spacing: 0 10px;
}

table tbody tr td {
  @apply text-center py-4 px-1 2xl:px-5;
}

table tbody tr::after {
  content: '';
  position: absolute;
  left: -1px;
  top: 0;
  height: 100%;
  width: 3px;
  background-color: #ADE8F4;
}

table tbody tr:nth-child(even)::after {
  background-color: #DADADA;
}

table.counters tbody tr td:not(:first-child) {
  @apply bg-fungo-gray4;
}
</style>
