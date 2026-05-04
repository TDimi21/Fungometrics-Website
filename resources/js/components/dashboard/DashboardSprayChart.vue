<script setup>
import { ref, computed } from 'vue'
import { PrintFieldData } from '@/components/shared'

const props = defineProps({
  contactSpray: {
    type: Object,
    default: () => ({ strikes: [], balls: [] })
  },
  ballStrike: {
    type: Object,
    default: () => ({ strikes: { count: 0, percent: 0 }, balls: { count: 0, percent: 0 } })
  }
})

const activeView = ref('strikes')
const flipped = ref(false)
const drillKey = ref(null)   // 'W' | 'A' | 'H' | traj key
const drillType = ref(null)  // 'contact' | 'traj'

const fieldCoordinates = computed(() => props.contactSpray?.[activeView.value] ?? [])

const strikeCount   = computed(() => props.ballStrike?.strikes?.count ?? 0)
const ballCount     = computed(() => props.ballStrike?.balls?.count ?? 0)
const strikePercent = computed(() => props.ballStrike?.strikes?.percent ?? 0)
const ballPercent   = computed(() => props.ballStrike?.balls?.percent ?? 0)

const counts = computed(() => {
  const data = fieldCoordinates.value
  return {
    MF: data.filter(d => d.feature === 'MF').length,
    W:  data.filter(d => d.feature === 'W').length,
    A:  data.filter(d => d.feature === 'A').length,
    H:  data.filter(d => d.feature === 'H').length,
  }
})

const trajectoryLabels = {
  SM: 'Swing Miss',
  GB: 'Ground Ball',
  PF: 'Pop Fly',
  FB: 'Fly Ball',
  LD: 'Line Drive',
  F:  'Foul',
}

const trajectoryCounts = computed(() => {
  const data = fieldCoordinates.value
  return Object.entries(trajectoryLabels).map(([key, label]) => ({
    key,
    label,
    count: data.filter(d => d.trajectory === key).length,
  }))
})

const contactRows = computed(() => [
  { key: 'W',  label: 'Weak Contact', color: '#22c55e', count: counts.value.W },
  { key: 'A',  label: 'Avg Contact',  color: '#eab308', count: counts.value.A },
  { key: 'H',  label: 'Hard Contact', color: '#3b82f6', count: counts.value.H },
])

const maxContact  = computed(() => Math.max(1, ...contactRows.value.map(r => r.count)))
const maxTraj     = computed(() => Math.max(1, ...trajectoryCounts.value.map(r => r.count)))
const totalSwings = computed(() => fieldCoordinates.value.length)

// Drill-down: group rows by player name
const drillPlayers = computed(() => {
  if (!drillKey.value) return []
  const data = fieldCoordinates.value
  const filtered = drillType.value === 'contact'
    ? data.filter(d => d.feature === drillKey.value)
    : data.filter(d => d.trajectory === drillKey.value)

  const map = {}
  for (const row of filtered) {
    const name = row.player || 'Unknown'
    map[name] = (map[name] || 0) + 1
  }
  return Object.entries(map)
    .map(([name, count]) => ({ name, count }))
    .sort((a, b) => b.count - a.count)
})

const drillLabel = computed(() => {
  if (!drillKey.value) return ''
  if (drillType.value === 'contact') {
    return contactRows.value.find(r => r.key === drillKey.value)?.label ?? drillKey.value
  }
  return trajectoryLabels[drillKey.value] ?? drillKey.value
})

const drillColor = computed(() => {
  if (drillType.value === 'contact') {
    return contactRows.value.find(r => r.key === drillKey.value)?.color ?? '#fff'
  }
  return '#004080'
})

const maxDrill = computed(() => Math.max(1, ...drillPlayers.value.map(p => p.count)))

function openDrill(key, type) {
  drillKey.value = key
  drillType.value = type
}

function closeDrill() {
  drillKey.value = null
  drillType.value = null
}
</script>

