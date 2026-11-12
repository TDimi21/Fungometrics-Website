<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import officialLogo from '../assets/img/login/assteslogin/updatedlogo.webp';
import PlatformCapture from '../components/landing/PlatformCapture.vue';
import ImageModal from '../components/landing/ImageModal.vue';
import { platformSections } from '../components/landing/platformSections';

const activeIndex = ref(0);
const expandedReport = ref(null);
const tabButtons = ref([]);
function expand(report) { expandedReport.value = report; }
async function navigateTabs(event) {
  const offsets = { ArrowRight: 1, ArrowLeft: -1 };
  if (!(event.key in offsets) && !['Home', 'End'].includes(event.key)) return;
  event.preventDefault();
  activeIndex.value = event.key === 'Home' ? 0 : event.key === 'End' ? platformSections.length - 1
    : (activeIndex.value + offsets[event.key] + platformSections.length) % platformSections.length;
  await nextTick();
  tabButtons.value[activeIndex.value]?.focus();
}
let previousTitle;
onMounted(() => { previousTitle = document.title; document.title = 'FMTRX | Every rep. Every player. A purpose.'; });
onBeforeUnmount(() => { if (previousTitle !== undefined) document.title = previousTitle; });
const steps = [
  { number: '01', title: 'Capture.', text: 'Use the mobile app to score sessions and record the work on the field.' },
  { number: '02', title: 'Understand.', text: 'Review session reports on the web. Find the patterns behind the performance.' },
  { number: '03', title: 'Develop.', text: 'Turn that review into development priorities and a plan for the next session.' },
];
</script>

