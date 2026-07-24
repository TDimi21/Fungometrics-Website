<script setup>
import { useRouter, useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import Layout from '@/layout/Layout.vue'
import { InputImage, InputBase, SelectField} from '@/components/form'
import { ArrowRightIcon } from '@/components/icons'
import { reactive, ref } from 'vue'
import {states} from '../../utils'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { toast } from "@/utils/AlertPlugin"
import { useTeamStore } from '@/store/team.js'
import Loader from '@/components/Loader.vue'

const { axiosPost } = useAxiosAuth()
const teamStore = useTeamStore()
const router = useRouter()
const route = useRoute()

let team = null
const isLoading = ref(false)

const { teams } = storeToRefs(teamStore)

if (route.params.id != '') {
  team = teams.value.find(team => team.id == route.params.id)
}
const { updateTeams } = teamStore

const coach = reactive({
  name: team?.name ?? '',
  zip: team?.zip ?? '',
  state: team?.state ?? '',
  logo: team?.logo ?? ''
});

const sendData = async() => {
  let dataForm = new FormData();
  dataForm.append('name', coach.name)
  dataForm.append('zip', coach.zip)
  dataForm.append('state', coach.state)
  if (coach.logo instanceof File) dataForm.append('logo', coach.logo)

  try {
    isLoading.value = true
    await axiosPost(`coach/edit/teams/${team.id}`, dataForm).then(async(response) => {
      if (response.data.status == "success") {
        updateTeams(response.data.data)
        toast.fire({
          icon: 'success',
          title: 'Team updated',
          text: 'Team update succefully'
        })
        router.push('/manage')
      }
    })

  } catch (error) {
    isLoading.value = !isLoading.value
    let errorsMssg = ''
    Object.values(error.response.data.data.errors).forEach((error) => {
      errorsMssg = errorsMssg + error[0]
    });
    await toast.fire({
      icon: 'warning',
      title: 'Failed to create team',
      text: errorsMssg
    })
  } finally {
    isLoading.value = false
  }

}

</script>

<template>
  <Loader v-show="isLoading"/>
    <Layout>
      <main class="team-update-shell">
        <section class="team-update-card">
          <header class="team-update-header">
            <div>
              <p class="team-update-kicker">Team Management</p>
              <h1>{{ route.params.id ? 'Update Team' : 'Create Team' }}</h1>
              <p>Keep your team identity and location information current.</p>
            </div>
            <RouterLink to="/manage" class="team-update-close" aria-label="Return to Manage Team">×</RouterLink>
          </header>

          <form class="team-update-form" @submit.prevent="sendData">
            <section class="team-logo-card">
              <div class="team-section-heading">
                <span>01</span>
                <div>
                  <h2>Team identity</h2>
                  <p>Upload a square logo for the best display across FMTRX.</p>
                </div>
              </div>
              <div class="team-logo-input">
                <InputImage v-model="coach.logo" label="Team logo"/>
              </div>
            </section>

            <section class="team-details-card">
              <div class="team-section-heading">
                <span>02</span>
                <div>
                  <h2>Team details</h2>
                  <p>These details are used throughout rosters, reports, and sessions.</p>
                </div>
              </div>

              <div class="team-field-grid">
                <label class="team-field">
                  <span>Team name <b>*</b></span>
                  <InputBase v-model="coach.name" required="required" placeholder="Enter team name"/>
                </label>
                <label class="team-field">
                  <span>State <b>*</b></span>
                  <SelectField v-model="coach.state" :options="states"/>
                </label>
                <label class="team-field">
                  <span>ZIP code <b>*</b></span>
                  <InputBase v-model="coach.zip" required="required" inputType="text" placeholder="Enter ZIP code"/>
                </label>
              </div>
            </section>

            <footer class="team-update-actions">
              <RouterLink to="/manage" class="team-cancel-button">Cancel</RouterLink>
              <button class="team-save-button" type="submit" :disabled="isLoading">
                <img alt="" src="../../assets/img/login/assteslogin/ballbutton.svg">
                <span>{{ isLoading ? 'Saving…' : 'Save Team' }}</span>
                <ArrowRightIcon color="ffffff" w="32" h="32"/>
              </button>
            </footer>
          </form>
        </section>
      </main>
    </Layout>
</template>

<style scoped>
.team-update-shell { width: min(1180px, calc(100% - 2rem)); margin: 1.5rem auto 3rem; }
.team-update-card { overflow: hidden; border: 1px solid rgba(255,255,255,.16); border-radius: 28px; background: rgba(6,15,36,.9); box-shadow: 0 28px 80px rgba(0,0,0,.32); color: white; }
.team-update-header { display: flex; justify-content: space-between; gap: 2rem; padding: 2rem 2.25rem; border-bottom: 1px solid rgba(255,255,255,.1); background: linear-gradient(135deg, rgba(255,43,74,.12), transparent 42%); }
.team-update-kicker { color: #fb7185 !important; font-size: .72rem; font-weight: 900; letter-spacing: .24em; text-transform: uppercase; }
.team-update-header h1 { margin-top: .35rem; font-size: clamp(2rem, 4vw, 3.2rem); font-weight: 900; line-height: 1; }
.team-update-header p:last-child { margin-top: .65rem; color: rgba(255,255,255,.55); }
.team-update-close { display: grid; width: 46px; height: 46px; flex: 0 0 auto; place-items: center; border: 1px solid rgba(255,255,255,.15); border-radius: 14px; background: rgba(255,255,255,.05); color: rgba(255,255,255,.72); font-size: 2rem; line-height: 1; transition: .18s ease; }
.team-update-close:hover { border-color: rgba(255,43,74,.55); background: rgba(255,43,74,.14); color: white; }
.team-update-form { padding: 2rem 2.25rem 2.25rem; }
.team-logo-card, .team-details-card { border: 1px solid rgba(255,255,255,.1); border-radius: 22px; background: rgba(255,255,255,.035); padding: 1.5rem; }
.team-details-card { margin-top: 1rem; }
.team-section-heading { display: flex; align-items: flex-start; gap: 1rem; }
.team-section-heading > span { display: grid; width: 38px; height: 38px; place-items: center; border-radius: 12px; background: #ff2b4a; font-size: .75rem; font-weight: 900; }
.team-section-heading h2 { font-size: 1.15rem; font-weight: 900; }
.team-section-heading p { margin-top: .25rem; color: rgba(255,255,255,.48); font-size: .85rem; }
.team-logo-input { width: min(320px, 100%); margin: 1.5rem auto 0; }
.team-field-grid { display: grid; grid-template-columns: 1.4fr 1fr 1fr; gap: 1rem; margin-top: 1.5rem; }
.team-field { display: flex; flex-direction: column; gap: .55rem; }
.team-field > span { color: rgba(255,255,255,.72); font-size: .78rem; font-weight: 900; letter-spacing: .06em; text-transform: uppercase; }
.team-field b { color: #fb7185; }
.team-field :deep(input), .team-field :deep(select) { width: 100%; min-height: 48px; border: 1px solid rgba(255,255,255,.14) !important; border-radius: 12px !important; background: rgba(255,255,255,.07) !important; color: white !important; padding: 0 .9rem; }
.team-field :deep(option) { color: #0f172a; }
.team-logo-input :deep(.image-input-label) { color: rgba(255,255,255,.7); font-weight: 800; }
.team-logo-input :deep(.image-preview-panel) { min-height: 220px; border-color: rgba(255,255,255,.12); background: rgba(2,8,23,.65); }
.team-update-actions { display: flex; justify-content: flex-end; gap: .75rem; margin-top: 1.5rem; }
.team-cancel-button, .team-save-button { display: inline-flex; min-height: 50px; align-items: center; justify-content: center; gap: .7rem; border-radius: 14px; padding: .75rem 1.35rem; font-weight: 900; transition: .18s ease; }
.team-cancel-button { border: 1px solid rgba(255,255,255,.14); background: rgba(255,255,255,.045); color: white; }
.team-cancel-button:hover { background: rgba(255,255,255,.09); }
.team-save-button { min-width: 190px; background: #e10600; color: white; }
.team-save-button:hover { background: #ff2b24; transform: translateY(-1px); }
.team-save-button:disabled { cursor: wait; opacity: .65; }
.team-save-button img { width: 30px; height: 30px; }
@media (max-width: 760px) {
  .team-update-header, .team-update-form { padding: 1.35rem; }
  .team-field-grid { grid-template-columns: 1fr; }
  .team-update-actions { flex-direction: column-reverse; }
  .team-cancel-button, .team-save-button { width: 100%; }
}

</style>
