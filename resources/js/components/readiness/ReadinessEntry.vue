<script setup>
import {readinessFields} from './contract'
defineProps({entry:Object})
</script>
<template><article class="entry"><strong>{{entry.date}} · Average {{entry.average}}/10</strong><p>Sleep: {{entry.answers.sleep_hours}} hours</p><dl><template v-for="[key,label] in readinessFields" :key="key"><dt>{{label}}</dt><dd>{{entry.answers[key]}}/10</dd></template></dl><p>Pain reported: {{entry.answers.pain_flag?'Yes':'No'}}</p><p v-if="entry.answers.notes">Notes: {{entry.answers.notes}}</p><p v-for="flag in entry.alerts" :key="flag" class="alert">{{flag}}</p></article></template>
<style scoped>.entry{padding:12px;border:1px solid #344963;border-radius:10px;margin:8px 0}dl{display:grid;grid-template-columns:1fr auto;gap:6px}.alert{color:#ffb4a8}p{margin-top:8px}</style>
