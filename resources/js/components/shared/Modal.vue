<script setup>
import { Dialog, DialogPanel, DialogTitle, TransitionRoot, TransitionChild } from '@headlessui/vue'

const props = defineProps({
  modalTitle: {
    type: String,
    required: true
  },
  isOpen: {
    type: Boolean,
    required: true
  },
  variant: {
    type: String,
    default: 'light',
    validator: value => ['light', 'dark'].includes(value)
  }
})

const emit = defineEmits(['close'])
</script>

<template>
  <TransitionRoot appear :show="isOpen" as="template">
    <Dialog as="div" @close="emit('close')" class="relative z-50">
      <TransitionChild
        as="template"
        enter="duration-300 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-200 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto">
        <div
          class="flex min-h-full items-center justify-center p-4 text-center"
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
              class="relative h-max max-h-[85vh] w-full transform overflow-y-auto p-6 text-left align-middle shadow-2xl transition-all sm:p-8"
              :class="props.variant === 'dark'
                ? 'max-w-3xl rounded-[26px] border border-white/15 bg-[#0b142b]/95 text-white'
                : 'max-w-3xl rounded-2xl bg-white text-slate-900'"
            >
              <DialogTitle
                as="h3"
                class="text-2xl capitalize font-black leading-tight"
                :class="props.variant === 'dark' ? 'text-white' : 'text-gray-900'"
              >
                {{ modalTitle }}
              </DialogTitle>
              <hr class="mt-4" :class="props.variant === 'dark' ? 'border-white/10' : 'border-slate-200'">
              <div class="mt-5">
                <slot name="content" />
              </div>

              <div class="mt-4">
                <slot name="actions" />
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
