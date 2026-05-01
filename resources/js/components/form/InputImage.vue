<script setup>
import {computed, reactive, onMounted} from 'vue';
import defaultImg from '../../assets/img/layout/logofungo-nav.png'

let defaultImage = defaultImg


const props = defineProps({
  modelValue: [String, Number, Boolean, Object],
  label: {
    type: String,
    required: true
  },
  inputClasses: {
    type: String,
    required: false,
  },
});

const image = reactive({
  src: props.modelValue != null && props.modelValue != HTMLInputElement ? props.modelValue : defaultIamge
})
const emit = defineEmits(['update:modelValue']);

let value = computed({
  get() {
    return props.modelValue
  },
  set(value) {
    emit('update:modelValue', value)
  },
})



const onFileChange = (e) => {
  image.src = URL.createObjectURL(e.target.files[0])
}

const resetInputFile = (file) => {
  file.value = null
  image.src = defaultImage
}
</script>

<template>
  <slot>
    <form @submit.prevent>
      <div class="w-full relative">

        <input
            ref="value"
            accept="image/png, image/gif, image/jpeg"
            class="hidden"
            type="file"
            @change="onFileChange">
      </div>
      <div class="w-full h-full ">
        <div class="flex justify-between items-center">
          <p class="text-fungo-darkblue text-lg">{{ props.label }}</p>
          <div>
            <button class="bg-[#01CDCC] rounded-lg p-3" @click="value.click()">
              <img alt="Edit picture" src="@/assets/img/icons/i-edit.svg">
            </button>
            <button class="bg-fungo-red rounded-lg p-3 ml-1" @click="resetInputFile(value)" @submit.prevent>
              <img alt="Remove picture" src="@/assets/img/icons/i-remove.svg">
            </button>
          </div>
        </div>

        <div
            :class="inputClasses"
            class="bg-white rounded-md border border-fungo-darkblue min-h-[90px] mt-3.5 py-7 flex items-center justify-center">
          <img
            v-if="image.src == ''"            
            :src= defaultImage
            alt="Picture"
            class="object-center mx-auto">
          <img
            v-else
            ref="img-source-data"
            :class="{'w-36 h-36 object-cover rounded-full border-[11px] border-[#D9D9D9]' : image.src != defaultImage }"
            :src="image.src"
            alt="Picture"
            class="object-center mx-auto">
        </div>
      </div>
    </form>
  </slot>
</template>
