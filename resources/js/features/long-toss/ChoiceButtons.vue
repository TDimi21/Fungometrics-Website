<script setup>
defineProps({label:String,modelValue:[String,Number,Boolean],options:Array,disabled:Boolean})
const emit=defineEmits(['update:modelValue'])
</script>
<template>
<div class="choice-group" role="group" :aria-label="label">
  <p class="choice-label">{{label}}</p>
  <div class="choice-row"><button v-for="option in options" :key="String(option.value)" type="button" :disabled="disabled" :aria-pressed="modelValue===option.value" :class="{selected:modelValue===option.value}" @click="emit('update:modelValue',option.value)">{{modelValue===option.value?'✓ ':''}}{{option.label}}</button></div>
</div>
</template>
<style scoped>
.choice-group{margin:10px 0;min-width:0}.choice-label{margin:0 0 8px;font-size:16px;color:#fff}.choice-row{display:flex;flex-wrap:wrap;gap:8px}.choice-row button{min-width:48px;min-height:48px;padding:12px 16px;border:1px solid #8c9cb2;border-radius:10px;background:#263c57;color:#fff;font-size:16px;font-weight:600}.choice-row button.selected{background:#b90e36;border:2px solid #ff8da8}.choice-row button:focus-visible{outline:3px solid #fff;outline-offset:3px}.choice-row button:disabled{opacity:.5}
</style>
