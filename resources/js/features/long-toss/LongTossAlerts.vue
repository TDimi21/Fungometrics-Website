<script setup>
import {ref,watch} from 'vue'
import {useAxiosAuth} from '@/composables/axios-auth'
const props=defineProps({teamId:String})
const {axiosGet}=useAxiosAuth(), alerts=ref([]), error=ref('')
let request=0
async function load(){const current=++request;alerts.value=[];error.value='';try{const res=await axiosGet(`long-toss-guidance-team/${props.teamId}/alerts`);if(current===request)alerts.value=res.data.data}catch{if(current===request)error.value='Long Toss reviews could not be loaded.'}}
watch(()=>props.teamId,()=>{if(props.teamId)load()},{immediate:true})
</script>
<template><section v-if="alerts.length||error" class="rounded-xl border border-amber-400/50 p-4 my-4"><h2>Long Toss coach reviews</h2><p v-if="error" role="alert">{{error}} <button @click="load">Retry</button></p><article v-for="alert in alerts" :key="alert.player_id" class="my-3"><strong>{{alert.name}}</strong><p>{{alert.message}}</p><small>{{alert.flagged_sessions}} flagged in latest 5 · {{alert.date?.slice(0,10)}} · Results and PRs remain recorded.</small></article></section></template>
