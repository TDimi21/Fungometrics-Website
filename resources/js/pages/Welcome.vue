<script setup>
import router from "../../router";
import {BigButtonField} from "../components/form";

const goToLogin = (value) => {
  if (value === 'coach') {
    router.replace('/login/coach')
  }

  if (value === 'player') {
    router.replace('/login/player')
  }

}
</script>


<template>
  <div class="grid min-h-screen min-w-screen bg-img">
    <div class="home-bg"></div>
    <div class="blue-overlay">
    <div class="band-banner grid ">
      <div
        class="grid grid-flow-row  md:grid-flow-col  place-items-center place-content-center md:place-content-around md:mx-5">
        <div class="order-1  md:order-2">
          <div class="grid justify-items-center shadow-lg  xl:scale-125">
            <img alt="main banner" class="max-h-screen max-w-[350px]  lg:max-w-[550px] lg:max-h-[80%] xl:max-h-[100%]"
                 src="../assets/img/login/assteslogin/updatedlogo.png"/>
          </div>
        </div>
        <div class="order-2 md:order-1">
          <div class="grid gap-2  justify-items-center xl:scale-150">
            <BigButtonField direction="right" iconColor="f00" label="Login as <strong>Coach</strong>" shadow="shadow"
                            @click="goToLogin('coach')"/>
            <RouterLink class="text-xl font-black text-white hover:text-gray-300 hover:text-2xl mt-2"
                        to="/register/coach">Create an
              account
            </RouterLink>
          </div>
        </div>
        <div class="order-3 mt-5">
          <div class="grid gap-4 justify-items-center xl:scale-150 ">
            <BigButtonField direction="left" iconColor="f00" label="Login as <strong>Player</strong>" shadow="shadow"
                            @click="goToLogin('player')"/>
            <RouterLink class="text-xl font-black text-white hover:text-gray-300 hover:text-2xl"
                        to="/register/player">Create an account
            </RouterLink>
          </div>

        </div>
      </div>
    </div>
    </div>
  </div>
</template>

<style scoped>
.bg-img {
  position: relative;
  height: 100%;
  overflow: hidden;
}

.home-bg {
  position: absolute;
  inset: 0;
  background-image: url("../assets/img/login/assteslogin/updatedlogo.png");
  background-repeat: no-repeat;
  background-size: cover;
  background-position: center;
  opacity: 0.4;
}

.blue-overlay {
  background: rgba(10, 30, 80, 0.4);
  min-height: 100vh;
  display: grid;
  position: relative;
  z-index: 1;
}

.band-banner {
  background-image: url("../assets/img/login/assteslogin/bandbanner.png");
  background-repeat: no-repeat;
  background-position: center;
  background-size: contain;
}

</style>
