<script setup>
import Layout from '@/layout/Layout.vue'
import { DATA_HUB_PLATFORMS } from '@/data/dataHubPlatforms.js'
</script>

<template>
  <Layout>
    <section class="hub-dashboard">
      <header>
        <div>
          <span>FMTRX Data Hub</span>
          <h1>One home for every player-development signal.</h1>
          <p>Prepare data from baseball technology platforms for a connected FMTRX development profile.</p>
        </div>
        <div class="hub-actions"><RouterLink class="secondary" to="/data-hub/unknown-columns">Unknown Columns</RouterLink><RouterLink to="/data-hub/import">Start Preview <b>→</b></RouterLink></div>
      </header>

      <div class="phase-card">
        <div class="phase-number">01</div>
        <div>
          <span>Current phase</span>
          <h2>Import Experience</h2>
          <p>Select a platform, choose a local file, set its future destination, and review the setup. No data is uploaded or imported in Phase 1.</p>
        </div>
      </div>

      <div class="platform-section">
        <div class="section-heading">
          <div><span>Connected foundation</span><h2>Supported platforms</h2></div>
          <small>Additional platforms can be added through the Data Hub platform registry.</small>
        </div>
        <div class="platform-list">
          <article v-for="platform in DATA_HUB_PLATFORMS" :key="platform.key">
            <b>{{ platform.initials }}</b>
            <div><strong>{{ platform.name }}</strong><small>{{ platform.description }}</small></div>
            <span>CSV · XLSX</span>
          </article>
        </div>
      </div>
    </section>
  </Layout>
</template>

<style scoped>
.hub-dashboard { width:min(1180px,calc(100% - 36px)); margin:0 auto; padding:18px 0 50px; color:#fff; }
.hub-dashboard > header { display:flex; align-items:flex-end; justify-content:space-between; gap:28px; padding:34px; border:1px solid rgba(255,255,255,.12); border-radius:22px; background:radial-gradient(circle at 90% 0,rgba(255,43,74,.16),transparent 34%),linear-gradient(135deg,rgba(27,37,72,.94),rgba(6,12,29,.96)); box-shadow:0 24px 65px rgba(0,0,0,.34); }
header span,.section-heading span,.phase-card span { color:#ff4964; font-size:10px; font-weight:900; letter-spacing:.16em; text-transform:uppercase; }
header h1 { max-width:720px; margin-top:8px; font-size:clamp(32px,4.8vw,56px); font-weight:900; letter-spacing:-.04em; line-height:1.02; }
header p { max-width:680px; margin-top:14px; color:rgba(226,232,240,.65); font-size:14px; line-height:1.65; }
header a { display:inline-flex; align-items:center; justify-content:center; gap:22px; min-width:190px; min-height:52px; border:1px solid #ff2b4a; border-radius:12px; background:#ff2b4a; color:#fff; font-size:12px; font-weight:900; letter-spacing:.06em; text-transform:uppercase; box-shadow:0 12px 28px rgba(255,43,74,.22); }
.hub-actions{display:flex;gap:10px}.hub-actions .secondary{border-color:rgba(255,255,255,.16);background:rgba(255,255,255,.04);box-shadow:none}
.phase-card { display:grid; grid-template-columns:auto 1fr; align-items:center; gap:24px; margin-top:16px; padding:24px 28px; border:1px solid rgba(255,255,255,.1); border-radius:18px; background:rgba(18,27,53,.78); }
.phase-number { color:rgba(255,255,255,.1); font-size:56px; font-weight:900; line-height:1; }
.phase-card h2,.section-heading h2 { margin-top:4px; font-size:23px; font-weight:900; }
.phase-card p { margin-top:6px; color:rgba(226,232,240,.62); font-size:12px; line-height:1.6; }
.platform-section { margin-top:16px; padding:26px; border:1px solid rgba(255,255,255,.1); border-radius:18px; background:rgba(11,18,38,.76); }
.section-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:20px; margin-bottom:18px; }
.section-heading > small { max-width:380px; color:rgba(148,163,184,.7); font-size:11px; text-align:right; }
.platform-list { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
.platform-list article { display:grid; grid-template-columns:auto 1fr auto; align-items:center; gap:14px; padding:15px; border:1px solid rgba(255,255,255,.09); border-radius:13px; background:rgba(255,255,255,.035); }
.platform-list article > b { display:grid; place-items:center; width:44px; height:44px; border-radius:12px; background:rgba(255,43,74,.1); color:#ff4964; font-size:11px; }
.platform-list article div { display:flex; min-width:0; flex-direction:column; gap:3px; }
.platform-list article strong { font-size:14px; }
.platform-list article small { overflow:hidden; color:rgba(226,232,240,.56); font-size:10px; text-overflow:ellipsis; white-space:nowrap; }
.platform-list article > span { color:rgba(148,163,184,.58); font-size:9px; letter-spacing:.08em; }
@media(max-width:760px){.hub-dashboard{width:calc(100% - 20px)}.hub-dashboard>header,.section-heading{align-items:flex-start;flex-direction:column}.platform-list{grid-template-columns:1fr}.platform-list article>span{display:none}.phase-card{grid-template-columns:1fr}.section-heading>small{text-align:left}}
</style>
