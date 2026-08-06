<script setup>
import DashCard from '@/features/shared/components/DashCard.vue'
import updatedLogo from '@/assets/img/login/assteslogin/updatedlogo.webp'

defineProps({
  playerName: { type: String, default: 'Player' },
  playerImageSrc: { type: String, default: '' },
  profile: { type: Object, required: true },
  coachProfile: { type: Object, required: true },
  schoolTeamText: { type: String, default: '—' },
  strengthLine: { type: String, default: '' },
  speedLine: { type: String, default: '' },
  rapsodoReports: { type: Array, default: () => [] },
})

defineEmits(['open-development', 'open-metrics', 'open-sessions', 'open-rapsodo', 'open-assessments', 'open-armcare'])

const profileBgStyle = {
  backgroundImage: `url('${updatedLogo}')`,
  backgroundSize: '70%',
  backgroundPosition: 'center',
  backgroundRepeat: 'no-repeat',
}

// Fall back to the default logo if the avatar URL fails to load (dead link,
// expired blob: from a previous session, wrong host, etc.) instead of a broken icon.
const onAvatarError = (e) => {
  if (e?.target && e.target.src !== updatedLogo) e.target.src = updatedLogo
}
</script>

<template>
  <DashCard>
    <div class="pointer-events-none absolute inset-0 scale-110 opacity-20 blur-2xl" :style="profileBgStyle"></div>

    <div class="relative z-10">
      <h2 class="mb-3 text-lg font-black tracking-wide">Player Profile</h2>

      <div class="mb-3 overflow-hidden rounded-xl border border-white/20 bg-white/10">
        <div class="relative h-52 w-full">
          <img :src="playerImageSrc" :alt="playerName" class="h-full w-full object-cover object-top" @error="onAvatarError" />
          <div class="absolute inset-0 bg-gradient-to-r from-surface/90 via-surface/65 to-surface/20"></div>

          <p class="absolute right-4 top-3 text-4xl font-black text-white/95">#{{ coachProfile.jersey !== '—' ? coachProfile.jersey : '' }}</p>

          <div class="absolute bottom-3 left-4 right-4">
            <div class="min-w-0 pr-2">
              <p class="truncate text-3xl font-black tracking-wide text-white">{{ playerName }}</p>
              <p class="mt-1 text-xl text-white/90">Height: {{ profile.height }}</p>
              <p class="text-xl text-white/90">Weight: {{ profile.weight }}</p>
              <p class="text-xl text-white/90">Position: {{ profile.position }}</p>
              <p class="mt-1 truncate text-sm text-white/70">{{ strengthLine }}</p>
              <p class="truncate text-sm text-white/70">{{ speedLine }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="mb-3 space-y-2">
        <button
          type="button"
          class="flex w-full items-center justify-center rounded-xl border border-emerald-400/50 bg-emerald-500/15 px-4 py-2 text-xs font-black uppercase tracking-widest text-white"
          @click="$emit('open-development')"
        >
          Development Profile
        </button>

        <button
          type="button"
          class="flex w-full items-center justify-center rounded-xl border border-accent-2/60 bg-accent-2 px-4 py-2 text-xs font-black uppercase tracking-widest text-white"
          @click="$emit('open-metrics')"
        >
          Player Metrics ›
        </button>

        <button
          type="button"
          class="flex w-full items-center justify-center rounded-xl border border-rose-900/80 bg-rose-900 px-4 py-2 text-xs font-black uppercase tracking-widest text-white"
          @click="$emit('open-sessions')"
        >
          Session Reports ›
        </button>

        <button
          v-for="report in rapsodoReports.slice(0, 2)"
          :key="`rapsodo-${report.id}`"
          type="button"
          class="flex w-full items-center justify-between rounded-xl border border-teal-400/60 bg-teal-500/15 px-4 py-2 text-xs font-black uppercase tracking-widest text-white"
          @click="$emit('open-rapsodo', report)"
        >
          <span>View Rapsodo Report</span><small class="text-teal-100/70">{{ report.pitch_count }} pitches ›</small>
        </button>

        <button
          type="button"
          class="flex w-full items-center justify-center rounded-xl border border-sky-400/60 bg-sky-500/20 px-4 py-2 text-xs font-black uppercase tracking-widest text-white"
          @click="$emit('open-assessments')"
        >
          Assessment Reports ›
        </button>

        <button
          type="button"
          class="flex w-full items-center justify-center rounded-xl border border-amber-400/60 bg-amber-500/15 px-4 py-2 text-xs font-black uppercase tracking-widest text-white"
          @click="$emit('open-armcare')"
        >
          Arm Care ›
        </button>
      </div>

      <div class="grid grid-cols-2 gap-3 text-sm">
        <div class="rounded-lg border border-white/10 bg-white/5 p-3">
          <p class="text-[10px] uppercase tracking-widest text-white/55">Name</p>
          <p class="mt-1 font-bold">{{ coachProfile.fullName }}</p>
        </div>
        <div class="rounded-lg border border-white/10 bg-white/5 p-3">
          <p class="text-[10px] uppercase tracking-widest text-white/55">Jersey</p>
          <p class="mt-1 font-bold">{{ coachProfile.jersey }}</p>
        </div>
        <div class="rounded-lg border border-white/10 bg-white/5 p-3">
          <p class="text-[10px] uppercase tracking-widest text-white/55">B/T</p>
          <p class="mt-1 font-bold">{{ coachProfile.bats }} / {{ coachProfile.throws }}</p>
        </div>
        <div class="rounded-lg border border-white/10 bg-white/5 p-3">
          <p class="text-[10px] uppercase tracking-widest text-white/55">Grad Year</p>
          <p class="mt-1 font-bold">{{ coachProfile.gradYear }}</p>
        </div>
        <div class="col-span-2 rounded-lg border border-white/10 bg-white/5 p-3">
          <p class="text-[10px] uppercase tracking-widest text-white/55">School / Team</p>
          <p class="mt-1 font-bold">{{ schoolTeamText }}</p>
        </div>
      </div>
    </div>
  </DashCard>
</template>