<template>
  <div class="fmtrx-home" id="top">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="site-header">
      <a href="#top" class="brand" aria-label="FMTRX home"><img :src="officialLogo" alt="Official Fungo Metrics logo" width="512" height="512" /><span>FMTRX<span class="brand-caption">BASEBALL. WITH PURPOSE.</span></span></a>
      <nav aria-label="Page navigation" class="page-nav"><a href="#inside">Inside FMTRX</a><a href="#coaches">For coaches</a><a href="#players">For players</a></nav>
      <nav aria-label="Account access" class="account-nav"><RouterLink to="/login/coach">Coach Login <span aria-hidden="true">↗</span></RouterLink><RouterLink to="/login/player">Player Login <span aria-hidden="true">↗</span></RouterLink></nav>
    </header>

    <main id="main-content" tabindex="-1">
      <section class="hero section-shell" aria-labelledby="hero-heading">
        <div class="hero-copy">
          <p class="eyebrow"><span class="red-rule"></span> THE WORK DESERVES A PURPOSE</p>
          <h1 id="hero-heading">EVERY REP.<br />EVERY PLAYER.<br /><span>A PURPOSE.</span></h1>
          <p class="hero-description">Turn the work you put in into progress you can see. Training, performance, and player development—connected through FMTRX.</p>
          <div class="button-row"><RouterLink class="button button-red" to="/login/coach">I’m a coach <span aria-hidden="true">↗</span></RouterLink><RouterLink class="button button-outline" to="/login/player">I’m a player <span aria-hidden="true">↗</span></RouterLink></div>
          <p class="hero-note">Capture on the app. Review and plan on the web.</p>
        </div>
        <div class="hero-visual">
          <span class="visual-label">YOUR WORK. A CLEARER PICTURE.</span>
          <PlatformCapture class="hero-main-capture" :report="platformSections[0]" eager @expand="expand" />
          <PlatformCapture class="hero-small-capture" :report="platformSections[2]" compact @expand="expand" />
          <p class="visual-note">{{ platformSections[0].src ? 'REAL PLATFORM. DEMONSTRATION TEST DATA.' : 'PLATFORM PREVIEWS · APPROVED IMAGES PENDING' }}</p>
        </div>
      </section>

      <div class="session-strip" aria-label="Session types"><span class="strip-label">BUILT FOR THE WORK</span><ul><li>Bullpens</li><li>Live ABs</li><li>Cage &amp; BP</li><li>Long Toss</li><li>Weighted Balls</li></ul></div>

      <section id="inside" class="inside section-shell" aria-labelledby="inside-heading">
        <div class="section-intro"><div><p class="eyebrow">INSIDE FMTRX</p><h2 id="inside-heading">DON’T JUST LOG IT.<br /><span>SEE WHAT IT MEANS.</span></h2></div><p>From a single rep to a session’s worth of work. Find the detail that helps you decide what comes next.</p></div>
        <div class="report-tabs" role="tablist" aria-label="Explore session reports" @keydown="navigateTabs">
          <button v-for="(report, index) in platformSections" :key="report.id" :ref="el => tabButtons[index] = el" :id="`tab-${report.id}`" type="button" role="tab" :aria-selected="activeIndex === index" :aria-controls="`panel-${report.id}`" :tabindex="activeIndex === index ? 0 : -1" @click="activeIndex = index"><span class="tab-index" aria-hidden="true">0{{ index + 1 }}</span>{{ report.label }}<span aria-hidden="true">↗</span></button>
        </div>
        <div v-for="(report, index) in platformSections" :key="report.id" v-show="activeIndex === index" :id="`panel-${report.id}`" role="tabpanel" :aria-labelledby="`tab-${report.id}`" tabindex="0" class="report-panel">
          <PlatformCapture :report="report" @expand="expand" />
          <div class="report-copy"><p class="eyebrow">THE SESSION, EXPLAINED</p><h3>{{ report.title }}</h3><p>{{ report.description }}</p><ul class="feature-list"><li v-for="feature in report.features" :key="feature"><span aria-hidden="true">+</span>{{ feature }}</li></ul><button v-if="report.src" type="button" class="text-link" @click="expand(report)">View full screen <span aria-hidden="true">↗</span></button><p v-else class="asset-note">An approved, anonymized screenshot is needed for this preview.</p></div>
        </div>
      </section>

      <section class="audience-section" aria-label="One connected platform for coaches and players"><div class="section-shell audience-grid">
        <article id="coaches" class="audience-card"><p class="eyebrow">FOR THE COACH</p><h2>A ROSTER OF PLAYERS.<br /><span>A PLAN FOR EACH.</span></h2><p>Give the work direction. Use the web to manage your roster, review performance, identify development priorities, and organize training.</p><ul class="feature-list"><li><span aria-hidden="true">01</span>Keep your team and player profiles connected.</li><li><span aria-hidden="true">02</span>Review session results and development reports.</li><li><span aria-hidden="true">03</span>Build practice plans and assign workouts.</li></ul><RouterLink to="/register/coach" class="text-link">Create a coach account <span aria-hidden="true">↗</span></RouterLink></article>
        <article id="players" class="audience-card player-card"><p class="eyebrow">FOR THE PLAYER</p><h2>YOUR WORK.<br /><span>YOUR WAY FORWARD.</span></h2><p>Know what you put in. Review your session history, follow your progress, and stay connected to the training your coach assigns.</p><ul class="feature-list"><li><span aria-hidden="true">01</span>See your recorded sessions and results.</li><li><span aria-hidden="true">02</span>Review personal history or connected team data.</li><li><span aria-hidden="true">03</span>Access assigned workouts and development reports.</li></ul><RouterLink to="/register/player" class="text-link">Create a player account <span aria-hidden="true">↗</span></RouterLink><RouterLink to="/login/player" class="claim-link">Coach already added you? Claim your profile →</RouterLink></article>
      </div></section>

      <section class="process section-shell" aria-labelledby="process-heading"><div class="section-intro"><div><p class="eyebrow">MAKE THE WORK COUNT</p><h2 id="process-heading">FROM REPS<br /><span>TO A REASON.</span></h2></div><p>A connected routine, from the field to the next conversation with your coach.</p></div><div class="steps"><article v-for="step in steps" :key="step.number"><span class="step-number">{{ step.number }}</span><h3>{{ step.title }}</h3><p>{{ step.text }}</p></article></div></section>

      <section class="faq section-shell" aria-labelledby="faq-heading"><div><p class="eyebrow">BEFORE THE FIRST REP</p><h2 id="faq-heading">LET’S GET<br /><span>YOU STARTED.</span></h2></div><div class="faq-list">
        <details><summary>Where should I start?</summary><p>Choose Coach Login or Player Login. New to FMTRX? Use the create-account option for your role. If your coach already created your profile, use the player claim flow to connect to that existing profile.</p><div class="faq-links"><RouterLink to="/register/coach">Create coach account ↗</RouterLink><RouterLink to="/register/player">Create player account ↗</RouterLink></div></details>
        <details><summary>What do I use the app and website for?</summary><p>The mobile app supports scoring and training capture at the field. The web brings session reports, performance review, roster management, and planning together. Available tools depend on your role and plan.</p></details>
        <details><summary>My coach added me. Do I need a new profile?</summary><p>Claim the existing profile instead. Open Player Login and follow the claim instructions using the contact information and invitation details your coach provided.</p><RouterLink class="text-link" to="/login/player">Go to Player Login ↗</RouterLink></details>
        <details><summary>Are personal sessions and team data the same?</summary><p>No. Personal history and team-collected results have distinct scopes. Connected players can review a team’s data or their combined history from the player dashboard.</p></details>
        <details><summary>Does FMTRX measure velocity automatically?</summary><p>Record measured velocity when you score a session. FMTRX organizes and presents recorded results; the app itself is not a radar device. Expected curves are references, not measurements or prescribed training targets.</p></details>
      </div></section>

      <section class="closing" aria-labelledby="closing-heading"><div class="section-shell"><p class="eyebrow">THE NEXT REP STARTS HERE</p><h2 id="closing-heading">OWN YOUR CRAFT.</h2><p>Put purpose behind the work.</p><div class="button-row"><RouterLink to="/login/coach" class="button button-cream">I’m a coach <span aria-hidden="true">↗</span></RouterLink><RouterLink to="/login/player" class="button button-outline">I’m a player <span aria-hidden="true">↗</span></RouterLink></div></div></section>
    </main>
    <footer class="site-footer section-shell"><a href="#top" class="brand" aria-label="Back to top"><img :src="officialLogo" alt="Fungo Metrics" width="512" height="512" loading="lazy" /><span>FMTRX</span></a><p>Every rep. Every player. A purpose.</p><nav aria-label="Footer"><a href="#inside">Inside FMTRX</a><RouterLink to="/login/coach">Coach Login</RouterLink><RouterLink to="/login/player">Player Login</RouterLink></nav><small>© {{ new Date().getFullYear() }} FMTRX</small></footer>
    <ImageModal :report="expandedReport" @close="expandedReport = null" />
  </div>
