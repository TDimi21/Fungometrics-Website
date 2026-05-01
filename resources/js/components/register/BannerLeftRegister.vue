<script setup>
const props = defineProps({
  backgroundImage: {
    type: String,
    required: true
  },

  title: {
    type: String,
    required: true,
  }
})
</script>
<template>
  <slot>
  <section :style="{ backgroundImage: `url('${ props.backgroundImage }')` }"
           class="hidden h-[113%] lg:h-screen w-[35%] bg-cover bg-no-repeat bg-center md:flex flex-col justify-center items-center">
    <img alt="Fungo's logo" src="@/assets/img/login/assteslogin/logo-fungo.png">
    <h2 class="text-white font-fungo-300 text-4xl leading-[60px] my-6">{{ props.title }} <span class="font-fungo-800">REGISTER</span>
    </h2>
    <img alt="Logo with ball" src="@/assets/img/login/assteslogin/logoapp.png">
    <img alt="Image for app store" src="@/assets/img/login/assteslogin/storesbutton.png">
  </section>
  </slot>
</template>

