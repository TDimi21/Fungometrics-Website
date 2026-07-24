<script setup>
/**
 * TabBallByBall.vue — batting "Ball by Ball" (Swing Analysis) tab.
 * Matches the app (Statics/tableBatting/Ballbyball.js): the swing-by-swing table
 * sits over the stadium background with the app's navy header and FMTRX brand
 * color-coded Contact + Trajectory cells. Sorting + row edit are preserved.
 */
import { ref } from 'vue'
import { TableEdit } from '@/components/icons'
import { useTrainingStore } from '../../store/training'
import router from '../../../router'
import DefaultImg from '@/assets/img/login/assteslogin/updatedlogo.webp'
import stadiumBg from '@/assets/img/fungometrics-stadium.webp'

const training = useTrainingStore()

const props = defineProps({
  tableData: { type: Object, required: false, default: () => ({}) },
  isLoading: { type: Boolean, required: true },
})

const tableHeadings = ref([
  { title: 'PITCH #', is_sort: true, filter: 'sort' },
  { title: 'PLAYER', is_sort: true, filter: 'profile.first_name' },
  { title: 'CONTACT', is_sort: true, filter: 'quality_of_contact' },
  { title: 'TRAJ.', is_sort: true, filter: 'type_of_hit' },
  { title: 'B/S', is_sort: true, filter: 'zone' },
  { title: 'DIR', is_sort: true, filter: 'field_direction' },
  { title: 'VELO', is_sort: true, filter: 'velocity' },
  { title: 'EDIT', is_sort: false, filter: '' },
])

// FMTRX brand cell colors (shared with the app's stat tables).
const contactStyle = (q) => {
  const u = String(q ?? '').toUpperCase()
  if (u === 'H' || u === 'HARD') return { background: '#d8232a', color: '#fff' }
  if (u === 'A' || u === 'AVG' || u === 'AVERAGE') return { background: '#e6d08a', color: '#000' }
  if (u === 'W' || u === 'WEAK') return { background: '#2160c4', color: '#fff' }
  if (u === 'M' || u === 'MF' || u === 'F' || u === 'MISS') return { background: '#5c6b8a', color: '#fff' }
  return {}
}
const trajStyle = (t) => {
  const u = String(t ?? '').toUpperCase()
  if (u === 'FB') return { background: '#2160c4', color: '#fff' }
  if (u === 'PF') return { background: '#e6d08a', color: '#000' }
  if (u === 'LD') return { background: '#d8232a', color: '#fff' }
  if (u === 'GB') return { background: '#16224c', color: '#fff' }
  return {}
}

const editData = (player) => {
  const editData = {
    id: player.practice_id,
    players: [{
      id: player.batter_id,
      name: {
        first: player.profile.first_name,
        last: player.profile.last_name,
        full: player.profile.first_name + ' ' + player.profile.last_name,
      },
      picture: player.profile.picture,
    }],
    ...player,
  }
  training.setDataTraining(editData)
  router.push({ path: '/track/batting' })
}

const getPlayerPicture = (item) => item?.profile?.picture || item?.player?.picture || DefaultImg
const getPlayerFirstName = (item) => item?.profile?.first_name || item?.player?.first_name || item?.batter_name || 'Player'
const getPlayerLastName = (item) => item?.profile?.last_name || item?.player?.last_name || ''
const getPlayerDisplayName = (item) => `${getPlayerFirstName(item)} ${getPlayerLastName(item)}`.trim() || 'Player'
</script>

