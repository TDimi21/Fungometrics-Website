<script setup>
import { Dialog, DialogPanel, TransitionRoot, TransitionChild } from '@headlessui/vue'
import { InputImage, InputBase, LabelField } from '@/components/form'
import { ArrowRightIcon } from '@/components/icons'
import { Tabs, MetricsViews, TableMetric, ChartsFitness } from './index'
import { ref, reactive } from 'vue'
import {toast} from "../../utils/AlertPlugin";
import Loader from "@/components/Loader.vue";
import {useRouter} from "vue-router"

  const props = defineProps({
    isOpen: {
      type: Boolean,
      required: true
    },
    item: {
      type: Object,
      required: true
    },
    response: {
      type: Object,
      required: true,
      default: {}
    },
    score: {
      type: Object,
      required: true,
      default: {}
    },
  })

  const emit = defineEmits(['closeModal'])
  const date = Date.now();
  const fecha = new Date(date);
  const view = ref("home")
  const isLoading = reactive({status: true})
  const router = useRouter()
  const token = JSON.parse(localStorage.getItem('auth')).token
  const api_url = process.env.API_ENDPOINT;
  const limitDate = `${fecha.getFullYear()}-${fecha.getMonth() + 1}-${fecha.getDate()}`

  const tabs = [
    {
      title: "Metrics",
      value: 'metrics',
      icon: '',
      disabled: false,
    },
    {
      title: "Charts",
      value: 'charts',
      icon: '',
      disabled: false,
    },
    {
      title: "Metric Log",
      value: 'metric-log',
      icon: '',
      disabled: false,
    },
    {
      title: "Edit",
      value: 'edit',
      icon: '',
      disabled: false,
    },
  ]

  let player = reactive({
    height: props.item.body.full_height,
    name: props.item.name.full,
    lastName: props.item.name.last,
    email: props.item.email,
    // weight: props.item.body.weight_data,
    avatar: props.item.avatar,
  })

  let dataFitness = reactive({
    body_weight: "",
    bench_press: "",
    front_squat: "",
    back_squat: "",
    power_clean: "",
    dead_lift: "",
    yd_40_dash: "",
    yd_60_dash: "",
    // id: Object.keys(props.response).length === 0 ? "" : props.response[length].id,
    fitness_date: "",
  })

  const submitEditFitness = async () => {
    isLoading.status =!isLoading.status;
    let dataForm = new FormData();
    if (dataFitness.fitness_date == undefined || dataFitness.fitness_date == null || dataFitness.fitness_date == "") {
      dataForm.append('fitness_date', `${fecha.getMonth() + 1}/${fecha.getDate()}/${fecha.getFullYear()}`)
    } else {
      dataForm.append('fitness_date', dataFitness.fitness_date)
    }
    dataForm.append('user_id', props.item.id)
      // dataForm.append('fitness_date', fecha.toLocaleDateString())
    dataForm.append('bench_press', parseInt(dataFitness.bench_press == "" || dataFitness.bench_press == undefined ? 0 : dataFitness.bench_press))
    dataForm.append('front_squat', parseInt(dataFitness.front_squat == "" || dataFitness.front_squat == undefined ? 0 : dataFitness.front_squat))
    dataForm.append('back_squat', parseInt(dataFitness.back_squat == "" || dataFitness.back_squat == undefined ? 0 : dataFitness.back_squat))
    dataForm.append('power_clean', parseInt(dataFitness.power_clean == "" || dataFitness.power_clean == undefined ? 0 : dataFitness.power_clean))
    dataForm.append('dead_lift', parseInt(dataFitness.dead_lift == "" || dataFitness.dead_lift == undefined ? 0 : dataFitness.dead_lift))
    dataForm.append('yd_40_dash', parseFloat(dataFitness.yd_40_dash == "" || dataFitness.yd_40_dash == undefined ? 0.0 : dataFitness.yd_40_dash))
    dataForm.append('yd_60_dash', parseFloat(dataFitness.yd_60_dash == "" || dataFitness.yd_60_dash == undefined ? 0.0 : dataFitness.yd_60_dash))
    dataForm.append('body_weight', parseFloat(dataFitness.body_weight == "" || dataFitness.body_weight == undefined ? 0.0 : dataFitness.body_weight))
    const config = {
      headers: { Authorization: `Bearer ${token}` }
    };
    console.log("PASO");
    await axios.post(api_url+'player/fitness', dataForm, config).then(async function (response) {
      console.log("PASO");
      let tempResponse = response.data
      toast.fire({
        icon: 'success',
        title: 'Fitness Update',
        text: tempResponse.message,
      })
      isLoading.status =!isLoading.status;
      router.go("/dashboard")
    }).catch(async function (error){
      console.log(error.response);
      if (error.response.data.code === '001V' || error.response.status === 422 ) {
        const errorsObject = error.response.data.data.errors
        let errorMessage = ''
        let isAllow = false
        for (const [key, value] of Object.entries(errorsObject)) {
          if(!isAllow){
            isAllow = true
            errorMessage = value
          }
        }
        await toast.fire({
          icon: 'warning',
          title: 'Team Warning !!!',
          text: errorMessage,
        })
        isLoading.status =!isLoading.status;
      } else {
        await toast.fire({
          icon: 'error',
          title: 'Team Error !!!',
          text: "strike 3 is out, have a internal problem, " +error.response.data.message,
        })
        isLoading.status =!isLoading.status;
      }
    })
  }
