<script setup>
import { ref, computed, watch } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { usePlayerStore } from '@/store/players.js'
import defaultAvatar from '@/assets/img/login/assteslogin/updatedlogo.png'

const { axiosGet } = useAxiosAuth()
const { players } = usePlayerStore()

const selectedA = ref('')
const selectedB = ref('')
const statsA = ref(null)
const statsB = ref(null)
const loadingA = ref(false)
const loadingB = ref(false)

// Each dropdown excludes the other's pick
const optionsA = computed(() => players.filter(p => p.id !== selectedB.value))
const optionsB = computed(() => players.filter(p => p.id !== selectedA.value))

const categories = ['GB', 'LD', 'FLY', 'SM/F', 'TAKE']
const veloCategories = ['GB', 'LD', 'FLY']

const qualityKeys = ['H', 'A', 'W']
const qualityLabels = { H: 'Hard', A: 'Avg', W: 'Weak' }
const qualityColors = { H: '#4ADE80', A: '#FFB457', W: '#F87171' }

const catColors = {
  'GB':   '#F8A488',
  'LD':   '#ADE8F4',
  'FLY':  '#8676FF',
  'SM/F': '#FFB457',
  'TAKE': '#03F1E3',
}

async function loadPlayer(id, side) {
  if (!id) { side === 'A' ? statsA.value = null : statsB.value = null; return }
  side === 'A' ? loadingA.value = true : loadingB.value = true
  try {
    const { data } = await axiosGet(`player-compare/${id}`)
    side === 'A' ? statsA.value = data.data : statsB.value = data.data
  } catch(e) {
    console.error(e)
  } finally {
    side === 'A' ? loadingA.value = false : loadingB.value = false
  }
}

watch(selectedA, val => loadPlayer(val, 'A'))
watch(selectedB, val => loadPlayer(val, 'B'))

// Which side wins each category (higher % = better for FLY/LD/GB, lower = better for SM/F + TAKE)
const lowerIsBetter = ['SM/F', 'TAKE']

function winner(cat) {
  if (!statsA.value || !statsB.value) return null
  const a = statsA.value.stats[cat]?.percent ?? 0
  const b = statsB.value.stats[cat]?.percent ?? 0
  if (a === b) return 'tie'
  const aWins = lowerIsBetter.includes(cat) ? a < b : a > b
  return aWins ? 'A' : 'B'
}

function avatarSrc(stats) {
  return stats?.avatar || defaultAvatar
}
</script>

