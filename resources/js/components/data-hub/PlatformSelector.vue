<script setup>
defineProps({
  platforms: { type: Array, required: true },
  modelValue: { type: String, default: '' },
})
defineEmits(['update:modelValue'])
</script>

<template>
  <div class="platform-grid">
    <button
      v-for="platform in platforms"
      :key="platform.key"
      type="button"
      class="platform-card"
      :class="{ selected: modelValue === platform.key }"
      @click="$emit('update:modelValue', platform.key)"
    >
      <span class="platform-logo">{{ platform.initials }}</span>
      <span class="platform-copy">
        <strong>{{ platform.name }}</strong>
        <small>{{ platform.description }}</small>
        <em>{{ platform.sessionTypes.join(' · ') }}</em>
      </span>
      <span class="platform-check">{{ modelValue === platform.key ? '✓' : '→' }}</span>
    </button>
  </div>
</template>

<style scoped>
.platform-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
.platform-card { display:grid; grid-template-columns:auto 1fr auto; align-items:center; gap:16px; min-height:122px; padding:18px; border:1px solid rgba(255,255,255,.12); border-radius:16px; background:rgba(7,14,33,.68); color:#fff; text-align:left; transition:.18s ease; }
.platform-card:hover { border-color:rgba(255,255,255,.28); transform:translateY(-1px); }
.platform-card.selected { border-color:#ff2b4a; background:linear-gradient(135deg,rgba(255,43,74,.15),rgba(7,14,33,.84)); box-shadow:0 12px 30px rgba(255,43,74,.1); }
.platform-logo { display:grid; place-items:center; width:54px; height:54px; border:1px solid rgba(255,255,255,.15); border-radius:15px; background:linear-gradient(145deg,#24345f,#0b132d); color:#ff4964; font-size:14px; font-weight:900; }
.platform-copy { display:flex; min-width:0; flex-direction:column; gap:5px; }
.platform-copy strong { font-size:17px; }
.platform-copy small { color:rgba(226,232,240,.68); font-size:12px; line-height:1.45; }
.platform-copy em { color:rgba(148,163,184,.68); font-size:10px; font-style:normal; line-height:1.4; text-transform:uppercase; }
.platform-check { color:#ff4964; font-size:18px; font-weight:900; }
@media (max-width:820px) { .platform-grid { grid-template-columns:1fr; } }
</style>

