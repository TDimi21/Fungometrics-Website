<script setup>
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import Layout from '@/layout/Layout.vue'
import { useAccessStore } from '@/store/access.js'
import { useTeamStore } from '@/store/team.js'
import { useUserStore } from '@/store/user.js'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const access = useAccessStore()
const teamStore = useTeamStore()
const userStore = useUserStore()
const { team } = storeToRefs(teamStore)
const { userData } = storeToRefs(userStore)
const { axiosGet } = useAxiosAuth()
const products = ref([])
const loading = ref(true)
const error = ref('')
const teamId = computed(() => team.value?.id_team ?? team.value?.id ?? null)

onMounted(async () => {
  try {
    await access.refresh({ team_id: teamId.value })
    const response = await axiosGet('me/billing/revenuecat/products')
    products.value = Array.isArray(response?.data?.data) ? response.data.data : []
  } catch (_) {
    error.value = 'Current plan information could not be verified. Please try again while online.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <Layout>
    <main class="min-h-screen bg-[#060b14] px-6 py-12 text-white">
      <section class="mx-auto max-w-3xl rounded-2xl border border-white/10 bg-white/5 p-8">
        <p class="text-xs font-black uppercase tracking-[0.25em] text-orange-400">FMTRX access</p>
        <h1 class="mt-3 text-3xl font-black">Plans and upgrades</h1>
        <p class="mt-3 text-white/70">
          Web checkout is not available. Purchases and restores are supported in the FMTRX iOS app using the same account
          ({{ userData?.email || 'your signed-in FMTRX account' }}).
        </p>

        <div v-if="loading" class="mt-8 text-white/60">Checking authoritative access…</div>
        <div v-else-if="error" class="mt-8 rounded-lg border border-red-400/30 bg-red-500/10 p-4 text-red-100">{{ error }}</div>
        <div v-else class="mt-8 space-y-4">
          <div class="rounded-xl border border-white/10 bg-black/20 p-5">
            <p class="text-xs uppercase tracking-widest text-white/50">Current plan</p>
            <p class="mt-1 text-2xl font-black">{{ access.summary.plan || 'free' }}</p>
            <p class="mt-1 text-sm text-white/60">Status: {{ access.summary.status || 'unknown' }}</p>
          </div>
          <div class="rounded-xl border border-white/10 bg-black/20 p-5">
            <p class="font-bold">Store products available for this account: {{ products.length }}</p>
            <p class="mt-2 text-sm text-white/60">Open FMTRX on iPhone, then choose More → Plans &amp; Billing to purchase, restore, or manage your subscription.</p>
          </div>
        </div>
      </section>
    </main>
  </Layout>
</template>