<template>
  <div class="w-full">
    <div class="grid grid-cols-[1fr_40px_1fr] gap-0 items-start">

      <!-- PLAYER A -->
      <div class="compare-col">
        <!-- Dropdown -->
        <select v-model="selectedA" class="compare-select">
          <option value="">Select Player A</option>
          <option v-for="p in optionsA" :key="p.id" :value="p.id">{{ p.name?.full ?? p.name }}</option>
        </select>

        <!-- Profile card -->
        <div v-if="loadingA" class="compare-loading">Loading…</div>
        <div v-else-if="statsA" class="compare-profile">
          <img :src="avatarSrc(statsA)" class="compare-avatar" alt="" />
          <div class="compare-pname">{{ statsA.name }}</div>
          <div class="compare-total">{{ statsA.total }} swings</div>
        </div>
        <div v-else class="compare-empty">No player selected</div>

        <!-- Stats bars -->
        <div v-if="statsA" class="compare-bars">
          <div v-for="cat in categories" :key="cat" class="compare-bar-row side-a">
            <div class="bar-label-a">
              <span class="bar-cat">{{ cat }}</span>
              <span class="bar-pct">{{ statsA.stats[cat]?.percent ?? 0 }}%</span>
              <span class="bar-cnt">({{ statsA.stats[cat]?.count ?? 0 }})</span>
            </div>
            <div class="bar-track-a">
              <div
                class="bar-fill-a"
                :style="{ width: (statsA.stats[cat]?.percent ?? 0) + '%', background: catColors[cat] }"
              ></div>
            </div>
          </div>
          <!-- Avg exit velo row -->
          <div class="velo-row velo-row-a">
            <template v-for="cat in veloCategories" :key="cat">
              <div class="velo-cell">
                <span class="velo-label">{{ cat }}</span>
                <span class="velo-val" :style="{ color: catColors[cat] }">
                  {{ statsA.stats[cat]?.avg_velo != null ? statsA.stats[cat].avg_velo + ' mph' : '—' }}
                </span>
              </div>
            </template>
          </div>
          <!-- Quality of contact bars + avg velo -->
          <div class="quality-section quality-section-a">
            <div class="quality-section-title">Quality of Contact</div>
            <div v-for="q in qualityKeys" :key="q" class="compare-bar-row side-a">
              <div class="bar-label-a">
                <span class="bar-cat" :style="{ color: qualityColors[q] }">{{ qualityLabels[q] }}</span>
                <span class="bar-pct">{{ statsA.quality_velo?.[q]?.percent ?? 0 }}%</span>
                <span class="bar-cnt">({{ statsA.quality_velo?.[q]?.count ?? 0 }})</span>
              </div>
              <div class="bar-track-a">
                <div class="bar-fill-a" :style="{ width: (statsA.quality_velo?.[q]?.percent ?? 0) + '%', background: qualityColors[q] }"></div>
              </div>
            </div>
            <div class="velo-row velo-row-a" style="margin-top:6px">
              <div class="velo-cell" v-for="q in qualityKeys" :key="q">
                <span class="velo-label" :style="{ color: qualityColors[q] }">{{ qualityLabels[q] }}</span>
                <span class="velo-val" :style="{ color: qualityColors[q] }">
                  {{ statsA.quality_velo?.[q]?.avg_velo != null ? statsA.quality_velo[q].avg_velo + ' mph' : '—' }}
                </span>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="compare-bars-empty"></div>
      </div>

      <!-- MIDDLE: winner indicators -->
      <div class="compare-middle">
        <div class="middle-spacer"></div><!-- aligns with dropdown + profile -->
        <div
          v-for="cat in categories"
          :key="cat"
          class="middle-row"
        >
          <span v-if="winner(cat) === 'A'" class="win-badge win-left">◀</span>
          <span v-else-if="winner(cat) === 'B'" class="win-badge win-right">▶</span>
          <span v-else-if="winner(cat) === 'tie'" class="win-badge win-tie">—</span>
          <span v-else class="win-badge win-none">·</span>
        </div>
      </div>

      <!-- PLAYER B -->
      <div class="compare-col">
        <!-- Dropdown -->
        <select v-model="selectedB" class="compare-select">
          <option value="">Select Player B</option>
          <option v-for="p in optionsB" :key="p.id" :value="p.id">{{ p.name?.full ?? p.name }}</option>
        </select>

        <!-- Profile card -->
        <div v-if="loadingB" class="compare-loading">Loading…</div>
        <div v-else-if="statsB" class="compare-profile">
          <img :src="avatarSrc(statsB)" class="compare-avatar" alt="" />
          <div class="compare-pname">{{ statsB.name }}</div>
          <div class="compare-total">{{ statsB.total }} swings</div>
        </div>
        <div v-else class="compare-empty">No player selected</div>

        <!-- Stats bars (reversed — grows from right) -->
        <div v-if="statsB" class="compare-bars">
          <div v-for="cat in categories" :key="cat" class="compare-bar-row side-b">
            <div class="bar-track-b">
              <div
                class="bar-fill-b"
                :style="{ width: (statsB.stats[cat]?.percent ?? 0) + '%', background: catColors[cat] }"
              ></div>
            </div>
            <div class="bar-label-b">
              <span class="bar-cnt">({{ statsB.stats[cat]?.count ?? 0 }})</span>
              <span class="bar-pct">{{ statsB.stats[cat]?.percent ?? 0 }}%</span>
              <span class="bar-cat">{{ cat }}</span>
            </div>
          </div>
          <!-- Avg exit velo row -->
          <div class="velo-row velo-row-b">
            <template v-for="cat in veloCategories" :key="cat">
              <div class="velo-cell">
                <span class="velo-val" :style="{ color: catColors[cat] }">
                  {{ statsB.stats[cat]?.avg_velo != null ? statsB.stats[cat].avg_velo + ' mph' : '—' }}
                </span>
                <span class="velo-label">{{ cat }}</span>
              </div>
            </template>
          </div>
          <!-- Quality of contact bars + avg velo -->
          <div class="quality-section quality-section-b">
            <div class="quality-section-title">Quality of Contact</div>
            <div v-for="q in qualityKeys" :key="q" class="compare-bar-row side-b">
              <div class="bar-track-b">
                <div class="bar-fill-b" :style="{ width: (statsB.quality_velo?.[q]?.percent ?? 0) + '%', background: qualityColors[q] }"></div>
              </div>
              <div class="bar-label-b">
                <span class="bar-cnt">({{ statsB.quality_velo?.[q]?.count ?? 0 }})</span>
                <span class="bar-pct">{{ statsB.quality_velo?.[q]?.percent ?? 0 }}%</span>
                <span class="bar-cat" :style="{ color: qualityColors[q] }">{{ qualityLabels[q] }}</span>
              </div>
            </div>
            <div class="velo-row velo-row-b" style="margin-top:6px">
              <div class="velo-cell" v-for="q in qualityKeys" :key="q">
                <span class="velo-val" :style="{ color: qualityColors[q] }">
                  {{ statsB.quality_velo?.[q]?.avg_velo != null ? statsB.quality_velo[q].avg_velo + ' mph' : '—' }}
                </span>
                <span class="velo-label" :style="{ color: qualityColors[q] }">{{ qualityLabels[q] }}</span>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="compare-bars-empty"></div>
      </div>

    </div>

    <!-- Legend -->
    <div class="cat-legend">
      <span v-for="cat in categories" :key="cat" class="legend-dot">
        <span class="dot" :style="{ background: catColors[cat] }"></span>{{ cat }}
      </span>
      <span class="legend-dot ml-auto text-white/30 text-[10px]">◀ A wins &nbsp; ▶ B wins</span>
    </div>
  </div>