<template>
  <div class="w-full">
    <!-- Toggle Buttons -->
    <div class="flex gap-2 mb-4">
      <button
        @click="activeView = 'strikes'"
        class="flex-1 py-2 px-4 rounded-xl text-xs font-black uppercase tracking-wider transition-all border"
        :class="activeView === 'strikes'
          ? 'bg-[#C00000] border-[#C00000] text-white shadow-lg'
          : 'bg-transparent border-white/20 text-white/50 hover:border-white/40 hover:text-white'"
      >
        ⚡ Strikes
        <span class="ml-1 opacity-80">{{ strikeCount }} ({{ strikePercent }}%)</span>
      </button>
      <button
        @click="activeView = 'balls'"
        class="flex-1 py-2 px-4 rounded-xl text-xs font-black uppercase tracking-wider transition-all border"
        :class="activeView === 'balls'
          ? 'bg-[#004080] border-[#004080] text-white shadow-lg'
          : 'bg-transparent border-white/20 text-white/50 hover:border-white/40 hover:text-white'"
      >
        ⚾ Balls
        <span class="ml-1 opacity-80">{{ ballCount }} ({{ ballPercent }}%)</span>
      </button>
    </div>

    <!-- Flip Card -->
    <div class="flip-card" :class="{ 'is-flipped': flipped }" @click="flipped = !flipped">
      <div class="flip-inner">

        <!-- FRONT: Field -->
        <div class="flip-face flip-front">
          <div class="flip-hint">Click to see breakdown →</div>
          <div v-if="fieldCoordinates.length === 0" class="absolute inset-0 flex items-center justify-center text-white/25 text-sm z-10 pointer-events-none">
            No contact data yet
          </div>
          <PrintFieldData
            :key="activeView"
            :fieldCoordinates="fieldCoordinates"
            typeOfCondition="qtyContact"
          />
        </div>

        <!-- BACK: Breakdown Chart -->
        <div class="flip-face flip-back" @click.stop>
          <div class="back-header">
            <template v-if="drillKey">
              <button class="back-close" style="margin-right:4px" @click="closeDrill">←</button>
              <span class="back-title" :style="{ color: drillColor }">{{ drillLabel }}</span>
              <span class="back-total">{{ drillPlayers.reduce((s,p) => s+p.count, 0) }} swings</span>
            </template>
            <template v-else>
              <span class="back-title">{{ activeView === 'strikes' ? '⚡ Strike' : '⚾ Ball' }} Breakdown</span>
              <span class="back-total">{{ totalSwings }} total swings</span>
            </template>
            <button class="back-close" @click="flipped = false; closeDrill()">✕</button>
          </div>

          <!-- Drill-down: players for selected type -->
          <div v-if="drillKey" class="back-sections">
            <div class="back-section">
              <div class="section-label">Players</div>
              <div class="bar-list">
                <div v-for="p in drillPlayers" :key="p.name" class="bar-row">
                  <div class="bar-name">{{ p.name }}</div>
                  <div class="bar-track">
                    <div class="bar-fill" :style="{ width: (p.count / maxDrill * 100) + '%', background: drillColor }"></div>
                  </div>
                  <div class="bar-count">{{ p.count }}</div>
                </div>
                <div v-if="drillPlayers.length === 0" class="bar-empty">No data</div>
              </div>
            </div>
          </div>

          <!-- Main breakdown -->
          <div v-else class="back-sections">
            <!-- Quality of Contact -->
            <div class="back-section">
              <div class="section-label">Quality of Contact <span class="section-hint">click to see players</span></div>
              <div class="bar-list">
                <div v-for="row in contactRows" :key="row.key" class="bar-row bar-row-clickable" @click="openDrill(row.key, 'contact')">
                  <div class="bar-name">{{ row.label }}</div>
                  <div class="bar-track">
                    <div class="bar-fill" :style="{ width: (row.count / maxContact * 100) + '%', background: row.color }"></div>
                  </div>
                  <div class="bar-count">{{ row.count }}</div>
                  <div class="bar-arrow">›</div>
                </div>
              </div>
            </div>

            <!-- Trajectory -->
            <div class="back-section">
              <div class="section-label">Trajectory <span class="section-hint">click to see players</span></div>
              <div class="bar-list">
                <div v-for="row in trajectoryCounts" :key="row.key" class="bar-row bar-row-clickable" @click="openDrill(row.key, 'traj')">
                  <div class="bar-name">{{ row.label }}</div>
                  <div class="bar-track">
                    <div class="bar-fill traj-fill" :style="{ width: (row.count / maxTraj * 100) + '%' }"></div>
                  </div>
                  <div class="bar-count">{{ row.count }}</div>
                  <div class="bar-arrow">›</div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Legend (front only) -->
    <div v-if="!flipped" class="mt-3 flex flex-wrap gap-3 justify-center">
      <div class="legend-item">
        <img src="../../assets/img/login/assteslogin/ballbutton.svg" class="w-4 h-4" />
        <span>Miss/Foul <span class="font-black text-white">{{ counts.MF }}</span></span>
      </div>
      <div class="legend-item">
        <img src="../../assets/img/training/balltraining-green.svg" class="w-4 h-4" />
        <span>Weak <span class="font-black text-white">{{ counts.W }}</span></span>
      </div>
      <div class="legend-item">
        <img src="../../assets/img/training/balltraining.svg" class="w-4 h-4" />
        <span>Average <span class="font-black text-white">{{ counts.A }}</span></span>
      </div>
      <div class="legend-item">
        <img src="../../assets/img/training/balltraining-blue.svg" class="w-4 h-4" />
        <span>Hard <span class="font-black text-white">{{ counts.H }}</span></span>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ── Flip Card ── */
