<script setup>
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import ArmCarePanel from '@/components/training/ArmCarePanel.vue'

const router = useRouter()
</script>

<template>
  <Layout>
    <div class="min-h-full bg-[#060b14] text-white px-4 py-5 lg:px-6">
      <div class="mx-auto max-w-3xl space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-black tracking-wide">Arm Care</h1>
            <p class="text-sm text-white/55">Throwing prep &amp; recovery — work through it and score your reps.</p>
          </div>
          <button
            type="button"
            @click="router.push({ name: 'playerDashboard' })"
            class="rounded-lg border border-white/15 bg-white/5 px-3 py-2 text-xs font-bold uppercase tracking-widest text-white/80"
          >
            ‹ Dashboard
          </button>
        </div>

        <ArmCarePanel />
      </div>
    </div>
  </Layout>
</template>
