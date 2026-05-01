<script setup>
import { ref } from 'vue'
import { ArrowRightIcon } from '@/components/icons'

const isOpenModal = ref(false)
const cardsPurchase = ref([
{
    name: 'Silver',
    cost: 79,
    icon: '../../../assets/img/icons/i-ball-purchase.svg',
    beneficts: [
      'Coach package',
      'One coach',
      'Full access to score, Bullpen, BP, Cage',
      'Only coach has access to sessions statistcs',
      'Team and player chart',
    ],
    isRed: false,
  },
  {
    name: 'Gold',
    cost: 149,
    icon: '../../../assets/img/icons/i-base-purchase.svg',
    beneficts: [
      'Coach package',
      'One coach',
      'Full access to score, Bullpen, BP, Cage',
      'Only coach has access to sessions statistcs',
      'Team and player chart',
    ],
    isRed: false,
  },
  {
    name: 'Diamond',
    cost: 339,
    icon: '../../../assets/img/icons/i-diamond-purchase.svg',
    beneficts: [
      'Coach package',
      'One coach',
      'Full access to score, Bullpen, BP, Cage',
      'Only coach has access to sessions statistcs',
      'Team and player chart',
    ],
    isRed: false,
  },
])


const changeOpen = () => {
  isOpenModal.value = !isOpenModal.value
}
</script>
<template>
  <section class="mt-10 grid grid-cols-1 md:grid-cols-3 gap-10 w-[75%]">
    <template v-for="item in cardsPurchase" class="">
      <div class="card">
        <div class="flex flex-row md w-[100%] mt-5 gap-1 px-5">
          <div class="rounded-full bg-fungo-lightblue-disabled p-3 flex justify-center">
            <div class="rounded-full bg-fungo-lightblue w-[35px] p-1 h-[35px] flex items-center justify-center">
              <img :src="item.icon ?? '../../../assets/img/icons/i-edit.svg'" alt="" class="w-[2em] lg:w-[2em]"/>
              <!-- <img src="../../../assets/img/icons/i-edit.svg" alt="" class="w-[2em] lg:w-[2em]"/> -->
              <!-- <img alt="Avatar Player" class="w-[75px] max-w-[75px]" src="../../../assets/img/login/assteslogin/ballbutton.svg"></img> -->
            </div>
          </div>
          <div class="flex flex-col pl-2">
            <h3 class="text-[24px] font-fungo-700 text-fungo-darkblue">{{ item.name ?? "" }}</h3>
            <h3 class="text-[16px] font-fungo-700 text-fungo-red">Fungo</h3>
          </div>
        </div>
        <hr class="my-2"/>
        <div class="w-full py-3">
          <template v-for="data in item.beneficts">
            <div class="flex flex-row my-2 gap-4 px-5">
              <img src="../../../assets/img/icons/i-edit.svg" alt="" class="w-[1em]"/>
              <h3 class="text-[14px] font-fungo-700 text-fungo-darkblue">{{ data ?? "" }}</h3>
            </div>
          </template>
        </div>
        <div class="w-full flex flex-row">
          <div class="bg-fungo-gray2 w-[50%] py-5 flex justify-center items-center">
            <h3 class="text-[24px] font-fungo-800 text-fungo-red">${{item.cost ?? 0.0}}</h3>
          </div>
          <div class="bg-fungo-gray9 w-[50%] py-5 px-2 flex justify-center items-center">
            <h3 class="text-[16px] font-fungo-500 text-fungo-darkblue">Month for Full access</h3>
          </div>
        </div>
        <div class="w-full h-[100px] flex justify-center items-center">
          <button class="btn rounded-button-right" type="submit" @click="changeOpen()">
            <img alt="ball" class="w-6 h-6 md:w-8 md:h-8 mx-2 md:mx-0" src="../../../assets/img/login/assteslogin/ballbutton.svg">
            <span class="mx-2">Purchase</span>
          </button>
        </div>
      </div>
    </template>
  </section>

  <div v-if="isOpenModal">
    <div class="fixed inset-0 z-50 flex justify-center items-center">
      <div class="modal">
        <h1 class="text-lg lg:text-2xl text-fungo-red font-fungo-700 my-5">This section will be enabled very soon</h1>
        <button class="btn rounded-button-right" type="submit" @click="changeOpen()">
            <img alt="ball" class="w-6 h-6 md:w-8 md:h-8 mx-2 md:mx-0" src="../../../assets/img/login/assteslogin/ballbutton.svg">
            <span class="mx-2">Okay</span>
            <div class="text-white mx-2 animate-bounce-r"><ArrowRightIcon color="ffffff" w="50" h="50"/></div>
        </button>
      </div>
    </div>
    <div class="opacity-70 fixed inset-0 z-40 bg-fungo-darkblue"></div>
  </div>
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

.btn{
  @apply grid place-items-center grid-flow-col flex-row w-[185px] lg:w-[185px] rounded-t-[30px] rounded-r-[10px] rounded-b-[10px] rounded-l-[30px]
    py-1 text-xl md:text-[16px] lg:text-[20px] bg-fungo-darkblue text-white hover:bg-fungo-red-hover
}

.card{
  @apply bg-white rounded-xl hover:shadow-2xl hover:shadow-fungo-lightblue-hover pt-3 border-fungo-gray2
}

.modal{
  @apply flex flex-col justify-center items-center rounded-lg shadow-xl overflow-y-auto bg-white border drop-shadow-xl min-h-[175px]
    max-h-[175px] lg:min-h-[175px] lg:max-h-[175px] px-5 py-2
}
</style>
