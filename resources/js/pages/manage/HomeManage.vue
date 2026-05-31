<script setup>
import { storeToRefs } from 'pinia'
import Layout from '@/layout/Layout.vue'
import { InputBase, BigButtonField } from '@/components/form'
import { SearchIcon } from '@/components/icons'
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
    <section class="practice-shell px-[5%] py-6">
      <h1 class="text-white text-2xl md:text-[40px] text-center mb-6 font-fungo-700">Manage Team</h1>

      <div class="practice-toolbar flex flex-col items-center lg:flex-row space-y-6 lg:space-y-0 lg:space-x-3">
        <div class="w-full lg:w-[35%] text-center lg:text-left">
          <h2 class="text-white text-lg md:text-[30px] font-fungo-700">Create Team</h2>
        </div>

        <div class="w-full lg:w-[30%]">
          <form @submit.prevent="searchTeamByName" class="flex flex-row items-center space-x-3">
            <label for="search" class="text-white/80">Search</label>
            <InputBase v-model="search" inputType="search" placeholder="Search by name" class="inline-flex w-[85%]" />
            <button type="submit" @click="searchTeamByName" class="bg-fungo-red inline-flex rounded-lg w-10 h-10 items-center justify-center">
              <SearchIcon />
            </button>
          </form>
        </div>

        <div class="w-full lg:w-[35%] flex justify-center lg:justify-end">
          <RouterLink :to="{name: 'manage.team'}" to="/manage/create">
            <BigButtonField color="dark" label="New"/>
          </RouterLink>
        </div>
      </div>

      <TeamTable @updateTable="reloadData($event)" :tableData="tableData" :isLoading="isLoading"/>
    </section>
  </Layout>
</template>

<style scoped>
.practice-shell {
  background: rgba(10, 16, 32, 0.58);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 20px;
  box-shadow: 0 18px 45px rgba(0, 0, 0, 0.28);
}

.practice-toolbar {
  border-bottom: 1px solid rgba(255, 255, 255, 0.14);
  padding-bottom: 1rem;
  margin-bottom: 1rem;
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
