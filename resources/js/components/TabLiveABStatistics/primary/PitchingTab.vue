<script setup>
import { ref } from 'vue'
import { Tendency, PitchBreackdown, Contact, Velocity } from '@/components/TabLiveABStatistics/secundary/TabsPitching/index.js'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
const primaryTabHeading = ref(['TENDENCY', 'PITCH BREAK DOWN', 'CONTACT', 'VELOCITY'])

defineProps({
  statsData: {
    type: [Object, Array]
  }
})
</script>
<template>
  <section class="mt-10">
    <tab-group>
      <tab-list class="bg-fungo-gray3 flex justify-center items-center py-4">
        <div class="border border-fungo-darkblue rounded-lg">
          <tab
            as="template"
            v-slot="{ selected }"
            v-for="head in primaryTabHeading"
          >
            <button
              class="outline-none py-2 rounded-md px-6 !mx-0"
              :class="{ 'bg-fungo-darkblue text-white font-fungo-500': selected, 'text-fungo-darkblue': !selected }"
            >
              {{ head }}
            </button>
          </tab>
        </div>
      </tab-list>
      <tab-panels>
        <tab-panel>
          <tendency :tableData="statsData.matrixBSPH" :teamData="statsData.matrixBS"/>
        </tab-panel>
        <tab-panel>
          <pitch-breackdown :tableData="statsData.calculates" :ballData="statsData.ball_x_ball"/>
        </tab-panel>
        <tab-panel>
          <contact :tableData="statsData.calculates"/>
        </tab-panel>
        <tab-panel>
          <Velocity :tableData="statsData.by_pitchers"/>
        </tab-panel>
      </tab-panels>
    </tab-group>
  </section>
</template>