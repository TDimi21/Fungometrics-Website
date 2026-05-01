<script setup>
const props = defineProps(['firstTitle', 'secondTitle', 'thirdTitle'])
</script>

<template>
  <div class="bg-white rounded-2xl h-max w-full p-8 drop-shadow-2xl lg:max-w-[450px]">
    <p class="text-fungo-darkblue text-base">{{ props.firstTitle }}</p>
    <p class="text-fungo-darkblue text-lg font-fungo-700 my-3">{{ props.secondTitle }}</p>
    <p class="text-[#8888AC] text-sm">{{ props.thirdTitle }}</p>
    <slot name="chart-body">

    </slot>
    <!-- <apexchart width="500" type="donut" :options="chartOptions" :series="series"></apexchart> -->
  </div>
</template>