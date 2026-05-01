<script setup>
import { ref, defineEmits} from 'vue';
import ArrowDownIcon from "../icons/ArrowDownIcon.vue";
const show = ref(false)
const stringSelected = ref('')
const optionsSelected = ref({
  'B': [],
  'P': [],
  'C': [],
  'EV': [],
  "LT": [],
  "WB": [],
  "L": []
})
const sessions=  ref({
    "B": {
      'name': 'Hitting',
      'collection': {
        0: 'Totals', 1: 'Percentages',
        2: 'Average Velocity - Breakdown', 3: 'Max Velocity',
        4: 'Left Type Of Hit %', 5: 'Right Type Of Hit %',
        6: 'Middle Type Of Hit %', 7: 'Left Quality Of Contact %',
        8: 'Right Quality Of Contact %', 9: 'Middle Quality Of Contact %'
      }
    },
    "P": {
      'name': 'Pitching',
      'collection': {
        10: 'Totals', 11: 'Percentages',
        12: 'Average Velocity Breakdown', 13: 'Top Velocity Breakdown',
        14: 'FastBalls %', 15: 'Curve Balls %',
        16: 'Change Up %', 17: 'Slider %',
        18: 'Other %', 19: 'Ground Balls %',
        20: 'Line Drives %', 21: 'Fly Balls %',
        22: 'Pop flies %', 23: 'Foul %',
        24: 'FastBalls Strike %', 25: 'Curve Balls strike%',
        26: 'Change up strike %', 27: 'Slider strike %',
        28: 'Other strike %'
      }
    },
    "C": {
      'name': 'Cage',
      'collection': {
        29: 'Launch Angle Totals', 30: 'Launch Angle %',
        31: 'Launch Angle Average Exit Velocity', 32: 'Spray Angle Totals',
        33: 'Spray Angle %', 34: 'Spray Angle average Exit Velocity'
      },
    },
    "EV": {
      'name': 'Exit Velocity',
      'collection': {
        35: 'Total Swings', 36: '% Swings',
        37: 'Average exit velocity swings', 38: 'Top exit velocity swings'
      }
    },
    "LT": {
      'name': 'Long Toss',
      'collection': {
        39: 'Distance Total', 40: 'Distance %',
        41: 'Distance Average', 42: 'Total Throws',
        43: 'Max Distance', 44: 'Average Distance'
      }
    },
    "WB": {
      'name': 'Weigthed Ball',
      'collection': {
        45: 'Totals', 46: 'Average',
        47: 'Max Velocity'
      }
    },
    "L": {
      'name': 'Live AB',
      'collection': {
        48: 'Hitter Basic', 49: 'hitter advance',
        50: 'pitcher basic', 51: 'pitcher advance',
        52: 'hitter pitch breakdown', 53: 'hitter contact',
        54: 'hitter trajectory', 55: 'hitter velocity',
        56: 'pitcher pitch breakdown', 57: 'pitcher contact',
        58: 'pitcher velocity',
      }
    }
  })

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => {}
  },
  seletedSessionShow: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:modelValue'])

const addOption = (value, key) => {
  if(optionsSelected.value[key].includes(Number.parseInt(value))){
    let options = optionsSelected.value[key].map((element)=>Number.parseInt(element))
    optionsSelected.value[key] = options.filter((val) => val!= value)
  }else{
    optionsSelected.value[key].push(Number.parseInt(value))
  }

  emit('update:modelValue', optionsSelected.value)
  stringSelected.value = key
  showSelectedString()
}

const removeAll = () => {
  for (const key in sessions.value) {
    optionsSelected.value[key] = []
  }

  emit('update:modelValue', optionsSelected.value)
  stringSelected.value = ''
}

const selectAll = () => {
  removeAll()
  for (const key of props.seletedSessionShow) {
    optionsSelected.value[key] = [...Object.keys(sessions.value[key].collection)].map((element)=>Number.parseInt(element))
  }

  emit('update:modelValue', optionsSelected.value)
  showSelectedString()
}

const showDrop = () => {
  show.value = !show.value
}

const showSelectedString = () => {
  stringSelected.value = ''
  for (const key in optionsSelected.value) {
    const element = optionsSelected.value[key];
      for (const iterator of element) {
        stringSelected.value += sessions.value[key].collection[iterator] + ','
      }
    }
}

</script>
<template>
  <div class="relative" v-on:mouseleave="show = false">
    <div v-on:click="showDrop()"
    :class="show == false ? 'flex justify-between w-full bg-white rounded-lg border border-black':
    'flex justify-between w-full bg-white rounded-lg border border-black border-b-0 rounded-b-none'">
      <span class="h-5 overflow-clip self-center ml-2">{{ stringSelected }}</span>
      <ArrowDownIcon v-if="optionsSelected.B.length > 0 || optionsSelected.P.length > 0
        || optionsSelected.EV.length > 0 || optionsSelected.L.length > 0
        || optionsSelected.C.length > 0 || optionsSelected.WB.length > 0
        || optionsSelected.LT.length > 0" color="046C4E"/> <ArrowDownIcon v-else/>
    </div>
    <div v-if="show" class="absolute z-10 w-full bg-white p-4 rounded-lg border border-black h-[400px] overflow-y-scroll fixed bottom-0">
      <div class="flex justify-between">
        <button type="button" class="w-1/2 mx-2 border-b-2 border-b-green-700" v-on:click="selectAll">Select all</button>
        <button type="button" class="w-1/2 mx-2 border-b-2 border-b-green-700" v-on:click="removeAll">Unselect all</button>
      </div>
      <div v-for="(option, optionIndex) in sessions">
        <ol v-if="seletedSessionShow.includes(optionIndex)">
          <li class="text-gray-500 font-black py-1">{{ option.name }}</li>
            <ol v-for="(item, index) in option.collection" v-on:click="addOption(index, optionIndex)">
              <span v-if="optionsSelected[optionIndex].includes(Number.parseInt(index))" class="font-black text-base text-green-700 rounded-full ">✓</span>
              <span class="px-[9px]" v-else></span>
              {{ item }}
            </ol>
        </ol>
      </div>
  </div>
  </div>

</template>
