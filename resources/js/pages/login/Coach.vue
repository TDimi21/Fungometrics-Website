<script setup>
  import { LoginTemplate } from '../../components/login'
  import loginCoachImg from '../../assets/img/login/bg-login-coach.jpg'
  const imgPath = loginCoachImg;
</script>
<template>

  <LoginTemplate :backgroundImage="imgPath" title="coach" />

</template>
