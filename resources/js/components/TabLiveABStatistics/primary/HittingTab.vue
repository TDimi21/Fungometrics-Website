<script setup>
import { ref } from 'vue'
import LiveABAnalysis from '@/components/statistics/LiveABAnalysis.vue'
import { Tendency } from '@/components/TabLiveABStatistics/secundary/TabsHittings/index.js'
import BasicStatsModern from '@/components/TabLiveABStatistics/secundary/TabsHittings/BasicStatsModern.vue'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
const primaryTabHeading = ref(['BASIC', 'TENDENCY', 'PITCH BREAKDOWN', 'CONTACT', 'TRAJECTORY', 'VELOCITY'])

defineProps({
  statsData: {
    type: [Object, Array]
  }
})
</script>
<template>
  <section class="mt-4">
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
              :class="{ 'bg-app-red text-white font-fungo-500': selected, 'text-app-muted hover:text-white': !selected }"
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
          <LiveABAnalysis :balls="statsData.ball_x_ball || []" side="hitting" mode="pitch" />
        </tab-panel>
        <tab-panel>
          <LiveABAnalysis :balls="statsData.ball_x_ball || []" side="hitting" mode="contact" />
        </tab-panel>
        <tab-panel>
          <LiveABAnalysis :balls="statsData.ball_x_ball || []" side="hitting" mode="trajectory" />
        </tab-panel>
        <tab-panel>
          <LiveABAnalysis :balls="statsData.ball_x_ball || []" side="hitting" mode="velocity" />
        </tab-panel>
      </tab-panels>
    </tab-group>
  </section>
</template>