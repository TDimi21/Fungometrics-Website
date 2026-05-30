<script setup>
import Layout from '@/layout/Layout.vue'
import benchmarks from '../data/defaultAgePercentileBenchmarks'
</script>

<template>
  <Layout>
    <div class="mx-auto w-full max-w-7xl px-4 py-6">
      <div class="rounded-xl border border-white/10 bg-slate-900/70 p-3 mb-4">
        <div class="flex flex-wrap items-center gap-2">
          <RouterLink to="/dashboard?tab=development" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">← Back to Dashboard</RouterLink>
          <RouterLink to="/development" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Player</RouterLink>
          <RouterLink to="/development/team" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Team</RouterLink>
          <RouterLink to="/development/coach" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Coach</RouterLink>
          <RouterLink to="/development/admin/benchmarks" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Admin</RouterLink>
        </div>
      </div>

      <h1 class="text-2xl font-semibold text-white">Admin Benchmarks Dashboard</h1>
      <p class="mt-2 text-slate-300">Editable benchmark controls will be Phase 3. For now this displays seeded benchmark data.</p>

      <div class="mt-4 overflow-x-auto rounded-xl border border-white/10 bg-slate-900/70 p-4">
        <table class="min-w-full text-left text-sm text-slate-300">
          <thead>
            <tr class="text-xs uppercase text-slate-400">
              <th class="py-2 pr-4">Metric</th>
              <th class="py-2 pr-4">Age</th>
              <th class="py-2 pr-4">Level</th>
              <th class="py-2 pr-4">P50</th>
              <th class="py-2">P90</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(b, idx) in benchmarks" :key="idx" class="border-t border-white/10">
              <td class="py-2 pr-4">{{ b.metric_key }}</td>
              <td class="py-2 pr-4">{{ b.age_group }}</td>
              <td class="py-2 pr-4">{{ b.level }}</td>
              <td class="py-2 pr-4">{{ b.p50 }}</td>
              <td class="py-2">{{ b.p90 }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </Layout>
</template>
