<script setup>
import { InputBase, LabelField} from '@/components/form'
import { ref, reactive, onMounted } from 'vue'
import { TableTop, TableTotal } from './index'

  const props = defineProps({
    item: {
      type: Object,
      required: true
    },
    data: {
      type: Object,
      required: true,
      default: {}
    },
    score: {
      type: Object,
      required: true,
      default: {}
    },
    view: {
      type: String,
      required: true,
      default: 'home'
    },
  })

  onMounted(() => {
    updateInfo()
  })

  let player = reactive({
    avatar: props.item.avatar,
    type: [],
    heightFt: props.item.body.full_height,
    heightInch: 0,
    name: props.item.name.full,
    lastName: props.item.name.last,
    born: '',
    email: '',
    password: '',
    // weight: props.item.body.weight_data,
    mobileNumber: '',
  })

  let dataFitness = ref({
    body_weight: "",
    bench_press: "",
    front_squat: "",
    back_squat: "",
    power_clean: "",
    dead_lift: "",
    yd_40_dash: "",
    yd_60_dash: "",
    id: "",
  })


  const updateInfo = () =>{
    let valueWeight = 0
    let valueBench = 0
    let valueFront = 0
    let valueBack = 0
    let valuePower = 0
    let valueDead = 0
    let value40 = 0
    let value60 = 0

    if (Object.keys(props.data).length != 0) {
      const orderArray = props.data.slice().sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime())
      orderArray.forEach(item => {
        if (item) {
          valueWeight =  item.body_weight == 0 || item.body_weight == undefined ? valueWeight : item.body_weight
          valueBench = item.bench_press == 0 || item.bench_press == undefined ? valueBench : item.bench_press
          valueFront = item.front_squat == 0 || item.front_squat == undefined ? valueFront : item.front_squat
          valueBack = item.back_squat == 0 || item.back_squat == undefined ? valueBack : item.back_squat
          valuePower = item.power_clean == 0 || item.power_clean == undefined ? valuePower : item.power_clean
          valueDead = item.dead_lift == 0 || item.dead_lift == undefined ? valueDead : item.dead_lift
          value40 = item.yd_40_dash == 0 || item.yd_40_dash == undefined ? value40 : item.yd_40_dash
          value60 = item.yd_60_dash == 0 || item.yd_60_dash == undefined ? value60 : item.yd_60_dash
        }
      })

      dataFitness.value = {
        body_weight: valueWeight,
        bench_press: valueBench,
        front_squat: valueFront,
        back_squat: valueBack,
        power_clean: valuePower,
        dead_lift: valueDead,
        yd_40_dash: value40,
        yd_60_dash: value60,
      }
    }
  }

  const boolTop = ref(false);
  const boolTotal = ref(false);
</script>

