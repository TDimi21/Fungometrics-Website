<script setup>
import {ArrowLeftIcon, ArrowRightIcon} from '../icons/'

const props = defineProps({
  direction: {
    type: String,
    required: false,
    default: 'right',
  },
  label: {
    type: String,
    required: true,
  },
  disabled: {
    type: Boolean,
    required: false,
    default: false,
  },
  color: {
    type: String,
    required: false,
    default: 'dark' // posible: dark, red
  },
  iconColor: {
    type: String,
    required: false,
    default: 'fff' // posible: dark, red
  },
  shadow: {
    type: Boolean,
    required: false,
    default: false
  },
  containerClass: {
    type: String,
    required: false,
    default: ''
  },
  buttonWidth: {
    type: String,
    required: false,
    default: 'max-w-[350px]'
  }
});

const classes = {
  'flex-row rounded-button-right': props.direction === 'right',
  'flex-row-reverse rounded-button-left': props.direction === 'left',
  'bg-fungo-darkblue text-white hover:bg-fungo-darkblue-hover': props.color === "dark",
  'bg-fungo-red text-white hover:bg-fungo-red-hover': props.color === "red",
  'shadow-fungo-but-shadow  shadow-slate-900': props.shadow
}

const iconClasses = {
  'text-white': props.color === "dark" || props.color === "red",
  'animate-bounce-r': props.direction === 'right',
  'animate-bounce-l': props.direction === 'left',
}

const icon = props.direction === 'right'
  ? ArrowRightIcon
  : ArrowLeftIcon;
</script>

<template>
  <div :class="containerClass">
    <button
      :class="[classes, buttonWidth]"
      :disabled="props.disabled"
      class="grid place-items-center grid-flow-col  px-2 py-1 text-xl md:text-[12px] lg:text-[16px]"
      type="button"
      v-bind="$attrs"
      @click="$emit('click')"
    >
      <img alt="button register coach" class="w-6 h-6 md:w-4 md:h-4 mx-2"
           src="../../assets/img/login/assteslogin/ballbutton.svg">
      <span v-html="props.label" class="mx-2"></span>
      <div v-if="direction === 'right'" :class="iconClasses" class="mx-2">
        <ArrowRightIcon :color="props.iconColor" w="30" h="30"/>
      </div>
      <div v-if="direction === 'left'" :class="iconClasses" class="mx-2">
        <ArrowLeftIcon :color="props.iconColor" w="30" h="30"/>
      </div>
    </button>
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

@keyframes bouncel {
  0%, 100% {
    transform: translateX(25%);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  50% {
    transform: none;
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
}

.animate-bounce-l {
  animation: bouncel 1s infinite;
}

.rounded-button-right {
  border-radius: 30px 10px 10px 30px;
}

.rounded-button-left {
  border-radius: 10px 30px 30px 10px;
}

.size-icon {
  @apply w-2 h-2
}
</style>
