<script setup>
import { ref } from 'vue'
import { YearTab, MonthTab } from '@/components/player/PurchaseComponents/index.js'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
const tabHeading = ref(['Bill Yearly', 'Bill Monthly'])

</script>
<template>
  <section class="mt-10">
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
