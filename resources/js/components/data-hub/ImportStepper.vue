<script setup>
defineProps({
  currentStep: { type: Number, required: true },
})

const steps = ['Platform', 'File', 'Destination', 'Player Mapping', 'Column Mapping', 'Review']
</script>

<template>
  <ol class="stepper" aria-label="Import progress">
    <li
      v-for="(label, index) in steps"
      :key="label"
      :class="{ active: currentStep === index + 1, complete: currentStep > index + 1 }"
    >
      <span>{{ currentStep > index + 1 ? '✓' : index + 1 }}</span>
      <strong>{{ label }}</strong>
    </li>
  </ol>
</template>

<style scoped>
.stepper { display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin:0; padding:0; list-style:none; }
.stepper li { display:flex; align-items:center; gap:10px; min-width:0; padding:12px 14px; border:1px solid rgba(255,255,255,.1); border-radius:12px; background:rgba(255,255,255,.04); color:rgba(255,255,255,.46); }
.stepper span { display:grid; place-items:center; width:26px; height:26px; flex:0 0 auto; border-radius:50%; background:rgba(255,255,255,.08); font-size:11px; font-weight:900; }
.stepper strong { overflow:hidden; font-size:11px; letter-spacing:.08em; text-overflow:ellipsis; text-transform:uppercase; }
.stepper li.active { border-color:rgba(255,43,74,.65); background:rgba(255,43,74,.12); color:#fff; }
.stepper li.active span { background:#ff2b4a; }
.stepper li.complete { color:#64e6b4; }
.stepper li.complete span { background:rgba(59,211,154,.16); }
@media (max-width:700px) { .stepper strong { display:none; } .stepper li { justify-content:center; padding:10px; } }
</style>
