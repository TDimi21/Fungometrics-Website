<script setup>
import cagePhoto from '@/assets/img/training/fungo-cage.png'
import { cageWireframe } from '@/utils/cagePerspective'
const props = defineProps({ modelValue: Object, disabled: Boolean })
const emit = defineEmits(['update:modelValue'])
const wire = cageWireframe()
const points = line => line.map(p => `${p.x * 1000},${p.y * 1000}`).join(' ')
function choose(event) {
  if (props.disabled) return
  const rect = event.currentTarget.getBoundingClientRect()
  emit('update:modelValue', { u: Math.max(0,Math.min(1,(event.clientX-rect.left)/rect.width)), v: Math.max(0,Math.min(1,(event.clientY-rect.top)/rect.height)) })
}
function move(event) {
  const delta = { ArrowLeft: [-.01,0], ArrowRight:[.01,0], ArrowUp:[0,-.01], ArrowDown:[0,.01] }[event.key]
  if (!delta || props.disabled) return
  event.preventDefault()
  emit('update:modelValue', { u: Math.max(0,Math.min(1,(props.modelValue?.u ?? .5)+delta[0])), v: Math.max(0,Math.min(1,(props.modelValue?.v ?? .5)+delta[1])) })
}
</script>
<template><div class="cage-target" role="button" tabindex="0" :aria-disabled="disabled" aria-label="Cage contact location. Tap or use arrow keys to mark where the ball hit the net or floor." @click="choose" @keydown="move"><img :src="cagePhoto" alt="Perspective view into the batting cage" draggable="false" /><svg viewBox="0 0 1000 1000" preserveAspectRatio="none" aria-hidden="true"><polyline :points="points(wire.back)" /><polyline v-for="(line,index) in wire.edges" :key="index" :points="points(line)" /></svg><span v-if="modelValue" class="marker" :style="{ left: `${modelValue.u*100}%`, top: `${modelValue.v*100}%` }" /></div></template>
<style scoped>.cage-target{position:relative;width:100%;cursor:crosshair;touch-action:manipulation}.cage-target:focus-visible{outline:3px solid #83e9ff;outline-offset:4px}img{display:block;width:100%;height:auto;user-select:none}svg{position:absolute;inset:0;width:100%;height:100%;pointer-events:none}polyline{fill:none;stroke:#ffffff70;stroke-width:2}.marker{position:absolute;width:18px;height:18px;border:3px solid white;border-radius:50%;background:#ff2451;transform:translate(-50%,-50%);pointer-events:none}</style>
