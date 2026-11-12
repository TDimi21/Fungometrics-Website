<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
const props = defineProps({ report: { type: Object, default: null } });
const emit = defineEmits(['close']);
const dialog = ref(null);
let previousFocus;
let previousOverflow;
function restore() {
  if (previousOverflow !== undefined) document.body.style.overflow = previousOverflow;
  previousOverflow = undefined;
  if (previousFocus?.isConnected) previousFocus.focus();
  previousFocus = null;
}
watch(() => props.report, async (report) => {
  if (report?.src) {
    previousFocus = document.activeElement;
    previousOverflow = document.body.style.overflow;
    await nextTick();
    if (!props.report?.src || !dialog.value) return;
    dialog.value.showModal();
    document.body.style.overflow = 'hidden';
  } else {
    dialog.value?.close();
    restore();
  }
});
onBeforeUnmount(restore);
</script>

<template>
  <Teleport to="body">
    <dialog ref="dialog" class="fmtrx-image-modal" aria-labelledby="capture-modal-title" @cancel.prevent="emit('close')" @click="event => { if (event.target === dialog) emit('close') }">
      <div v-if="report?.src" class="modal-content">
        <header><div><h2 id="capture-modal-title">{{ report.label }} — inside FMTRX</h2><p>Demonstration screenshot · Test data</p></div><button type="button" autofocus @click="emit('close')" aria-label="Close screenshot">Close <span aria-hidden="true">×</span></button></header>
        <img :src="report.src" :alt="report.alt" :width="report.width" :height="report.height" />
      </div>
    </dialog>
  </Teleport>
</template>

<style scoped>
.fmtrx-image-modal { width: min(1400px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); padding: 0; margin: auto; border: 1px solid #526078; border-radius: 12px; color: #f5f1e8; background: #0b1627; overflow: auto; font-family: Arial, sans-serif; }
.fmtrx-image-modal::backdrop { background: #030811e8; backdrop-filter: blur(6px); }
header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 20px; position: sticky; top: 0; background: #0b1627; }
h2 { margin: 0; font-size: 18px; font-weight: 700; } p { margin: 5px 0 0; color: #b5c1d0; font-size: 12px; }
button { flex-shrink: 0; border: 1px solid #6a788d; border-radius: 5px; padding: 12px 16px; background: #1b2940; color: white; cursor: pointer; }
button:focus-visible { outline: 3px solid #ffc970; outline-offset: 3px; }
img { display: block; width: 100%; height: auto; }
</style>
