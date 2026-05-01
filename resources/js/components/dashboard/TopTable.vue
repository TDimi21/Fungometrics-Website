<script setup>
import { ref, onMounted } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { Table } from './Table/index'
import { useTeamStore } from '@/store/team.js'
import { SearchIcon, FilterIcon } from '@/components/icons'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import {
  optNameVeloDate, optNameDistanceDate, optNameAvg, optNameCount,
  optNamePowerDate, selectOption, timerOption, optNamePowerBodyDate,
  firtstTabHeading, secondTabHeading, thirdTabHeading, fourthTabHeading,
  fifthTabHeading, sixthTabHeading, seventhTabHeading
} from '@/utils/TopTenTableConfig'

const { axiosPost } = useAxiosAuth()
const { team } = useTeamStore()

const showSet = ref(1)

const formModels = ref({
  range: 0,
  option: 1
})

const isLoading = ref(false)

const allData = ref([])
const currentOption = ref(optNameVeloDate)

const navTabHeadings = ref(firtstTabHeading)

const getTopTable = async() => {
  
  try {
    isLoading.value = !isLoading.value
    const { data } = await axiosPost('table/'+team.id,{
      option: formModels.value.option,
      range: formModels.value.range
    })
    allData.value = await data.data
    updateTabHeadings()
    updateCurrentOption()
    filterByValueGreaterThanO(formModels.value.option)
  } catch (error) {
    console.log(error);
    console.log('error get top 10');
  } finally {
    isLoading.value = !isLoading.value
  }
}

const updateCurrentOption = () => {

  switch (true) {
    case formModels.value.option == 1 || formModels.value.option == 4 || formModels.value.option == 7:
      currentOption.value = optNameVeloDate
      break;
    case formModels.value.option == 2 || formModels.value.option == 5:
      currentOption.value = optNameAvg
      break;
    case formModels.value.option == 3 || formModels.value.option == 6 || formModels.value.option == 9:
      currentOption.value = optNameCount
      break;
    case formModels.value.option == 8:
      currentOption.value = optNameDistanceDate
      break;
    case formModels.value.option == 10:
      currentOption.value = optNamePowerDate
      break;
    case formModels.value.option == 11:
      currentOption.value = optNamePowerBodyDate
      break
  }
}

const updateTabHeadings = () => {
  switch (true) {
    case formModels.value.option == 1 || formModels.value.option == 2 || formModels.value.option == 3:
      navTabHeadings.value = firtstTabHeading
      showSet.value = 1
      break;
    case formModels.value.option == 4 || formModels.value.option == 5 || formModels.value.option == 6:
      navTabHeadings.value = secondTabHeading
      showSet.value = 2
      break;
    case formModels.value.option == 7:
      navTabHeadings.value = thirdTabHeading
      showSet.value = 3
      break;
    case formModels.value.option == 8:
      navTabHeadings.value = fourthTabHeading
      showSet.value = 4
      break;
    case formModels.value.option == 9:
      navTabHeadings.value = fifthTabHeading
      showSet.value = 5
      break;
    case formModels.value.option == 10:
      navTabHeadings.value = sixthTabHeading
      showSet.value = 6
      break
    case formModels.value.option == 11:
      navTabHeadings.value = seventhTabHeading
      showSet.value = 6
      break
  }
}

const filterByValueGreaterThanO = (opt) => {
  switch (true) {
    case opt == 1 || opt == 4 || opt == 7:
      allData.value.all = allData.value.all?.filter(item => item.velocity != 0)
      allData.value.batting = allData.value.batting?.filter(item => item.velocity != 0)
      allData.value.cage = allData.value.cage?.filter(item => item.velocity != 0)
      allData.value.exit = allData.value.exit?.filter(item => item.velocity != 0)
      allData.value.live = allData.value.live?.filter(item => item.velocity != 0)

    case opt == 2 || opt == 5:
      allData.value.all = allData.value.all?.filter(item => item.avg != 0)
      allData.value.batting = allData.value.batting?.filter(item => item.avg != 0)
      allData.value.cage = allData.value.cage?.filter(item => item.avg != 0)
      allData.value.exit = allData.value.exit?.filter(item => item.avg != 0)
      allData.value.live = allData.value.live?.filter(item => item.avg != 0)

    case opt == 3 || opt == 6 || opt == 9:
      allData.value.all = allData.value.all?.filter(item => item.count != 0)
      allData.value.batting = allData.value.batting?.filter(item => item.count != 0)
      allData.value.cage = allData.value.cage?.filter(item => item.count != 0)
      allData.value.exit = allData.value.exit?.filter(item => item.count != 0)
      allData.value.live = allData.value.live?.filter(item => item.count != 0)
    case opt == 8:
      if (!allData.value.result) return
      allData.value.result[1] = allData.value.result[1]?.filter(item => item.distance != 0)
      allData.value.result[2] = allData.value.result[2]?.filter(item => item.distance != 0)
      allData.value.result[3] = allData.value.result[3]?.filter(item => item.distance != 0)

    case opt == 10:
      allData.value.power_clean = allData.value.power_clean?.filter(item => item.value != 0)
      allData.value.bench_press = allData.value.bench_press?.filter(item => item.value != 0)
      allData.value.front_squat = allData.value.front_squat?.filter(item => item.value != 0)
      allData.value.back_squat = allData.value.back_squat?.filter(item => item.value != 0)
      allData.value.dead_lift = allData.value.dead_lift?.filter(item => item.value != 0)
    default :
      allData.value = allData.value
  }
}

