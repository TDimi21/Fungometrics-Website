<script setup>
import { ref, reactive } from 'vue'
import { SearchIcon, ArrowDownIcon } from '@/components/icons'
import {useUserStore} from "../../store/user";
import {toast} from "../../utils/AlertPlugin";
import useChart from '@/composables/useChart.js'
import useChartOptions from '@/composables/useChartOptions.js'
import Loader from "@/components/Loader.vue";

const { getFilteredDataChartPlayer, seriesDinamicChart, formModel } = useChart()
const { dinamicChartOptionsPlayers } = useChartOptions()
const {userData} = useUserStore();
const tableData = ref([])
const isLoading = reactive({status: true})
const showChart = ref(false)

const entriesModelCharts= ref('')
const entriesOptionCharts = ref([
  {label: 'Avg Exit Velocity for each Session', key: '1', title: 'AVG Exit Velocity'},
  {label: 'Max Exit Velocity for each session', key: '2', title: 'Max Exit Velocity'},
  {label: 'Max Cage Distance', key: '3', title: 'Max Cage Distance'},
  {label: 'Total strike percentage per session', key: '4', title: 'Total Strike'},
  {label: 'Max FB Velocity for each session', key: '5', title: 'Max FB Velocity'},
  {label: 'Average throw velocity for each weight', key: '6', title: 'AVG Throw Weighted Velocity'},

  {label: 'Max distance throw with 0 hops per session', key: '7', title: 'Max Distance Throw 0 Hops'},
  {label: 'Average Training Exit Velocity for each Session', key: '8', title: 'AVG Exit Velocity Training Mode'},
  // {label: 'Max Throw for each weight', key: '7', title: 'Max Throw Weighted Velocity'},
  // {label: 'Max distance throw with 0 hops per session', key: '8', title: 'Max Distance Throw 0 Hops'},
  // {label: 'Average Training Exit Velocity for each Session', key: '9', title: 'AVG Exit Velocity Training Mode'},
])

const entriesModelDates= ref('')
const entriesOptionDates = ref([
  {label: 'All Time', key: '0'},
  {label: '3 Month', key: '3'},
  {label: '6 Month', key: '6'},
])

const searchChart = async () => {
  isLoading.status =!isLoading.status;
  showChart.value = false
  if(entriesModelCharts.value == '' || entriesModelDates.value == ''){
    toast.fire({
      icon: 'warning',
      title: 'Validation !!!',
      text: 'You must complete all the fields',
    })
    isLoading.status =!isLoading.status;
  }else{
    // await getChartData(entriesModelCharts.value, entriesModelDates.value)
    formModel.value.type = parseInt(entriesModelCharts.value.key),
    formModel.value.range = parseInt(entriesModelDates.value),
    formModel.value.players = [userData.id]
    let getMessage = await getFilteredDataChartPlayer()
    if(getMessage == ''){
      showChart.value = true
      isLoading.status =!isLoading.status;
    }else{
      isLoading.status =!isLoading.status;
      await toast.fire({
        icon: 'error',
        title: 'Error',
        text: "Not Data Found"
      })
    }
  }
}

const onChange = (event) => {
  showChart.value = false
}
</script>

<template>
  <Loader v-show="!isLoading.status"/>
  <section class="mt-4 overflow-x-auto">
    <div class="w-full mt-2 px-1 sm:px-6 grid justify-items-center">
      <div class="flex flex-col md:flex-row justify-around gap-2 w-[85%]">
        <div class="flex justify-center items-center">
          <h1 class="text-fungo-red text-2xl md:text-[40px] text-center font-fungo-700">Filters</h1>
        </div>
        <div class="w-[100%] md:w-[75%] flex flex-col md:flex-row gap-2 sm:gap-4 mt-8 p-10 bg-fungo-gray4 rounded-xl shadow-xl">
          <div class="w-full col-span-2">
            <div>
              <label for="entries">Chart to show</label>
              <!-- <SelectField v-model="entriesModelYAxis" :options="entriesOptionYAxis" /> -->
              <div class="relative w-full">
                <select class="selectd-form" v-model="entriesModelCharts" style="z-index: 9" @change="onChange($event)">
                  <option class="text-fungo-darkblue" value="" disabled selected>Select your option</option>
                  <option class="text-fungo-darkblue" v-for="(item, index) in entriesOptionCharts" :value="item">{{ item.label }}</option>
                </select>
                <div class="arrow-position"> <ArrowDownIcon color="26364D"/> </div>
              </div>
            </div>
          </div>
          <div class="w-full col-span-2">
            <div>
              <label for="entries">Date</label>
              <div class="relative w-full">
                <select class="selectd-form" v-model="entriesModelDates" style="z-index: 9" @change="onChange($event)">
                  <option class="text-fungo-darkblue" value="" disabled selected>Select your option</option>
                  <option class="text-fungo-darkblue" v-for="(item, index) in entriesOptionDates" :value="item.key">{{ item.label }}</option>
                </select>
                <div class="arrow-position"> <ArrowDownIcon color="26364D"/> </div>
              </div>
            </div>
          </div>
          <div class="grid items-end justify-items-center w-full max-w-full md:max-w-[50px] h-[65px] md:h-full">
            <button @click="searchChart" class="bg-fungo-red rounded-lg w-[100%] h-[65%] flex items-center justify-center">
              <SearchIcon />
            </button>
          </div>
        </div>
      </div>
      <div class="w-full my-10" v-if="showChart || tableData.length > 0">
        <hr class="bg-fungo-gray8 h-1 mt-2 mb-5">
        <div>
          <apexchart width="100%" type="line" height="500px" :options="dinamicChartOptionsPlayers(entriesModelCharts.title, formModel.range)" :series="seriesDinamicChart"/>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
@keyframes bounce {
  0%, 100% {
    transform: translateX(-25%);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  50% {
    transform: none;
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
}

.animate-bounce-r {
  animation: bounce 1s infinite;
}

.btn-add{
  @apply grid place-items-center grid-flow-col flex-row w-[200px] lg:w-[250px] rounded-t-[30px] rounded-r-[10px] rounded-b-[10px] rounded-l-[30px]
    py-1 text-xl md:text-[16px] lg:text-[20px] bg-fungo-red text-white hover:bg-fungo-red-hover
}
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
  /* background-color: #ADE8F4; */
}

table tbody tr:nth-child(even)::after {
  background-color: #DADADA;
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
  @apply bg-fungo-red-hover rounded-md;
}

::-webkit-scrollbar-thumb:active {
  @apply bg-fungo-red;
}

::-webkit-scrollbar-track {
  border: 22px solid #918383;
  @apply bg-fungo-dark-gray rounded-md;
}

::-webkit-scrollbar-corner {
  background: transparent;
}

.arrow-position{
  z-index: 0;
  position: absolute;
  top: 0;
  right: 0;
}

.selectd-form{
  @apply bg-white h-10 appearance-none bg-none w-full border border-fungo-darkblue text-fungo-darkblue rounded-[5px]
}
</style>
