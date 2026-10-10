<script setup>
import {computed} from 'vue'
import {useRoute} from 'vue-router'
import {storeToRefs} from 'pinia'
import {useTeamStore} from '@/store/team'
import {useAccessStore} from '@/store/access'
const emit = defineEmits(['logout'])
const route = useRoute()
const {team} = storeToRefs(useTeamStore())
const access = useAccessStore()
const links = computed(() => [
  {id:'overview', label:'Overview', to:{path:'/dashboard'}},
  {id:'workout', label:'Workout', to:{name:'workout.planner'}},
  {id:'practice', label:'Practice', to:{name:'practice.planner'}},
  {id:'development', label:'Player Development', to:{path:'/dashboard', query:{tab:'development'}}},
  {id:'strengthcenter', label:'Strength Center', to:{path:'/dashboard', query:{tab:'strengthcenter'}}},
  {id:'strength', label:'Assessment', to:{path:'/dashboard', query:{tab:'strength'}}},
  ...(access.canAccess('data_hub_import') || access.summary?.capabilities?.subscription_admin
    ? [{id:'datahub', label:'Data Hub', to:{path:'/dashboard', query:{tab:'datahub'}}}] : []),
])
const active = computed(() => {
  if(route.path === '/dashboard') return route.query.tab || 'overview'
  if(route.path === '/workouts' || route.path === '/workout-library') return 'workout'
  if(route.path === '/practice') return 'practice'
  if(route.path.startsWith('/data-hub')) return 'datahub'
  if(route.path.startsWith('/development')) return 'development'
  if(route.path.startsWith('/free-assessment') || route.path === '/assessment-reports') return 'strength'
  return null
})
</script>
<template>
  <header class="coach-header">
    <RouterLink to="/dashboard" class="wordmark" aria-label="FMTRX home">FMTR<span>X</span><small>TRAIN SMARTER.<br>PLAY FURTHER.</small></RouterLink>
    <nav aria-label="Main navigation">
      <RouterLink v-for="link in links" :key="link.id" :to="link.to" :class="{selected:active===link.id}" :aria-current="active===link.id?'page':null">{{ link.label }}</RouterLink>
    </nav>
    <RouterLink to="/settings" class="account">Coach<small>{{ team?.name || 'Your team' }}</small></RouterLink>
    <button type="button" class="account" @click="emit('logout')">Log out</button>
  </header>
</template>
<style scoped>
.coach-header{position:sticky;top:0;z-index:40;display:flex;align-items:center;gap:22px;background:linear-gradient(110deg,#0e203b,#060d19);border-bottom:1px solid #21334e;padding:12px 25px;min-height:78px;color:white}.wordmark{font-style:italic;font-size:29px;font-weight:950;display:flex;align-items:center;letter-spacing:-1px;white-space:nowrap}.wordmark>span{color:#fa3037}.wordmark small{font-style:normal;font-size:8px;letter-spacing:2px;margin-left:15px;padding-left:15px;border-left:2px solid #e8353b}.coach-header nav{display:flex;align-items:center;flex:1;gap:2px;overflow-x:auto;min-width:0}.coach-header nav a{font-size:10px;text-transform:uppercase;letter-spacing:.6px;padding:18px 12px;white-space:nowrap;border-bottom:3px solid transparent;font-weight:600;color:#9eabc0;min-height:44px}.coach-header nav a:hover{color:white}.coach-header nav a.selected{color:white;border-bottom-color:#f83038}.account{font-size:12px;padding:8px 12px;border:1px solid #31415b;border-radius:8px;min-height:44px;white-space:nowrap}.account small{display:block;font-size:10px;color:#8ea3c1}.coach-header :is(a,button):focus-visible{outline:2px solid #80caff;outline-offset:2px}@media(max-width:1100px){.coach-header{flex-wrap:wrap;gap:8px;padding:10px 15px}.coach-header nav{order:3;flex-basis:100%}.wordmark{margin-right:auto}.coach-header nav a{padding:12px 10px}}@media(max-width:420px){.wordmark small{display:none}.coach-header{padding:8px}.account{padding:8px}}
</style>
