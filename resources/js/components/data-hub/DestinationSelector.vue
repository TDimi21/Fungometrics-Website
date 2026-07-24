<script setup>
defineProps({
  teams: { type: Array, required: true },
  sessionTypes: { type: Array, required: true },
  teamId: { type: String, default: '' },
  sessionType: { type: String, default: '' },
  loading: { type: Boolean, default: false },
})
defineEmits(['update:teamId', 'update:sessionType'])

const teamIdOf = (team) => String(team?.id_team ?? team?.id ?? '')
</script>

<template>
  <div class="destination-grid">
    <label>
      <span>Team</span>
      <select :value="teamId" :disabled="loading" @change="$emit('update:teamId', $event.target.value)">
        <option value="">{{ loading ? 'Loading teams…' : 'Select a team' }}</option>
        <option v-for="team in teams" :key="teamIdOf(team)" :value="teamIdOf(team)">{{ team.name }}</option>
      </select>
      <small>Only teams available to the signed-in coach are shown.</small>
    </label>
    <label>
      <span>Session type</span>
      <select :value="sessionType" @change="$emit('update:sessionType', $event.target.value)">
        <option value="">Select a destination</option>
        <option v-for="type in sessionTypes" :key="type" :value="type">{{ type }}</option>
      </select>
      <small>This selection does not create or modify an FMTRX session.</small>
    </label>
  </div>
</template>

<style scoped>
.destination-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:20px; padding:24px; border:1px solid rgba(255,255,255,.1); border-radius:16px; background:rgba(5,12,29,.5); }
label { display:flex; flex-direction:column; gap:9px; }
label > span { color:#fff; font-size:12px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
select { width:100%; min-height:52px; padding:0 14px; border:1px solid rgba(255,255,255,.14); border-radius:11px; background:#0b142c; color:#fff; outline:none; }
select:focus { border-color:#ff2b4a; box-shadow:0 0 0 3px rgba(255,43,74,.12); }
small { color:rgba(148,163,184,.72); font-size:11px; line-height:1.45; }
@media (max-width:700px) { .destination-grid { grid-template-columns:1fr; padding:18px; } }
</style>

