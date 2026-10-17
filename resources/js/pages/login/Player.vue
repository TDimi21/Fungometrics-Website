<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { LoginTemplate } from '../../components/login'
import AcceptInvitation from '../communications/AcceptInvitation.vue'
import { isPlayerInvitationRoute } from '@/utils/communicationDestination.js'
import bgPlayer from '../../assets/img/login/bg-login-player.jpg'
const route = useRoute()
const invited = computed(() => isPlayerInvitationRoute(route))
</script>
<template>
  <div v-if="invited" class="min-h-screen bg-cover bg-center p-4" :style="{ backgroundImage: `linear-gradient(#00152acc, #00152acc), url(${bgPlayer})` }">
    <AcceptInvitation player-claim />
  </div>
  <LoginTemplate v-else :backgroundImage="bgPlayer" title="player" />
</template>
