<script setup>
import { ref } from 'vue'

const props = defineProps({
  modelValue: { type: Object, default: null },
  error: { type: String, default: '' },
  warning: { type: String, default: '' },
  maxSizeBytes: { type: Number, required: true },
})
const emit = defineEmits(['update:modelValue'])
const input = ref(null)
const dragging = ref(false)

const choose = (files) => {
  emit('update:modelValue', files?.[0] || null)
  if (input.value) input.value.value = ''
}
</script>

<template>
  <div>
    <button
      type="button"
      class="dropzone"
      :class="{ dragging, invalid: error }"
      @click="input?.click()"
      @dragenter.prevent="dragging = true"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="dragging = false; choose($event.dataTransfer.files)"
    >
      <span class="upload-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 16V4m0 0L7 9m5-5 5 5M5 14v4a2 2 0 002 2h10a2 2 0 002-2v-4"/></svg>
      </span>
      <strong>{{ modelValue ? modelValue.name : 'Drop your data file here' }}</strong>
      <small v-if="modelValue">{{ (modelValue.size / 1024 / 1024).toFixed(2) }} MB · Ready for inspection</small>
      <small v-else>CSV, XLSX, or TSV · Maximum {{ Math.round(maxSizeBytes / 1024 / 1024) }} MB</small>
      <span class="browse">{{ modelValue ? 'Choose another file' : 'Browse files' }}</span>
    </button>
    <input ref="input" type="file" accept=".csv,.xlsx,.tsv,text/csv,text/tab-separated-values,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" hidden @change="choose($event.target.files)" />
    <p v-if="error" class="file-error">{{ error }}</p>
    <p v-else-if="warning" class="file-warning">{{ warning }}</p>
    <p class="privacy-note">The file stays in this browser until you approve inspection. It is uploaded privately; approved live imports retain a protected source copy for audit provenance.</p>
  </div>
</template>

<style scoped>
.dropzone { display:flex; width:100%; min-height:270px; flex-direction:column; align-items:center; justify-content:center; gap:10px; border:1px dashed rgba(255,255,255,.28); border-radius:18px; background:rgba(5,12,29,.54); color:#fff; transition:.18s ease; }
.dropzone:hover,.dropzone.dragging { border-color:#ff2b4a; background:rgba(255,43,74,.07); }
.dropzone.invalid { border-color:#ff4964; }
.upload-icon { display:grid; place-items:center; width:64px; height:64px; margin-bottom:5px; border-radius:18px; background:rgba(255,43,74,.12); color:#ff4964; }
.upload-icon svg { width:31px; height:31px; }
.dropzone strong { font-size:19px; }
.dropzone small { color:rgba(226,232,240,.62); font-size:12px; }
.browse { margin-top:8px; padding:9px 16px; border:1px solid rgba(255,255,255,.16); border-radius:10px; background:rgba(255,255,255,.07); font-size:11px; font-weight:900; letter-spacing:.06em; text-transform:uppercase; }
.file-error { margin-top:10px; color:#ff7c90; font-size:12px; font-weight:700; }
.file-warning { margin-top:10px; color:#ffc45c; font-size:12px; font-weight:700; }
.privacy-note { margin-top:12px; color:rgba(148,163,184,.72); font-size:11px; text-align:center; }
</style>
