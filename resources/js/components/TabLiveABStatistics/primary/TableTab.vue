<script setup>
import { ref } from 'vue'
import { BallByBall, Hitters, Pitchers } from '@/components/TabLiveABStatistics/secundary/TabsTables/index.js'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
const primaryTabHeading = ref(['BALL BY BALL', 'HITTERS', 'PITCHERS'])

defineProps({
  statsData: {
    type: [Object, Array]
  },
  isLoading: {
    type: Boolean,
    required: false
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
          <ball-by-ball :tableData="statsData.ball_x_ball" @sortData="$emit('sortData', $event)" :isLoading="isLoading"/>
        </tab-panel>
        <tab-panel>
          <hitters :tableData="statsData.calculates"/>
        </tab-panel>
        <tab-panel>
          <pitchers :tableData="statsData.calculates"/>
        </tab-panel>
      </tab-panels>
    </tab-group>
  </section>
</template>