<template>
  <div v-if="view == 'home'">
    <div class="w-auto mt-2 px-2">
      <div class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-3 gap-2 mt-8">
        <div class="">
          <div class="flex flex-col items-center justify-center w-full">
            <div class="w-[85px] md:h-[125px] md:w-[125px] rounded-full ring-[7px] ring-fungo-gray8">
              <template v-if="item.avatar != null">
                <img :src="player.avatar" alt="" class="h-[85px] w-[85px] md:h-[125px] md:w-[125px] rounded-full">
              </template>
              <img v-else src="../../assets/img/layout/logofungo-nav.png" alt="" class="h-[85px] w-[85px] md:h-[125px] md:w-[125px] rounded-full">
            </div>
          </div>
          <div class="flex justify-center">
            <div class="grid grid-cols-2 mt-2">
              <div class="text-fungo-darkblue">
                <LabelField text="Name" :required="false"/>
                <p class="font-fungo-500" v-text="player.name"></p>
              </div>
              <div class="text-fungo-darkblue">
                <LabelField text="E-Mail address" :required="false"/>
                <p class="font-fungo-500" v-text="item.email"></p>
              </div>
              <div class="text-fungo-darkblue">
                <LabelField text="Height" :required="false"/>
                <p class="font-fungo-500" v-text="`${player.heightFt ?? ''} ft`"></p>
              </div>

              <!-- <div class="text-fungo-darkblue">
                <LabelField text="Weight" :required="false" />
                <p class="font-fungo-500" v-text="player.weight"></p>
              </div> -->
            </div>
          </div>

          <div class="grid grid-cols-3 gap-4 mt-5">
            <button @click="view = 'home'" class="bg-fungo-red text-white border border-fungo-darkblue px-4 py-1 rounded-md">
              Home
            </button>
            <button @click="view = 'top'" class="bg-white text-fungo-darkblue px-4 py-1 rounded-md border border-fungo-darkblue">
              Top 10
            </button>
            <button @click="view = 'totals'" class="bg-white text-fungo-darkblue px-4 py-1 rounded-md border border-fungo-darkblue">
              Totals
            </button>
          </div>
        </div>
        <div class="justify-self-center mt-4 col-span-2 ">
          <h1 class="text-2xl capitalize font-medium leading-6 text-fungo-darkblue p-3 text-center font-fungo-800 bg-fungo-gray4">
            Current Information
          </h1>
          <div class="grid grid-cols-3 gap-5 lg:gap-2 mt-5 truncate">
            <div class="text-fungo-darkblue">
              <LabelField text="Weight" :required="false"/>
              <p v-text="dataFitness.body_weight" class="font-fungo-500"></p>
            </div>
            <div class="text-fungo-darkblue">
              <LabelField text="Front squat" :required="false"/>
              <p v-text="dataFitness.front_squat" class="font-fungo-500"></p>
            </div>
            <div class="text-fungo-darkblue">
              <LabelField text="Bench press" :required="false"/>
              <p v-text="dataFitness.bench_press" class="font-fungo-500"></p>
            </div>
            <div class="text-fungo-darkblue">
              <LabelField text="Back squat" :required="false"/>
              <p v-text="dataFitness.back_squat" class="font-fungo-500"></p>
            </div>
            <div class="text-fungo-darkblue">
              <LabelField text="DeadLift" :required="false"/>
              <p v-text="dataFitness.dead_lift" class="font-fungo-500"></p>
            </div>
            <div class="text-fungo-darkblue">
              <LabelField text="Clean" :required="false"/>
              <p v-text="dataFitness.power_clean" class="font-fungo-500"></p>
            </div>
            <div class="text-fungo-darkblue">
              <LabelField text="40 Time" :required="false"/>
              <p v-text="dataFitness.yd_40_dash" class="font-fungo-500"></p>
            </div>
            <div class="text-fungo-darkblue">
              <LabelField text="60 Time" :required="false"/>
              <p v-text="dataFitness.yd_60_dash" class="font-fungo-500"></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div v-if="view == 'top'">
    <div class="flex justify-center">
      <div class="grid grid-cols-3 gap-4 mt-5 max-w-[40%]">
        <button @click="view = 'home'" class="bg-white text-fungo-darkblue border border-fungo-darkblue px-4 py-2 rounded-md">
          Home
        </button>
        <button @click="view = 'top'" class="bg-fungo-red text-white border border-fungo-darkblue px-4 py-2 rounded-md">
          Top 10
        </button>
        <button @click="view = 'totals'" class="bg-white text-fungo-darkblue border border-fungo-darkblue px-4 py-2 rounded-md">
          Totals
        </button>
      </div>
    </div>
    <h1 class="text-2xl capitalize font-medium leading-6 text-fungo-darkblue p-3 text-center font-fungo-800 bg-fungo-gray4 mx-10 mt-5">
      Top 10 Matrix Log
    </h1>
    <TableTop :tableData="score" :isLoading="boolTop"></TableTop>
    <!-- <TableTop :tableData="score"></TableTop> -->
  </div>

  <div v-if="view == 'totals'">
    <div class="flex justify-center">
      <div class="grid grid-cols-3 gap-4 mt-5 max-w-[40%]">
        <button @click="view = 'home'" class="bg-white text-fungo-darkblue border border-fungo-darkblue px-4 py-2 rounded-md">
          Home
        </button>
        <button @click="view = 'top'" class="bg-white text-fungo-darkblue border border-fungo-darkblue px-4 py-2 rounded-md">
          Top 10
        </button>
        <button @click="view = 'totals'" class="bg-fungo-red text-white border border-fungo-darkblue px-4 py-2 rounded-md">
          Totals
        </button>
      </div>
    </div>
    <h1 class="text-2xl capitalize font-medium leading-6 text-fungo-darkblue p-3 text-center font-fungo-800 bg-fungo-gray4 mx-10 mt-5">
      Totals Matrix Log
    </h1>
    <TableTotal :tableData="data" :isLoading="boolTotal"></TableTotal>
  </div>
</template>