</template>

<style scoped>
.compare-col {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.compare-select {
  appearance: none;
  -webkit-appearance: none;
  width: 100%;
  background: rgba(0,32,96,0.6);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 8px center;
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 8px;
  color: #fff;
  font-size: 11px;
  padding: 6px 28px 6px 10px;
  outline: none;
  cursor: pointer;
}
.compare-select option { background: #001440; color: #fff; }
.compare-select:focus { border-color: rgba(192,0,0,0.6); }

.compare-loading {
  font-size: 11px;
  color: rgba(255,255,255,0.3);
  padding: 10px 0;
  text-align: center;
}
.compare-empty {
  height: 68px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  color: rgba(255,255,255,0.2);
}
.compare-profile {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  padding: 8px 4px;
  background: rgba(0,32,96,0.4);
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,0.08);
}
.compare-avatar {
  width: 36px; height: 36px;
  border-radius: 50%;
  object-fit: cover;
  background: #001440;
  border: 2px solid rgba(192,0,0,0.5);
  flex-shrink: 0;
}
.compare-pname {
  font-size: 12px; font-weight: 700; color: #fff;
  text-align: center; line-height: 1.2;
}
.compare-total {
  font-size: 10px; color: rgba(255,255,255,0.4);
}

/* Bars – left side (A) */
.compare-bar-row { display: flex; align-items: center; gap: 5px; margin-bottom: 5px; }
.side-a { flex-direction: row; }
.side-b { flex-direction: row-reverse; }

.bar-label-a {
  display: flex; gap: 3px; align-items: center;
  min-width: 80px; justify-content: flex-end;
}
.bar-label-b {
  display: flex; gap: 3px; align-items: center;
  min-width: 80px; justify-content: flex-start;
}
.bar-cat  { font-size: 10px; font-weight: 800; color: rgba(255,255,255,0.7); }
.bar-pct  { font-size: 10px; font-weight: 700; color: #fff; }
.bar-cnt  { font-size: 9px;  color: rgba(255,255,255,0.35); }

.bar-track-a, .bar-track-b {
  flex: 1; height: 8px;
  background: rgba(255,255,255,0.07);
  border-radius: 99px; overflow: hidden;
}
.bar-fill-a {
  height: 100%; border-radius: 99px;
  transition: width 0.5s ease;
  float: left;
}
.bar-fill-b {
  height: 100%; border-radius: 99px;
  transition: width 0.5s ease;
  float: right;
}
.compare-bars-empty { height: 130px; }

/* Middle column */
.compare-middle {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0;
}
.middle-spacer { height: 107px; } /* dropdown (32) + gap (8) + profile (68) + gap (8) = ~116 — tune as needed */
.middle-row {
  height: 21px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 5px;
}
.win-badge {
  font-size: 12px;
  font-weight: 900;
  line-height: 1;
}
.win-left  { color: #C00000; }
.win-right { color: #004080; }
.win-tie   { color: rgba(255,255,255,0.3); font-size: 14px; }
.win-none  { color: rgba(255,255,255,0.12); }

/* Legend */
.cat-legend {
  display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;
  align-items: center;
}
.legend-dot {
  display: flex; align-items: center; gap: 4px;
  font-size: 10px; color: rgba(255,255,255,0.5);
}
.dot {
  width: 8px; height: 8px; border-radius: 50%;
  display: inline-block; flex-shrink: 0;
}

.quality-section {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid rgba(255,255,255,0.1);
}
.quality-section-title {
  font-size: 9px;
  font-weight: 800;
  text-transform: uppercase;
  color: rgba(255,255,255,0.3);
  letter-spacing: 0.08em;
  margin-bottom: 6px;
}
.quality-velo-row {
  margin-top: 6px;
  padding-top: 6px;
  border-top: 1px dashed rgba(255,255,255,0.06);
}
.velo-cnt {
  font-size: 9px; color: rgba(255,255,255,0.3);
}
.velo-row {
  display: flex;
  gap: 6px;
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px solid rgba(255,255,255,0.08);
}
.velo-row-a { justify-content: flex-start; }
.velo-row-b { justify-content: flex-end; }
.velo-cell {
  display: flex; flex-direction: column; align-items: center; gap: 1px;
  flex: 1;
}
.velo-label {
  font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.35); text-transform: uppercase;
}
.velo-val {
  font-size: 11px; font-weight: 900;
}
</style>
