<script setup>
import { storeToRefs } from 'pinia'
import Layout from '@/layout/Layout.vue'
import { SelectField, InputBase, BigButtonField, InutTel } from '@/components/form'
import { SearchIcon, ArrowHeadRightIcon } from '@/components/icons'
import {ref, onMounted} from 'vue'
import { TeamTable } from '@/components/manage'
import { toast } from "@/utils/AlertPlugin"
import { useTeamStore } from '@/store/team.js'

const teamStore = useTeamStore()

const { teams } = storeToRefs(teamStore)

const tableDataDefault = ref([])
const tableData = ref([])

const search = ref('')
const isLoading = ref(false) //Constante tablas

const searchTeamByName = async () =>{
  if(search.value.length == 0){
    tableData.value = tableDataDefault.value;
  }else if(search.value.length >= 1 && search.value.length <= 2){
    await toast.fire({
      icon: 'error',
      title: 'Error get data',
      text: 'Please enter at least three letters to perform the search',
    })
  }else{
    const newArray = ref([])
    tableDataDefault.value.forEach(element => {
      if(element.name.toLowerCase().includes(search.value.toLowerCase())){
        newArray.value.push(element)
      }
    });

    if(newArray.value.length > 0){
      tableData.value = newArray.value
    }else{
      search.value = ""
      await toast.fire({
        icon: 'error',
        title: 'Error get data',
        text: 'Not  Coach Found',
      })
    }
  }
}

onMounted(() => {
  tableDataDefault.value = teams.value
  searchTeamByName();
})

const reloadData = (status) => {
  if(status){
    tableDataDefault.value = teams.value
    searchTeamByName();
  }
}
</script>

<template>
    <Layout>
        <h1 class="text-fungo-red text-2xl md:text-[40px] text-center mt-9 mb-6 font-fungo-700">Manage Team</h1>
        <section class="bg-fungo-gray4 w-full h-auto lg:h-[80px] absolute left-0 px-[10%] md:px-[3%]">
            <div class="flex flex-col items-center lg:flex-row space-y-6 lg:space-y-0 lg:space-x-3">
                <div class="w-full lg:w-[30%] mt-6 mb-6 text-center flex justify-center lg:justify-start">
                    <h1 class="text-fungo-darkblue text-lg md:text-[30px] font-fungo-700">Create team</h1>
                </div>
                <div class="w-[100%] lg:w-[75%] justify-center flex lg:justify-end pb-5 lg:pb-0">
                    <RouterLink
                      :to="{name: 'manage.team'}"
                      to="/manage/create">
                      <BigButtonField color="dark" label="New"/>
                    </RouterLink>
                </div>
            </div>
        </section>
        <section class="bg-fungo-gray3 w-full h-auto lg:h-[80px] absolute left-0 px-[10%] md:px-[3%] mt-[150px] lg:mt-[80px]">
          <div class="flex flex-wrap  flex-col lg:flex-row items-center space-y-6 lg:space-y-0 lg:space-x-3 justify-end">
            <div class="w-[100%] lg:w-[40%] flex flex-col lg:flex-row justify-end mt-6 mb-6">
              <div class="w-full lg:w-[60%] ml-0 lg:ml-2 flex flex-col lg:flex-row mt-6 lg:mt-0">
                <div class="flex flex-row flex-nowrap justify-start lg:justify-end items-center space-x-3">
                  <label for="search" class="block">Search</label>
                  <InputBase v-model="search" inputType="search" placeholder="Search by name" class="inline-flex w-[85%]" />
                  <button @click="searchTeamByName" class="bg-fungo-red inline-flex rounded-lg w-10 h-10 items-center justify-center">
                    <SearchIcon />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </section>
        <TeamTable @updateTable="reloadData($event)" :tableData="tableData" :isLoading="isLoading"/>
    </Layout>
</template>

<style scoped>
::-webkit-scrollbar {
  width: 4px;
  height: 4px;
}
::-webkit-scrollbar-button {
  width: 0px;
  height: 0px;
}
::-webkit-scrollbar-thumb {
  background: #e41111;
  border: 0px none #ffffff;
  border-radius: 5px;
}
::-webkit-scrollbar-thumb:hover {
  background: #ffffff;
}
::-webkit-scrollbar-thumb:active {
  background: #000000;
}
::-webkit-scrollbar-track {
  background: #666666;
  border: 22px solid #918383;
  border-radius: 4px;
}
::-webkit-scrollbar-track:hover {
  background: #e41111;
}
::-webkit-scrollbar-track:active {
  background: #333333;
}
::-webkit-scrollbar-corner {
  background: transparent;
}
</style>
