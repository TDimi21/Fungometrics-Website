<script setup>
import {ref, onMounted} from 'vue';
import PlayerWorkoutPreview from './PlayerWorkoutPreview.vue';
const props = defineProps({name: String, date: String, sections: Array, minutes: Number, startTime: String, endTime: String});
const emit = defineEmits(['close']);
const dialog = ref(null);
onMounted(() => dialog.value.showModal());
</script>
<template>
  <dialog ref="dialog" class="workout-preview" aria-label="Workout preview" @close="emit('close')" @click="e => { if (e.target === dialog) dialog.close() }">
    <header><div><small>WORKOUT PREVIEW · UNSAVED CHANGES INCLUDED</small><h2>{{ name || 'Untitled workout' }}</h2></div><button type="button" autofocus @click="dialog.close()">Close preview</button></header>
    <PlayerWorkoutPreview :name="name" :date="date" :sections="sections" :minutes="minutes" :start-time="startTime" :end-time="endTime" />
  </dialog>
</template>
<style scoped>
.workout-preview{position:fixed;inset:0;margin:auto;width:min(760px,94vw);max-height:88vh;overflow:auto;background:#101d30;color:#edf3ff;border:1px solid #49617e;border-radius:16px;padding:24px}.workout-preview::backdrop{background:#030712bb}.workout-preview header{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}.workout-preview h2{font-size:25px;font-weight:800}.workout-preview h3{font-size:18px;font-weight:700}.workout-preview small{font-size:10px;color:#b9c9df}.workout-preview p{white-space:pre-wrap;color:#b9c9df;margin:8px 0}.workout-preview section{border-top:1px solid #344b68;margin-top:18px;padding-top:18px}.workout-preview ol{padding-left:22px;list-style:decimal}.workout-preview li{padding:8px 0}.workout-preview button{min-height:44px;padding:10px 16px;background:#21334e;border:1px solid #49617e;border-radius:8px;color:#fff}
</style>
