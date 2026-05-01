<template>
  <div class=" bg-white p-3 ">
    <div class="grid grid-cols-2 gap-0.5 justify-items-center text-[24px]">
      <div id="content-contact" class="grid gap-y-1.5">
        <div class="text-fungo-red text-center ">Pitch</div>
        <button
          class="button-ct pt"
          value="FB"
          @click="$emit('setTypeThrow', $event)"
        >
          FB
        </button>
        <button
          class="button-ct pt"
          value="CH"
          @click="$emit('setTypeThrow', $event)"
        >
          CH
        </button>
        <button
          class="button-ct pt"
          value="CB"
          @click="$emit('setTypeThrow', $event)"
        >
          CB
        </button>
        <button
          class="button-ct pt"
          value="SL"
          @click="$emit('setTypeThrow', $event)"
        >
          SL
        </button>
        <button
          class="button-ct pt"
          value="OTHER"
          @click="$emit('setTypeThrow', $event)"
        >
          Other
        </button>
      </div>
      <div id="content-contact" class="grid gap-y-1.5">
        <div class="text-fungo-red text-center">Trajectory</div>
        <button
          class="button-ct tj"
          value="GB"
          @click="$emit('setTrajectory', $event)"
        >
          GB
        </button>
        <button
          class="button-ct tj"
          value="FB"
          @click="$emit('setTrajectory', $event)"
        >
          FLY
        </button>
        <button
          class="button-ct tj"
          value="LD"
          @click="$emit('setTrajectory', $event)"
        >
          LD
        </button>
        <button
          class="button-ct tj"
          value="F"
          @click="$emit('setTrajectory', $event)"
        >
          FOUL
        </button>
        <button
          class="button-ct tj"
          value="SM"
          @click="$emit('setTrajectory', $event)"
        >
          SW / MISS
        </button>
        <button
          class="button-ct tj"
          value="HBP"
          @click="$emit('setTrajectory', $event)"
        >
          HBP
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
