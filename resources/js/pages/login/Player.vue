<script setup>
import { LoginTemplate } from '../../components/login'
import bgPlayer from "../../assets/img/login/bg-login-player.jpg"
  const imgPath = bgPlayer
</script>

<template>
  <LoginTemplate :backgroundImage="imgPath" title="player" />
</template>
