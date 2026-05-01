<template>
  <div class=" bg-white p-3 ">
    <div class="grid grid-cols-2 gap-0.5 justify-items-center text-[24px]">
      <div id="content-contact" class="grid gap-y-1.5">
        <div class="text-fungo-red text-center ">Total bases</div>
        <button
          class="button-ct tb"
          value="1"
          @click="$emit('setTotalBase', $event)"
        >
          1b
        </button>
        <button
          class="button-ct tb"
          value="2"
          @click="$emit('setTotalBase', $event)"
        >
          2b
        </button>
        <button
          class="button-ct tb"
          value="3"
          @click="$emit('setTotalBase', $event)"
        >
          3b
        </button>
        <button
          class="button-ct tb"
          value="5"
          @click="$emit('setTotalBase', $event)"
        >
          HR
        </button>
        <button
          class="button-ct tb"
          value="8"
          @click="$emit('setTotalBase', $event)"
        >
          Out/E
        </button>
      </div>
      <div id="content-contact" class="grid gap-y-1.5">
        <div class="text-fungo-red">Quality of contact</div>
        <button
          class="button-ct qc"
          value="W"
          @click="$emit('setQtyOfContact', $event)"
        >
          Weak
        </button>
        <button
          class="button-ct qc"
          value="A"
          @click="$emit('setQtyOfContact', $event)"
        >
          Average
        </button>
        <button
          class="button-ct qc"
          value="H"
          @click="$emit('setQtyOfContact', $event)"
        >
          Hard
        </button>
      </div>
    </div>
  </div>
</template>

<style>
.button-ct {
  @apply border border-fungo-darkblue rounded h-[2em] w-[5em]  focus:bg-fungo-darkblue focus:text-white;
}

.active-btn-trajectory, .active-btn-contact {
  @apply bg-fungo-darkblue text-white;
}
</style>
