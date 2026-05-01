<script setup>
import { computed } from 'vue'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'

defineProps({
  isOpen: {
    type: Boolean,
    required: true
  },
  // picked: {
  //   type: String,
  //   required: true
  // },
  // endNote: {
  //   type: String,
  //   required: false,
  //   default: ''
  // }
})

const emit = defineEmits(['update:picked', 'update:endNote'])

</script>

<template>
  <TransitionRoot :show="isOpen" appear as="template">
    <Dialog as="div" class="relative z-10" @close="closeModal">
      <TransitionChild as="template" enter="duration-300 ease-out" enter-from="opacity-0" enter-to="opacity-100"
        leave="duration-200 ease-in" leave-from="opacity-100" leave-to="opacity-0">
        <div class="fixed inset-0 bg-black bg-opacity-25" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
          <TransitionChild as="template" enter="duration-300 ease-out" enter-from="opacity-0 scale-95"
            enter-to="opacity-100 scale-100" leave="duration-200 ease-in" leave-from="opacity-100 scale-100"
            leave-to="opacity-0 scale-95">
            <DialogPanel
              class="w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all">
              <DialogTitle as="h2" class="text-2xl font-medium leading-6 text-fungo-red mb-3">
                End Batting Practice
              </DialogTitle>
              <div class="container">
                <div class="grid grid-flow-row gap-3">
                  <div class=" p-5 border bg-fungo-gray4 grid grid-cols-3 place-items-center">
                    <div>
                      <svg class="w-[40px] h-[40px]" fill="none" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
                        <path clip-rule="evenodd"
                          d="M5 30C5 16.2 16.175 5 29.975 5C43.8 5 55 16.2 55 30C55 43.8 43.8 55 29.975 55C16.175 55 5 43.8 5 30ZM10 30C10 41.05 18.95 50 30 50C41.05 50 50 41.05 50 30C50 18.95 41.05 10 30 10C18.95 10 10 18.95 10 30ZM38.75 27.5C40.825 27.5 42.5 25.825 42.5 23.75C42.5 21.675 40.825 20 38.75 20C36.675 20 35 21.675 35 23.75C35 25.825 36.675 27.5 38.75 27.5ZM25 23.75C25 25.825 23.325 27.5 21.25 27.5C19.175 27.5 17.5 25.825 17.5 23.75C17.5 21.675 19.175 20 21.25 20C23.325 20 25 21.675 25 23.75Z"
                          fill="#082247" fill-rule="evenodd" />
                        <path
                          d="M37 39C37 37.1435 36.2625 35.363 34.9497 34.0503C33.637 32.7375 31.8565 32 30 32C28.1435 32 26.363 32.7375 25.0503 34.0503C23.7375 35.363 23 37.1435 23 39"
                          stroke="#082247" stroke-linecap="round" stroke-width="4" />
                      </svg>
                    </div>
                    <div class="grid grid-flow-row3 gap-1">
                      <div class="text-fungo-blue font-fungo-700 text-sm">Status</div>
                      <div class="text-fungo-darkblue font-fungo-700 "> In progress</div>
                      <div>
                        <progress class="rounded overflow-hidden h-[7px] in-proress w-[70px]" max="100" value="50">
                        </progress>
                      </div>
                    </div>
                    <div><input v-model="picked" checked class="h-[30px] w-[30px]" name="end-session" type="radio"
                        value="progress" @change="emit('update:picked', $event.target.value)"></div>
                  </div>
                  <div class=" p-5 border bg-fungo-gray4 grid grid-cols-3 place-items-center">
                    <div>
                      <svg class="w-[40px] h-[40px]" fill="none" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
                        <path clip-rule="evenodd"
                          d="M5 30C5 16.2 16.175 5 29.975 5C43.8 5 55 16.2 55 30C55 43.8 43.8 55 29.975 55C16.175 55 5 43.8 5 30ZM10 30C10 41.05 18.95 50 30 50C41.05 50 50 41.05 50 30C50 18.95 41.05 10 30 10C18.95 10 10 18.95 10 30ZM38.75 27.5C40.825 27.5 42.5 25.825 42.5 23.75C42.5 21.675 40.825 20 38.75 20C36.675 20 35 21.675 35 23.75C35 25.825 36.675 27.5 38.75 27.5ZM25 23.75C25 25.825 23.325 27.5 21.25 27.5C19.175 27.5 17.5 25.825 17.5 23.75C17.5 21.675 19.175 20 21.25 20C23.325 20 25 21.675 25 23.75Z"
                          fill="#082247" fill-rule="evenodd" />
                        <path
                          d="M23 33C23 34.8565 23.7375 36.637 25.0503 37.9497C26.363 39.2625 28.1435 40 30 40C31.8565 40 33.637 39.2625 34.9497 37.9497C36.2625 36.637 37 34.8565 37 33"
                          stroke="#082247" stroke-linecap="round" stroke-width="4" />
                      </svg>
                    </div>
                    <div class="grid grid-flow-row3 gap-1">
                      <div class="text-fungo-blue font-fungo-700 text-sm">Status</div>
                      <div class="text-fungo-darkblue font-fungo-700 "> Completed</div>
                      <div>
                        <progress class="rounded overflow-hidden h-[7px] completed w-[70px]" max="100" value="100">
                        </progress>
                      </div>
                    </div>
                    <div><input v-model="picked" class="h-[30px] w-[30px]" name="end-session" type="radio"
                        value="completed" @change="emit('update:picked', $event.target.value)"></div>
                  </div>
                </div>
                <div v-show="picked === 'completed'" class="grid grid-flow gap-1 mt-3">End
                  Note<textarea v-model="endNote" @change="emit('update:endNote', $event.target.value)"></textarea></div>
                <div class="grid mt-5">
                  <button class="rounded-xl rounded-l-3xl border bg-fungo-red text-white mx-auto"
                    @click="$emit('endPractice')">
                    <div class="grid grid-cols-2 w-[200px]">
                      <div class="m-1 p-1"><img class="w-[20px] h-[20px] xl:w-[30px] xl:h-[30px]"
                          src="../../assets/img/login/assteslogin/ballbutton.png"></div>
                      <div class="grid content-center items-center mr-8 text-[16px] font-fungo-700 -ml-12">Finish
                        Training
                      </div>
                    </div>
                  </button>
                </div>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<style>
progress.in-proress::-webkit-progress-value {
  background: #FFB457;
}
progress.completed::-webkit-progress-value {
  background: #35A800;
}
progress::-webkit-progress-bar {
  background: #DBDFF1;
}
</style>