</template>

<style scoped>
.fmtrx-home { --navy: #081321; --cream: #f5f1e8; --red: #f52e48; --muted: #a9b6c6; background: var(--navy); color: var(--cream); font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 1.65; overflow: clip; }
.fmtrx-home * { box-sizing: border-box; }
a { color: inherit; text-decoration: none; } button { font: inherit; cursor: pointer; } button, a { -webkit-tap-highlight-color: transparent; }
:where(a, button, summary, [tabindex]):focus-visible { outline: 3px solid #ffc970; outline-offset: 5px; }
.skip-link { position: fixed; top: 10px; left: 10px; z-index: 20; padding: 10px 20px; background: var(--cream); color: var(--navy); transform: translateY(-150%); }
.skip-link:focus { transform: none; }
.section-shell { max-width: 1320px; width: calc(100% - 96px); margin: 0 auto; }
.site-header { max-width: 1440px; margin: auto; padding: 22px 48px; display: flex; align-items: center; justify-content: space-between; gap: 28px; border-bottom: 1px solid #ffffff15; }
.brand { display: inline-flex; align-items: center; gap: 10px; font-size: 23px; letter-spacing: .04em; font-weight: 900; line-height: 1.1; flex-shrink: 0; }
.brand img { width: 62px; height: 62px; object-fit: contain; }.brand-caption { display: block; margin-top: 7px; font-size: 8px; letter-spacing: .14em; color: var(--muted); }
.page-nav, .account-nav { display: flex; align-items: center; gap: 24px; font-size: 12px; font-weight: 700; }
.page-nav a { color: #bbc5d1; }.page-nav a:hover, footer a:hover { color: #fff; text-decoration: underline; text-underline-offset: 5px; }
.account-nav { gap: 8px; }.account-nav a { border: 1px solid #455062; padding: 10px 14px; border-radius: 4px; white-space: nowrap; }.account-nav a:first-child { border-color: var(--red); }.account-nav span { margin-left: 8px; color: var(--red); }.account-nav a:hover { background: #1c2b3d; }
.hero { display: grid; grid-template-columns: 1fr 1.1fr; align-items: center; gap: 48px; padding: 80px 0 85px; min-height: 710px; }
.eyebrow { font-size: 10px; font-weight: 800; letter-spacing: .18em; color: #bbc6d2; margin: 0 0 22px; display: flex; align-items: center; gap: 12px; }.red-rule { width: 28px; height: 2px; background: var(--red); }
h1, h2 { font-family: Impact, 'Arial Narrow', 'Helvetica Neue', sans-serif; font-weight: 800; letter-spacing: -.025em; line-height: .98; margin: 0; }
h1 { font-size: clamp(52px, 5.6vw, 84px); }h1 span, h2 span { color: var(--red); }h2 { font-size: clamp(36px, 4vw, 56px); }
.hero-description { max-width: 440px; margin: 26px 0; color: #bbc5d1; font-size: 16px; line-height: 1.75; }
.button-row { display: flex; flex-wrap: wrap; gap: 12px; }.button { display: inline-flex; justify-content: space-between; align-items: center; gap: 28px; padding: 15px 22px; min-height: 52px; border-radius: 4px; font-size: 13px; font-weight: 800; border: 1px solid transparent; transition: background .15s; }.button-red { background: var(--red); color: white; }.button-red:hover { background: #d82038; }.button-outline { border-color: #596171; background: #ffffff03; }.button-outline:hover { background: #ffffff12; }.hero-note { font-size: 11px; color: #95a5b8; margin-top: 20px; }
.hero-visual { position: relative; padding: 24px 12px 100px 0; }.visual-label { font-size: 9px; font-weight: 700; letter-spacing: .2em; color: #a9b6c6; display: block; margin: 0 0 18px; }.hero-main-capture { width: 94%; }.hero-small-capture { position: absolute; width: 51%; right: -12px; bottom: 12px; }.visual-note { position: absolute; bottom: 32px; left: 0; width: 42%; font-size: 8px; line-height: 1.8; letter-spacing: .13em; color: #91a4ba; }
.session-strip { display: flex; align-items: center; justify-content: center; gap: 40px; padding: 26px 32px; background: #101c2b; border-block: 1px solid #293647; }.strip-label { font-size: 9px; letter-spacing: .16em; color: #96a6ba; font-weight: 700; }.session-strip ul { list-style: none; display: flex; flex-wrap: wrap; gap: 32px; margin: 0; padding: 0; }.session-strip li { font-size: 14px; font-weight: 700; }.session-strip li::before { content: '+'; color: var(--red); margin-right: 14px; }
.inside, .process { padding-block: 100px; }section[id], article[id] { scroll-margin-top: 24px; }.section-intro { display: flex; align-items: flex-end; justify-content: space-between; gap: 40px; margin-bottom: 38px; }.section-intro > p { color: var(--muted); max-width: 340px; margin: 0 0 5px; font-size: 14px; }
.report-tabs { display: flex; border-bottom: 1px solid #344054; margin-bottom: 30px; }.report-tabs button { display: flex; align-items: center; justify-content: space-between; gap: 25px; padding: 18px 22px; background: transparent; color: #a9b6c6; font-size: 14px; border-bottom: 3px solid transparent; min-width: 200px; }.report-tabs button[aria-selected='true'] { color: var(--cream); background: #152236; border-bottom-color: var(--red); }.tab-index { color: #8393a8; font-size: 10px; }.report-tabs button[aria-selected='true'] .tab-index { color: var(--red); }
.report-panel { display: grid; grid-template-columns: 1.5fr 1fr; align-items: center; gap: 55px; }.report-copy h3 { font-size: clamp(23px, 2.4vw, 32px); font-weight: 800; letter-spacing: -.03em; line-height: 1.2; margin: 0 0 18px; }.report-copy > p:not(.eyebrow) { color: var(--muted); font-size: 14px; }.report-copy .eyebrow { color: var(--red); font-size: 9px; }
.feature-list { margin: 24px 0; padding: 0; list-style: none; }.feature-list li { display: flex; gap: 14px; align-items: baseline; padding: 12px 0; border-bottom: 1px solid #ffffff14; font-size: 13px; }.feature-list li span { color: var(--red); font-weight: 800; flex-shrink: 0; }.text-link { display: inline-flex; gap: 25px; align-items: center; color: var(--cream); font-size: 13px; font-weight: 800; background: transparent; border-bottom: 1px solid var(--red); padding: 8px 0; }.text-link:hover { color: #ff687b; }.asset-note { font-size: 11px !important; border-left: 2px solid #4b5b70; padding-left: 12px; }
.audience-section { background: #101b2b; border-block: 1px solid #283448; }.audience-grid { display: grid; grid-template-columns: 1fr 1fr; }.audience-card { padding: 65px 55px 65px 0; }.audience-card h2 { font-size: clamp(32px, 3.3vw, 48px); }.audience-card > p:not(.eyebrow) { max-width: 450px; color: var(--muted); font-size: 14px; margin-top: 24px; }.player-card { border-left: 1px solid #2a3749; padding-left: 55px; padding-right: 0; }.claim-link { display: block; margin-top: 18px; font-size: 11px; color: #bcc9d8; }.claim-link:hover { text-decoration: underline; }
.steps { display: grid; grid-template-columns: repeat(3, 1fr); border-top: 1px solid #344054; margin-top: 48px; gap: 40px; }.steps article { padding-top: 25px; }.step-number { color: var(--red); font-size: 12px; letter-spacing: .1em; font-weight: 800; }.steps h3 { font-size: 30px; font-weight: 800; margin: 24px 0 12px; letter-spacing: -.03em; }.steps p { color: var(--muted); font-size: 14px; max-width: 310px; margin: 0; }
.faq { border-top: 1px solid #293647; padding-block: 80px 100px; display: grid; grid-template-columns: .85fr 1.15fr; gap: 65px; }.faq-list details { border-bottom: 1px solid #344054; }.faq-list details:first-child { border-top: 1px solid #344054; }.faq-list summary { list-style: none; cursor: pointer; font-weight: 700; font-size: 14px; padding: 22px 32px 22px 0; position: relative; }.faq-list summary::-webkit-details-marker { display: none; }.faq-list summary::after { content: '+'; color: var(--red); position: absolute; right: 5px; top: 18px; font-size: 23px; }.faq-list details[open] summary::after { content: '−'; }.faq-list p { font-size: 14px; color: var(--muted); margin: 0 0 20px; }.faq-links { display: flex; flex-wrap: wrap; gap: 20px; font-size: 12px; margin-bottom: 20px; text-decoration: underline; }.faq-list .text-link { margin-bottom: 20px; }
.closing { text-align: center; background: var(--red); padding: 70px 0; position: relative; }.closing .eyebrow { justify-content: center; color: #fff; }.closing h2 { font-size: clamp(52px, 7vw, 100px); }.closing p:not(.eyebrow) { margin: 20px 0 25px; }.closing .button-row { justify-content: center; }.closing .button-outline { border-color: #ffffffaa; }.button-cream { color: var(--navy); background: var(--cream); }.button-cream:hover { background: white; }
.site-footer { padding-block: 30px; display: flex; flex-wrap: wrap; align-items: center; gap: 25px; }.site-footer .brand img { width: 44px; height: 44px; }.site-footer .brand { font-size: 19px; }.site-footer p { font-size: 11px; color: var(--muted); }.site-footer nav { display: flex; flex-wrap: wrap; gap: 20px; margin-left: auto; font-size: 11px; }.site-footer small { font-size: 10px; width: 100%; color: #8999ad; border-top: 1px solid #293647; padding-top: 18px; }
@media (min-width: 1450px) { .hero { gap: 65px; } }
@media (max-width: 1100px) { .page-nav { display: none; }.hero { gap: 25px; min-height: auto; padding-block: 65px; }.hero-description { font-size: 14px; }.section-shell { width: calc(100% - 64px); }.site-header { padding-inline: 32px; }.report-panel { gap: 30px; }.session-strip { flex-direction: column; gap: 16px; }.audience-card { padding-right: 32px; }.player-card { padding-left: 32px; padding-right: 0; } }
@media (max-width: 800px) { .hero { grid-template-columns: 1fr; gap: 35px; }.hero h1 { font-size: clamp(58px, 10vw, 84px); }.hero-description { max-width: 520px; font-size: 16px; }.hero-visual { max-width: 650px; width: 100%; margin-inline: auto; }.section-intro { align-items: flex-start; flex-direction: column; gap: 22px; }.section-intro > p { max-width: 520px; }.report-panel { grid-template-columns: 1fr; }.report-copy { max-width: 580px; }.report-tabs button { min-width: 0; flex: 1; gap: 12px; padding-inline: 14px; }.faq { grid-template-columns: 1fr; gap: 35px; }.audience-grid { grid-template-columns: 1fr; }.audience-card { padding: 45px 0; }.player-card { border-left: 0; border-top: 1px solid #344054; }.steps { gap: 24px; }.site-footer p { display: none; } }
@media (max-width: 480px) { .section-shell { width: calc(100% - 40px); }.site-header { padding: 14px 20px; gap: 14px; flex-wrap: wrap; }.brand img { width: 44px; height: 44px; }.brand { font-size: 21px; }.brand-caption { font-size: 7px; }.account-nav { width: 100%; }.account-nav a { flex: 1; text-align: center; font-size: 12px; }.hero { padding-block: 42px 45px; }.hero h1 { font-size: clamp(46px, 12vw, 60px); }.hero-description { font-size: 14px; }.button-row .button { flex: 1; gap: 12px; padding-inline: 16px; }.hero-small-capture { right: 0; width: 60%; }.hero-main-capture { width: 100%; }.hero-visual { padding-right: 0; padding-bottom: 130px; }.visual-note { font-size: 7px; width: 35%; bottom: 28px; }.session-strip { padding: 22px 20px; }.session-strip ul { justify-content: center; gap: 14px 20px; }.session-strip li { font-size: 12px; }.session-strip li::before { margin-right: 8px; }.inside, .process { padding-block: 60px; }.report-tabs button { font-size: 12px; padding: 15px 8px; gap: 6px; }.tab-index { display: none; }.steps { grid-template-columns: 1fr; gap: 0; }.steps article { padding-block: 24px; border-bottom: 1px solid #293647; }.steps h3 { margin-top: 12px; }.steps p { max-width: none; }.faq { padding-block: 55px 65px; }.site-footer nav { margin-left: 0; gap: 18px; }.closing { padding-block: 55px; } }
@media (prefers-reduced-motion: reduce) { *, *::before, *::after { transition: none !important; animation: none !important; scroll-behavior: auto !important; } }
</style>
