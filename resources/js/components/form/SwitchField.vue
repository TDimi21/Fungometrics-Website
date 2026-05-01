<script setup>
  import { computed, ref } from 'vue'
  import { Switch, SwitchGroup, SwitchLabel } from '@headlessui/vue';

  const props = defineProps({
    modelValue: [String, Number, Boolean, Object],
    label: {
      type: String,
    },
    labelPosition: {
      type: String,
      required: false,
      default: 'left'
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
  })

</script>

<template>
  <slot>
  <SwitchGroup>
    <div class="flex items-center">
      <SwitchLabel class="mr-4" v-if="props.labelPosition==='left'">{{ props.label }}</SwitchLabel>
      <Switch
        v-model="value"
        :class='value ? "bg-blue-100" : "bg-gray-100"'
        class="relative inline-flex h-8 w-14 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
      >
        <span
          :class='value ? "translate-x-6" : "translate-x-1"'
          class="inline-block h-6 w-6 transform rounded-full bg-white transition-transform bg-fungo-lightblue"
        />
      </Switch>
      <SwitchLabel class="ml-4" v-if="props.labelPosition==='right'">{{ props.label }}</SwitchLabel>
    </div>
  </SwitchGroup>
  </slot>
</template>
