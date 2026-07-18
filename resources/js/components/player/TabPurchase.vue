<script setup>
import { ref } from 'vue'
import { YearTab, MonthTab } from '@/components/player/PurchaseComponents/index.js'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import { useAccessStore } from '@/store/access.js'
const tabHeading = ref(['Bill Yearly', 'Bill Monthly'])
const access = useAccessStore()

</script>
<template>
  <section class="mt-10">
    <div v-if="access.loaded" class="mx-auto mb-4 w-[75%] rounded-lg border border-fungo-gray2 bg-white p-4">
      <h3 class="font-fungo-700 text-fungo-darkblue">Current plan usage</h3>
      <p v-for="(label, key) in { players: 'Players', coaches: 'Coach seats', teams: 'Teams' }" :key="key" class="text-sm text-fungo-gray9">
        {{ label }}: {{ access.summary.usage?.[key] ?? '—' }} / {{ access.summary.limits?.[key] ?? 'Unlimited' }}
      </p>
    </div>
    <tab-group>
      <tab-list class="flex flex-col md:flex-row justify-center items-center py-4">
        <div class="border border-fungo-darkblue rounded-lg">
          <tab
            as="template"
            v-slot="{ selected }"
            v-for="head in tabHeading"
          >
            <button
              class="outline-none py-2 rounded-md px-5 md:px-16 !mx-0 text-fungo-darkblue"
              :class="{ 'bg-fungo-lightblue font-fungo-500': selected, '': !selected }"
            >
              {{ head }}
            </button>
          </tab>
        </div>
      </tab-list>
      <tab-panels>
        <tab-panel class="grid place-content-center justify-items-center content-center">
          <YearTab/>
        </tab-panel>
        <tab-panel class="grid place-content-center justify-items-center content-center">
          <MonthTab/>
        </tab-panel>
      </tab-panels>
    </tab-group>
  </section>
</template>
