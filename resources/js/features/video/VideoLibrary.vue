<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth'
import { getAuthToken } from '@/utils/authToken'
const { axiosGet, axiosPost, axiosDelete } = useAxiosAuth()
const context = ref(null), playerId = ref(''), teamId = ref(''), videos = ref([]), nextPage = ref(null)
const title = ref(''), category = ref('pitching'), notes = ref(''), file = ref(null), fileInput = ref(null)
const loading = ref(false), busy = ref(false), error = ref(''), progress = ref(0), playing = ref(null), mediaUrl = ref('')
const selectedTarget = ref({})
let generation = 0, mediaGeneration = 0
const player = computed(() => context.value?.players.find(p => p.id === playerId.value))
const targets = computed(() => [
  ...(context.value?.teams || []).map(t => ({key: `team:${t.id}`, label: `Team: ${t.name} (all current coaches)`, team_id: t.id})),
  ...(context.value?.coaches || []).map(c => ({key: `coach:${c.team_id}:${c.id}`, label: `Coach: ${c.name} — ${context.value?.teams.find(t => t.id === c.team_id)?.name || ''}`, team_id: c.team_id, coach_id: c.id})),
])
const apiRoot = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || ''
const message = e => Object.values(e?.response?.data?.errors || {}).flat().join(' ') || e?.response?.data?.message || e.message || 'Request failed. Please retry.'
function closeVideo() { mediaGeneration++; playing.value = null; if (mediaUrl.value) URL.revokeObjectURL(mediaUrl.value); mediaUrl.value = '' }
async function initialize() {
  error.value = ''
  try { context.value = (await axiosGet('player-videos/context')).data; playerId.value = context.value.players[0]?.id || '' }
  catch (e) { error.value = message(e) }
}
async function load(page = 1) {
  const request = ++generation
  loading.value = true; error.value = ''
  if (page === 1) { videos.value = []; nextPage.value = null }
  try {
    const response = (await axiosGet('player-videos', {player_id: playerId.value, page})).data
    if (request !== generation) return
    videos.value = page === 1 ? response.data : [...videos.value, ...response.data]
    nextPage.value = response.next_page
  } catch (e) { if (request === generation) error.value = message(e) }
  finally { if (request === generation) loading.value = false }
}
watch(playerId, () => {
  closeVideo(); teamId.value = player.value?.team_ids[0] || ''; file.value = null
  title.value = ''; notes.value = ''; if (fileInput.value) fileInput.value.value = ''
  if (playerId.value) load()
})
async function upload() {
  if (!file.value || !title.value.trim()) { error.value = 'Choose a video and enter a title.'; return }
  if (file.value.size > context.value.max_upload_bytes) { error.value = 'Choose a video smaller than 100 MB.'; return }
  busy.value = true; error.value = ''; progress.value = 0
  const data = new FormData()
  for (const [key, value] of Object.entries({player_id: playerId.value, title: title.value.trim(), category: category.value, notes: notes.value, video: file.value})) data.append(key, value)
  if (context.value.role === 'coach') data.append('team_id', teamId.value)
  try {
    await axios.post(apiRoot + 'player-videos', data, {headers: {Authorization: `Bearer ${getAuthToken()}`}, timeout: 300000,
      onUploadProgress: event => { if (event.total) progress.value = Math.round(event.loaded * 100 / event.total) }})
    file.value = null; title.value = ''; notes.value = ''; fileInput.value.value = ''; await load()
  } catch (e) { error.value = message(e) }
  finally { busy.value = false }
}
async function play(video) {
  closeVideo(); const request = mediaGeneration; playing.value = video; error.value = ''
  try {
    const response = await axios.get(apiRoot + `player-videos/${video.id}/content`, {headers: {Authorization: `Bearer ${getAuthToken()}`}, responseType: 'blob', timeout: 300000})
    if (request === mediaGeneration) mediaUrl.value = URL.createObjectURL(response.data)
  } catch { if (request === mediaGeneration) { closeVideo(); error.value = 'Video could not be played. Refresh the library to check your access.' } }
}
async function mutate(action) { busy.value = true; error.value = ''; try { await action(); await load() } catch (e) { error.value = message(e) } finally { busy.value = false } }
function share(video) {
  const target = targets.value.find(t => t.key === selectedTarget.value[video.id]); if (!target) return
  return mutate(() => axiosPost(`player-videos/${video.id}/shares`, {team_id: target.team_id, coach_id: target.coach_id || null}))
}
function remove(video) {
  if (!window.confirm(`Delete “${video.title}” from this player's library? This cannot be undone.`)) return
  closeVideo(); return mutate(() => axiosDelete('player-videos/', video.id))
}
onMounted(initialize)
onBeforeUnmount(() => { generation++; closeVideo() })
</script>
<template>
  <Layout><main class="library">
    <h1>Player Video Library</h1>
    <p>Upload mechanics clips and share only the videos you choose. Team sharing includes current coaches, not teammates.</p>
    <p v-if="error" role="alert" class="error">{{ error }} <button :disabled="busy || loading" @click="context ? load() : initialize()">Retry</button></p>
    <template v-if="context">
      <label v-if="context.role === 'coach'">Player
        <select v-model="playerId" :disabled="busy"><option v-for="p in context.players" :key="p.id" :value="p.id">{{ p.name }}</option></select>
      </label>
      <p v-if="!playerId">No active roster players are available.</p>
      <template v-else>
        <h2>{{ player?.name }}’s videos</h2>
        <form @submit.prevent="upload" class="panel">
          <h3>Upload a video</h3>
          <p v-if="context.role === 'coach'">This clip will be attached to this player and shared with you. The player controls further sharing and can remove your access.</p>
          <p v-else>New uploads are private until you share them.</p>
          <label v-if="context.role === 'coach'">Team
            <select v-model="teamId" :disabled="busy" required><option v-for="id in player?.team_ids || []" :key="id" :value="id">{{ context.teams.find(t => t.id === id)?.name }}</option></select>
          </label>
          <label>Title <input v-model="title" maxlength="120" required :disabled="busy"></label>
          <label>Category <select v-model="category" :disabled="busy"><option v-for="kind in ['pitching','hitting','fielding','other']" :key="kind">{{ kind }}</option></select></label>
          <label>Notes <textarea v-model="notes" maxlength="2000" :disabled="busy" /></label>
          <label>Video (MP4, MOV, or WebM; up to 100 MB)<input ref="fileInput" type="file" accept="video/mp4,video/quicktime,video/webm" :disabled="busy" @change="file = $event.target.files[0] || null"></label>
          <p class="hint">MP4 with H.264 video works best across devices. Library limit: 50 clips or 2 GB.</p>
          <button :disabled="busy || !file">{{ busy ? `Working… ${progress}%` : 'Upload video' }}</button>
        </form>
        <section v-if="playing" class="panel">
          <h3>{{ playing.title }}</h3><button @click="closeVideo">Close video</button>
          <video v-if="mediaUrl" :src="mediaUrl" controls playsinline preload="metadata" @error="error = 'This format cannot play in this browser. Try uploading an H.264 MP4.'" />
          <p v-else role="status">Loading video…</p>
        </section>
        <p v-if="loading" role="status">Loading library…</p>
        <p v-else-if="!videos.length">{{ context.role === 'player' ? 'No videos yet. Upload your first clip above.' : 'No videos shared with you for this player. You can upload a clip above.' }}</p>
        <article v-for="video in videos" :key="video.id" class="panel">
          <h3>{{ video.title }}</h3><p>{{ video.category }} · {{ video.created_at?.slice(0,10) }} · {{ (video.bytes / 1048576).toFixed(1) }} MB</p><p>{{ video.notes }}</p>
          <button :disabled="busy" @click="play(video)">Watch video</button>
          <button v-if="video.can_delete" :disabled="busy" @click="remove(video)">Delete</button>
          <template v-if="video.can_manage_sharing">
            <p>{{ video.shares.length ? 'Shared with:' : 'Private — only you can view this clip.' }}</p>
            <p v-for="grant in video.shares" :key="grant.id">{{ grant.coach_id ? 'Coach' : 'Team' }}: {{ grant.name }}
              <button :disabled="busy" @click="mutate(() => axiosDelete(`player-videos/${video.id}/shares/`, grant.id))">Remove access</button>
            </p>
            <label>Share this video<select v-model="selectedTarget[video.id]" :disabled="busy"><option value="">Choose team or coach</option><option v-for="target in targets" :key="target.key" :value="target.key">{{ target.label }}</option></select></label>
            <button :disabled="busy || !selectedTarget[video.id]" @click="share(video)">Grant access</button>
            <p class="hint">A coach may also have access through a team share. Remove both shares to revoke that coach completely. Removing access cannot erase a copy someone already saved.</p>
          </template>
        </article>
        <button v-if="nextPage" :disabled="loading || busy" @click="load(nextPage)">Load more videos</button>
      </template>
    </template>
  </main></Layout>
</template>
<style scoped>
.library{max-width:1000px;margin:auto;padding:24px;color:#fff}.library h1{font-size:28px;font-weight:800}.library h2{font-size:22px;margin:20px 0}.library h3{font-size:18px;font-weight:700}.panel{margin:20px 0;padding:20px;background:#101c35;border:1px solid #40516b;border-radius:12px}.library p{margin:10px 0}.library label{display:block;margin:12px 0}.library input,.library select,.library textarea{display:block;width:100%;padding:10px;color:#142238;background:#fff;border-radius:6px}.library button{padding:10px 16px;margin:6px 8px 6px 0;background:#204a7d;border-radius:6px;min-height:44px}.library button:disabled{opacity:.5}.library video{width:100%;max-height:600px;margin-top:12px}.hint{font-size:13px;color:#bdc9da}.error{padding:12px;background:#5d2030}
</style>
