<script setup>
import { ref, defineEmits, onMounted } from 'vue';
import ArrowDownIcon from "../icons/ArrowDownIcon.vue";
const show = ref(false)
const optionsSelected = ref([])
const stringSelected = ref('')
const props = defineProps({
  options: Object,
  modelValue: {
    type: Array,
    default: () => []
  },
  isSelectAll: {
    type: Boolean,
    requires: false,
    default: false
  }
})

const emit = defineEmits(['update:modelValue'])

const addOption = (value) => {
  if(optionsSelected.value.includes(value)){
    let options = optionsSelected.value
    optionsSelected.value = options.filter((val) => val!= value)
  }else{
    optionsSelected.value.push(value)
  }

  emit('update:modelValue', optionsSelected.value)
  showSelectedString()
}

const removeAll = () => {
  optionsSelected.value = []
  emit('update:modelValue', optionsSelected.value)
  stringSelected.value = ''
}

const selectAll = () => {
  optionsSelected.value = Object.keys(props.options)
  emit('update:modelValue', optionsSelected.value)
  showSelectedString()
}

const showDrop = () => {
  show.value = !show.value
}

const showSelectedString = () => {
  stringSelected.value = ''
  for (const key of optionsSelected.value) {
      stringSelected.value += props.options[key] + ', '
    }
}

onMounted(() => {
  if (props.options != undefined && props.isSelectAll) {
    selectAll()
  }
})

</script>
<template>
  <div class="relative" v-on:mouseleave="show = false">
    <div v-on:click="showDrop()"
    :class="show == false ? 'flex justify-between w-full bg-white rounded-lg border border-black':
    'flex justify-between w-full bg-white rounded-lg border border-black border-b-0 rounded-b-none'">
      <span class="h-5 overflow-clip self-center ml-2">{{ stringSelected }}</span>
      <ArrowDownIcon v-if="optionsSelected.length > 0" color="046C4E"/> <ArrowDownIcon v-else/>
    </div>
    <div v-if="show" class="absolute z-10 w-full bg-white p-4 rounded-lg border border-black border-t-0 rounded-t-none">
      <div class="flex justify-between">
        <button type="button" class="w-1/2 mx-2 border-b-2 border-b-green-700" v-on:click="removeAll">Unselect all</button>
        <button type="button" class="w-1/2 mx-2 border-b-2 border-b-green-700" v-on:click="selectAll">Select all</button>
      </div>
      <div v-for="(option, index) in props.options" v-on:click="addOption(index)">
      <div class="mt-1">
        <span v-if="optionsSelected.includes(index)" class="font-black text-base text-green-700 rounded-full ">✓</span>
        <span class="px-[9px]" v-else></span>
        {{ option }}
      </div>
    </div>
  </div>
  </div>

</template>
