<script setup>
import {skillFields, skillRates, matchesSkillSession} from '@/features/planner/lib/skillResults';
defineProps({bucket:String, actual:{type:Object,default:()=>({})}, sessions:{type:Array,default:()=>[]}, readonly:Boolean});
const emit=defineEmits(['change','load-sessions']);
</script>
<template>
  <section v-if="skillFields(bucket).length" class="skill-results">
    <strong>Recorded performance</strong>
    <p>{{ actual.session_id ? 'Results linked to an existing session.' : Object.values(actual.performance || {}).some(v => v != null && v !== '') ? 'Manually recorded results' : 'Not recorded — completing a drill does not record measurements.' }}</p>
    <template v-if="!actual.session_id">
      <label v-for="[key,label] in skillFields(bucket)" :key="key">{{ label }}
        <span v-if="readonly">: {{ actual.performance?.[key] ?? 'Not recorded' }}</span>
        <input v-else type="number" min="0" :step="key.includes('velocity') ? '0.1' : '1'" :value="actual.performance?.[key] ?? ''" @input="emit('change', {performance:{...actual.performance,[key]:$event.target.value === '' ? null : Number($event.target.value)}})" />
      </label>
      <label>Velocity device / source<span v-if="readonly">: {{ actual.performance?.measurement_source || 'Not recorded' }}</span><input v-else :value="actual.performance?.measurement_source || ''" maxlength="200" placeholder="Radar or launch monitor used" @input="emit('change',{performance:{...actual.performance,measurement_source:$event.target.value}})" /></label>
      <p v-for="rate in skillRates(actual.performance)" :key="rate">{{ rate }}</p>
    </template>
    <template v-if="!readonly && !actual.session_id">
      <button type="button" @click="emit('load-sessions')">Find an existing session instead</button>
      <select aria-label="Link recorded skill session" value="" @change="$event.target.value && emit('change',{session_id:$event.target.value,performance:{}})"><option value="">Choose recorded session</option><option v-for="s in sessions.filter(s => matchesSkillSession(s,bucket))" :key="s.id" :value="s.id">{{ s.started }} · {{ s.modes || s.type }} · {{ s.is_completed ? 'Completed' : 'In progress' }}</option></select>
      <small>Linking uses that session instead of these manual measurements.</small>
    </template>
  </section>
</template>
<style scoped>
.skill-results{border:1px solid #344b68;border-radius:10px;padding:14px;margin:12px 0}.skill-results label{display:block;margin:10px 0}.skill-results input,.skill-results select{display:block;max-width:100%;padding:10px;background:#0b1726;color:#edf3ff;border:1px solid #49617e;border-radius:6px}.skill-results p,.skill-results small{color:#b9c9df;margin:8px 0}.skill-results button{min-height:44px}
</style>