<template>
  <section class="sbb" :style="{ backgroundImage: `url(${stadiumBg})` }">
    <div class="sbb-overlay" />
    <div class="sbb-inner">
      <div class="sbb-scroll">
        <table class="sbb-table">
          <thead>
            <tr>
              <th
                v-for="(heading, index) in tableHeadings"
                :key="index"
                :class="{ 'sbb-th--player': heading.title === 'PLAYER' }"
                @click="heading.is_sort && $emit('sortData', heading.filter)"
              >
                <span role="button" class="sbb-th-label" :class="{ 'cursor-pointer': heading.is_sort }">
                  {{ heading.title }}
                  <img v-if="heading.is_sort" src="@/assets/img/icons/sort-solid.svg" alt="sort" class="sbb-sort" />
                </span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="isLoading">
              <td colspan="8" class="sbb-msg">Loading data…</td>
            </tr>
            <tr v-else-if="!(props.tableData && props.tableData.length)">
              <td colspan="8" class="sbb-msg">There is no data</td>
            </tr>
            <tr v-else v-for="(item, index) in props.tableData" :key="index">
              <td class="sbb-num">{{ (item?.sort ?? index) + 1 }}</td>
              <td class="sbb-player">
                <img :src="getPlayerPicture(item)" :alt="getPlayerDisplayName(item)" class="sbb-avatar" />
                <span>{{ getPlayerDisplayName(item) }}</span>
              </td>
              <td :style="contactStyle(item.quality_of_contact)" class="sbb-tag">
                {{ item.quality_of_contact === 'N' ? '—' : item.quality_of_contact }}
              </td>
              <td :style="trajStyle(item.type_of_hit)" class="sbb-tag">
                {{ (['H','A','W'].includes(item.quality_of_contact) && item.type_of_hit === 'TK') ? '—' : item.type_of_hit }}
              </td>
              <td>{{ item.zone }}</td>
              <td>{{ item.field_direction }}</td>
              <td class="sbb-velo">{{ item.velocity == 0 ? '0.0' : item.velocity }}</td>
              <td>
                <button class="sbb-edit" @click="editData(item)">
                  <TableEdit />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>

<style scoped>
.sbb { position: relative; border-radius: 16px; overflow: hidden; background-size: cover; background-position: center; }
.sbb-overlay { position: absolute; inset: 0; background: rgba(26, 31, 53, 0.88); }
.sbb-inner { position: relative; padding: 16px; }
.sbb-scroll { overflow-x: auto; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); }
.sbb-table { width: 100%; border-collapse: separate; border-spacing: 0; color: #e2e8f0; font-variant-numeric: tabular-nums; }
.sbb-table thead th { position: sticky; top: 0; background: #191c4a; color: #fff; font-size: 10px; font-weight: 800; letter-spacing: 0.05em; padding: 12px 8px; text-align: center; white-space: nowrap; }
.sbb-th--player { color: #e10600; text-align: left; padding-left: 14px; }
.sbb-th-label { display: inline-flex; align-items: center; gap: 6px; justify-content: center; }
.sbb-sort { width: 10px; opacity: 0.7; }
.sbb-table tbody td { padding: 10px 8px; text-align: center; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.06); }
.sbb-table tbody tr:nth-child(odd) { background: rgba(255,255,255,0.03); }
.sbb-table tbody tr:nth-child(even) { background: rgba(148,163,184,0.05); }
.sbb-table tbody tr:hover { background: rgba(33,96,196,0.16); }
.sbb-num { font-weight: 800; color: #fff; }
.sbb-player { display: flex; align-items: center; gap: 10px; text-align: left; white-space: nowrap; font-weight: 700; color: #fff; padding-left: 14px !important; }
.sbb-avatar { width: 40px; height: 40px; border-radius: 50%; border: 2px solid rgba(255,255,255,0.25); object-fit: cover; flex-shrink: 0; }
.sbb-tag { font-weight: 800; }
.sbb-velo { font-weight: 800; color: #fff; }
.sbb-edit { border-radius: 999px; padding: 8px; transition: background-color 0.2s; }
.sbb-edit:hover { background: rgba(255,255,255,0.15); }
.sbb-msg { padding: 40px 0; text-align: center; font-size: 20px; color: rgba(255,255,255,0.6); }
</style>
