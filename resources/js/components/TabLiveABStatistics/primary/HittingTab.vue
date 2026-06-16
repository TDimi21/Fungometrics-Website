<script setup>
import { ref } from 'vue'
import { Tendency, PitchBreackdown, Contact, Trajectory, Velocity } from '@/components/TabLiveABStatistics/secundary/TabsHittings/index.js'
import BasicStatsModern from '@/components/TabLiveABStatistics/secundary/TabsHittings/BasicStatsModern.vue'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
const primaryTabHeading = ref(['BASIC (NEW)', 'TENDENCY', 'PITCH BREAK DOWN', 'CONTACT', 'TRAJECTORY', 'VELOCITY'])

defineProps({
  statsData: {
    type: [Object, Array]
  }
})
</script>
<template>
  <section class="mt-10">
    <tab-group>
      <tab-list class="bg-app-surface flex justify-center items-center py-4 rounded-xl flex-wrap gap-y-2">
        <div class="border border-white/15 rounded-lg overflow-hidden flex flex-wrap">
          <tab
            as="template"
            v-slot="{ selected }"
            v-for="head in primaryTabHeading"
          >
            <button
              class="outline-none py-2 px-6 !mx-0 transition-colors"
              :class="{ 'bg-app-gold text-app-bg font-fungo-500': selected, 'text-app-muted hover:text-white': !selected }"
            >
              {{ head }}
            </button>
          </tab>
        </div>
      </tab-list>
      <tab-panels>
        <tab-panel>
          <basic-stats-modern :ball-by-ball="statsData.ball_x_ball" />
        </tab-panel>
        <tab-panel>
          <tendency :tableData="statsData.matrixBSPB" :teamData="statsData.matrixBS"/>
        </tab-panel>
        <tab-panel>
          <pitch-breackdown :tableData="statsData.calculates" :ballByBall="statsData.ball_x_ball"/>
        </tab-panel>
        <tab-panel>
          <contact :tableData="statsData.calculates"/>
        </tab-panel>
        <tab-panel>
          <trajectory :tableData="statsData.calculates"/>
        </tab-panel>
        <tab-panel>
          <Velocity :tableData="statsData.by_batters"/>
        </tab-panel>
      </tab-panels>
    </tab-group>
  </section>
</template>