<script setup>
import { computed } from 'vue'
import { configFromLevel, createFieldMapper, fenceAt, baseCoords } from '@/utils/ballFieldGeometry'
import { fieldMarkToBall } from '@/utils/fieldMark'
import { battingFieldMark } from '@/utils/battingScoring'
const props = defineProps({ modelValue: Number, disabled: Boolean })
const emit = defineEmits(['update:modelValue'])
const cfg = configFromLevel('hs'), mapper = createFieldMapper(600, 400, cfg)
const boundary = Array.from({length: 46}, (_,i) => mapper.mapBall(fenceAt(-45+i*2,cfg.fences), -45+i*2))
const wedge = [mapper.home, ...boundary].map(p => `${p.px},${p.py}`).join(' ')
const diamond = Object.values(baseCoords()).map(p => mapper.mapFeet(...p)).map(p => `${p.px},${p.py}`).join(' ')
const marker = computed(() => { const b = fieldMarkToBall({ field_mark: props.modelValue }, cfg); return mapper.mapBall(b.distance_travel,b.spray_angle) })
function select(event) {
  if (props.disabled) return
  const rect = event.currentTarget.getBoundingClientRect()
  emit('update:modelValue',battingFieldMark((event.clientX-rect.left)/rect.width*600,(event.clientY-rect.top)/rect.height*400))
}
function move(event) {
  if (props.disabled) return
  const steps = { ArrowLeft: -80, ArrowRight: 80, ArrowUp: -1, ArrowDown: 1 }
  if (!(event.key in steps)) return
  event.preventDefault(); emit('update:modelValue',Math.max(1,Math.min(6400,(props.modelValue || 3160)+steps[event.key])))
}
</script>
<template>
  <svg viewBox="0 0 600 400" preserveAspectRatio="none" role="button" tabindex="0" aria-label="Hit location: tap field or use arrow keys" :aria-disabled="disabled" @click="select" @keydown="move">
    <rect width="600" height="400" rx="18" fill="#101d23" />
    <polygon :points="wedge" fill="#267747" stroke="#efffe8" stroke-width="2" />
    <path v-for="n in 4" :key="n" :d="`M ${mapper.home.px} ${mapper.home.py} L ${boundary[n*9].px} ${boundary[n*9].py}`" stroke="#ffffff24" />
    <polygon :points="diamond" fill="#b4784e" stroke="white" stroke-width="2" />
    <circle :cx="mapper.home.px" :cy="mapper.home.py - 40" r="7" fill="#d8b58d" />
    <text x="300" y="28" text-anchor="middle" fill="white" font-size="15">CENTER · 390 ft</text>
    <text x="85" y="180" fill="white" font-size="14">LEFT</text><text x="465" y="180" fill="white" font-size="14">RIGHT</text>
    <circle v-if="modelValue" :cx="marker.px" :cy="marker.py" r="7" fill="#ff2451" stroke="white" stroke-width="3" />
  </svg>
</template>
<style scoped>svg{display:block;width:100%;height:100%;min-height:260px;cursor:crosshair;touch-action:manipulation}svg:focus-visible{outline:3px solid #8de6ff}svg[aria-disabled=true]{opacity:.55;cursor:not-allowed}</style>