</script>

<template>
  <Loader v-show="!isLoading.status"/>
  <TransitionRoot appear :show="isOpen" as="template">
    <Dialog as="div" @close="emit('closeModal')" class="relative z-10">
      <TransitionChild
        as="template"
        enter="duration-300 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-200 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-black bg-opacity-25" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto">
        <div
          class="flex items-center justify-center pl-10 md:pl-56 h-full p-4 text-center"
        >
          <TransitionChild
            as="template"
            enter="duration-300 ease-out"
            enter-from="opacity-0 scale-95"
            enter-to="opacity-100 scale-100"
            leave="duration-200 ease-in"
            leave-from="opacity-100 scale-100"
            leave-to="opacity-0 scale-95"
          >
            <DialogPanel
              class="max-w-[90%] min-w-[75%] h-max max-h-[70%] transform overflow-y-auto rounded-lg
              bg-fungo-gray2 text-left align-middle shadow-xl transition-all">

              <div>
                <div class="absolute top-5 right-2 md:right-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]" @click="emit('closeModal')">
                  <img alt="Icon close view" src="../../assets/img/register/cancel.svg">
                </div>
                <Tabs :tabs="tabs" active="tabs">
                  <template #content="{active}">

                    <template v-if="active == 'metrics'">
                      <MetricsViews :item="item" :view="view" :data="response" :score="score"></MetricsViews>
                    </template>

                    <template v-if="active == 'charts'">
                      <ChartsFitness :item="item" :response="response"></ChartsFitness>
                    </template>

                    <template v-if="active == 'metric-log'">
                      <TableMetric :tableData="response"></TableMetric>
                    </template>

                    <template v-if="active == 'edit'">
                      <div class="w-auto mt-2 px-2">
                        <div class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-3 gap-2 mt-5">
                          <div class="justify-self-center">
                            <div class="flex flex-col items-center justify-center w-full">
                              <div class="w-[85px] md:h-[125px] md:w-[125px] rounded-full ring-[7px] ring-fungo-gray8">
                                <template v-if="player.avatar != null">
                                  <img :src="player.avatar" alt="" class="h-[85px] w-[85px] md:h-[125px] md:w-[125px] rounded-full">
                                </template>
                                <img v-else src="../../assets/img/layout/logofungo-nav.png" alt="" class="h-[85px] w-[85px] md:h-[125px] md:w-[125px] rounded-full">
                              </div>
                            </div>
                            <div class="flex justify-center">
                              <div class="grid grid-cols-2 mt-4">
                                <div class="text-fungo-darkblue">
                                  <LabelField text="Name" :required="false" class=""/>
                                  <p class="font-fungo-500" v-text="player.name"></p>
                                </div>
                                <div class="text-fungo-darkblue">
                                  <LabelField text="E-Mail address" :required="false"/>
                                  <p class="font-fungo-500" v-text="item.email"></p>
                                </div>
                                <div class="text-fungo-darkblue">
                                  <LabelField text="Height" :required="false"/>
                                  <p class="font-fungo-500" v-text="`${player.height ?? ''} ft`"></p>
                                </div>

                                <!-- <div class="text-fungo-darkblue">
                                  <LabelField text="Weight" :required="false" />
                                  <p class="font-fungo-500" v-text="player.weight"></p>
                                </div> -->
                              </div>
                            </div>
                          </div>
                          <div class="justify-self-center mt-4 col-span-2">
                            <h1 class="text-2xl capitalize font-medium p-3 leading-6 text-fungo-darkblue bg-fungo-gray4 text-center font-fungo-800">
                              Current Information
                            </h1>
                            <div class="grid grid-cols-3 gap-5 lg:gap-2 mt-5 truncate px-3">
                              <div class="text-fungo-darkblue">
                                <LabelField text="Date" :required="false"/>
                                <InputBase v-model="dataFitness.fitness_date" inputType="date" :maxValue="limitDate" :minValue="'1970-12-31'" class="max-w-[100%]"/>
                              </div>
                              <div class="text-fungo-darkblue">
                                <LabelField text="Weight" :required="false"/>
                                <InputBase v-model="dataFitness.body_weight" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-fungo-darkblue">
                                <LabelField text="Front squat" :required="false"/>
                                <InputBase v-model="dataFitness.front_squat" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-fungo-darkblue">
                                <LabelField text="Bench press" :required="false"/>
                                <InputBase v-model="dataFitness.bench_press" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-fungo-darkblue">
                                <LabelField text="Back squat" :required="false"/>
                                <InputBase v-model="dataFitness.back_squat" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-fungo-darkblue">
                                <LabelField text="DeadLift" :required="false"/>
                                <InputBase v-model="dataFitness.dead_lift" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-fungo-darkblue">
                                <LabelField text="Clean" :required="false"/>
                                <InputBase v-model="dataFitness.power_clean" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-fungo-darkblue">
                                <LabelField text="40 Time" :required="false"/>
                                <InputBase v-model="dataFitness.yd_40_dash" inputType="number" class="max-w-[100%]"/>
                              </div>
                              <div class="text-fungo-darkblue">
                                <LabelField text="60 Time" :required="false"/>
                                <InputBase v-model="dataFitness.yd_60_dash" inputType="number" class="max-w-[100%]"/>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="w-full flex justify-center mt-4">
                          <div class="w-[100%] flex justify-center px-4 my-4">
                            <button class="btn-edit rounded-button-right" type="submit" @click="submitEditFitness()">
                              <img alt="ball" class="w-6 h-6 md:w-8 md:h-8 mx-2 md:mx-0"
                                src="../../assets/img/login/assteslogin/ballbutton.svg">
                              <span class="mx-2">Save</span>
                              <div class="text-white mx-2 animate-bounce-r"><ArrowRightIcon color="ffffff" w="50" h="50"/></div>
                            </button>
                          </div>
                        </div>
                      </div>
                    </template>
                  </template>
                </Tabs>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
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

.btn-edit{
  @apply grid place-items-center grid-flow-col flex-row w-[250px] lg:w-[300px] rounded-t-[30px] rounded-r-[10px] rounded-b-[10px] rounded-l-[30px]
    px-2 py-1 text-xl md:text-[16px] lg:text-[20px] bg-fungo-red text-white hover:bg-fungo-red-hover
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
