<script setup>
import { computed, ref } from 'vue'
import { LabelField, InputBase } from '.';
import { EyeIcon, EyeOffIcon } from '../icons'

// See InputBase Props for all variants
const props = defineProps({
  label: String,
  required: {
    type: Boolean,
    required: false,
    default: false
  },
  modelValue: [String, Number, Boolean, Object],
  labelClasses: {
    type: String,
    required: false,
  },
  transparent: {
    type: Boolean,
  }
});

const emit = defineEmits(['update:modelValue']);

const value = computed({
  get() {
    return props.modelValue
  },
  set(value) {
    emit('update:modelValue', value)
  }
});

const show = ref(false);

const showHidePassword = () => {
  if (value.value.length <= 0) return
  show.value = !show.value
}
</script>

<template>
  <slot>
  <LabelField :text="props.label" :required="props.required" :classes="props.labelClasses">
    <div class="relative">
      <div
        class="flex absolute inset-y-0 right-0 items-center px-3 bg-fungo-lightblue rounded-l-0 rounded-r cursor-pointer input-pass-decorator"
        @click="showHidePassword"
      >
        <EyeIcon color="26364D" v-if="!show" />
        <EyeOffIcon color="26364D" v-else />
      </div>
      <InputBase :decorator="true" v-model="value" :input-type="show ? 'text' : 'password'" v-bind="$attrs" input-classes="input-pass-text block w-full rounded-l-0" :transparent="transparent" />
    </div>
  </LabelField>
  </slot>
</template>
<style scoped>
.input-pass-decorator{
  margin: 1px;
  width: 48px;
  position: absolute;
}

</style>