.flip-card {
  perspective: 1200px;
  cursor: pointer;
  border-radius: 12px;
}
.flip-inner {
  position: relative;
  width: 100%;
  transition: transform 0.6s cubic-bezier(.4,0,.2,1);
  transform-style: preserve-3d;
}
.flip-card.is-flipped .flip-inner {
  transform: rotateY(180deg);
}
.flip-face {
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
  border-radius: 12px;
  overflow: hidden;
}
.flip-front {
  position: relative;
}
.flip-back {
  position: absolute;
  inset: 0;
  transform: rotateY(180deg);
  background: #001440;
  border: 1px solid rgba(255,255,255,0.08);
  display: flex;
  flex-direction: column;
  cursor: default;
  min-height: 260px;
  height: 100%;
}

/* Hint overlay */
.flip-hint {
  position: absolute;
  bottom: 8px;
  right: 10px;
  font-size: 10px;
  color: rgba(255,255,255,0.35);
  z-index: 10;
  pointer-events: none;
  text-shadow: 0 1px 3px rgba(0,0,0,0.8);
}

/* Back panel */
.back-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px 8px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}
.back-title {
  font-size: 13px;
  font-weight: 800;
  color: #fff;
  flex: 1;
}
.back-total {
  font-size: 11px;
  color: rgba(255,255,255,0.4);
}
.back-close {
  font-size: 12px;
  color: rgba(255,255,255,0.4);
  background: none;
  border: none;
  cursor: pointer;
  padding: 0 2px;
  line-height: 1;
}
.back-close:hover { color: #fff; }

.back-sections {
  flex: 1;
  overflow-y: auto;
  padding: 10px 14px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.back-section {
  /* section wrapper */
}
.section-hint {
  font-size: 9px;
  font-weight: 400;
  text-transform: none;
  color: rgba(255,255,255,0.25);
  margin-left: 6px;
  letter-spacing: 0;
}
.bar-row-clickable {
  cursor: pointer;
  border-radius: 6px;
  padding: 2px 4px;
  margin: 0 -4px;
  transition: background 0.15s;
}
.bar-row-clickable:hover {
  background: rgba(255,255,255,0.06);
}
.bar-arrow {
  font-size: 14px;
  color: rgba(255,255,255,0.25);
  flex-shrink: 0;
  width: 12px;
  text-align: right;
}
.bar-row-clickable:hover .bar-arrow {
  color: rgba(255,255,255,0.6);
}
.bar-empty {
  font-size: 11px;
  color: rgba(255,255,255,0.25);
  padding: 4px 0;
}
.section-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: rgba(255,255,255,0.4);
  margin-bottom: 8px;
}
.bar-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.bar-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.bar-name {
  width: 90px;
  font-size: 11px;
  color: rgba(255,255,255,0.7);
  flex-shrink: 0;
}
.bar-track {
  flex: 1;
  height: 8px;
  background: rgba(255,255,255,0.07);
  border-radius: 99px;
  overflow: hidden;
}
.bar-fill {
  height: 100%;
  border-radius: 99px;
  transition: width 0.5s ease;
}
.traj-fill {
  background: #004080;
}
.bar-count {
  width: 24px;
  text-align: right;
  font-size: 11px;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
}

/* Legend */
.legend-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  color: rgba(255,255,255,0.55);
}
</style>