onMounted(() => {
  getTopTable()
})
</script>
<template>
  <section class="bg-white rounded-3xl py-9 px-3 w-full">
    <h2 class="text-fungo-red font-fungo-700 text-3xl">Top 10</h2>
    <div class="mx-auto">
      <div class="my-4 inline-flex items-center space-x-4">
        <filter-icon />
        <p class="text-fungo-darkblue text-lg">Filter</p>
      </div>
      <form @submit.prevent="getTopTable" class="flex flex-row justify-between space-x-5">
        <select v-model="formModels.range" class="w-[45%]">
          <option v-for="opt in timerOption" :key="opt.value" :value="opt.value">{{ opt.text }}</option>
        </select>
        <select v-model="formModels.option" class="w-[45%]">
          <option v-for="opt in selectOption" :key="opt.value" :value="opt.value">{{ opt.text }}</option>
        </select>
        <button type="submit" role="submit" class="bg-fungo-red inline-flex rounded-lg w-10 h-10 items-center justify-center">
          <SearchIcon />
        </button>
      </form>
    </div>
    <tab-group>
      <div class="overflow-x-auto w-auto">
        <tab-list class="flex justify-center items-center py-4 w-max">
          <tab
            as="template"
            v-slot="{ selected }"
            v-for="head in navTabHeadings"
          >
            <button
              class="outline-none py-2 px-6 !mx-0"
              :class="{ ' text-fungo-red font-fungo-500 border-b-[3px] border-fungo-red': selected, 'text-fungo-darkblue': !selected }"
            >
              {{ head }}
            </button>
          </tab>
        </tab-list>
      </div>
      <tab-panels v-if="showSet == 1">
        <!-- all -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.all" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>

        <!-- batting -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.batting" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>

        <!-- training ev -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.live" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>

        <!-- cage -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.cage" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>

        <!-- live -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.live" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>
        

      </tab-panels>

      <tab-panels v-else-if="showSet == 2">
        <!-- all -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.all" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>

        <!-- batting -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.bullpen" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>

        <!-- live -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.live" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>
        

      </tab-panels>

      <tab-panels v-else-if="showSet == 3">
        <!-- all -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.result[3] ? allData.result[3] : []" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>

        <!-- batting -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.result[4] ? allData.result[4] : []" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>

        <!-- live -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.result[5] ? allData.result[5] : []" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>

        <tab-panel>
          <Table :header="currentOption" :items="allData.result[6] ? allData.result[6] : []" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>
        
        <tab-panel>
          <Table :header="currentOption" :items="allData.result[7] ? allData.result[7] : []" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>

      </tab-panels>

      <tab-panels v-else-if="showSet == 4">
        <!-- all -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.result[1] ? allData.result[1] : []" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>

        <!-- batting -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.result[2] ? allData.result[2] : []" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>

        <!-- live -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.result[3] ? allData.result[3] : []" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>

      </tab-panels>

      <tab-panels v-else-if="showSet == 5">
        <!-- all -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.all" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>
        <!-- live -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.long_toss" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>
        <!-- batting -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.weight_ball" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>
        
      </tab-panels>

      <tab-panels v-else-if="showSet == 6">
        <!-- all -->
        <!-- <p>here</p>
        <p>{{ allData.power_clean }}</p> -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.power_clean.filter(item => item.value !== 0)" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>
        <!-- live -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.bench_press.filter(item => item.value !== 0)" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>
        <!-- batting -->
        <tab-panel>
          <Table :header="currentOption" :items="allData.front_squat.filter(item => item.value !== 0)" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>

        <tab-panel>
          <Table :header="currentOption" :items="allData.back_squat.filter(item => item.value !== 0)" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>

        <tab-panel>
          <Table :header="currentOption" :items="allData.dead_lift.filter(item => item.value !== 0)" :is-loading="isLoading">
            <template #num="{ index }">
              <span>{{ index+1 }}</span>
            </template>
          </Table>
        </tab-panel>
        
      </tab-panels>
    </tab-group>
  </section>
</template>

<style scoped>
form select {
  border-radius: 5px;
}
</style>