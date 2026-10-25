import {defineStore} from "pinia";
import {ref, computed} from "vue";
import {useUserStore} from "@/store/user";
import { useAxiosAuth } from '@/composables/axios-auth.js'

export const useTeamStore = defineStore('teams',()=>{
  const { axiosGet, axiosPatch } = useAxiosAuth()
    const team = ref({});
    const teamA = ref({});
    const teamB = ref({});
    const teams = ref([]);
    const default_team_id = ref(null)
    const active_team_id = computed(() => team.value?.id_team ?? team.value?.id ?? null)
    const preferenceError = ref('')
    const preferencesReady = ref(false)
    let pendingPreferences = null
    let saveQueue = Promise.resolve()
    const idOf = value => String(value?.id_team ?? value?.id ?? '')
    const ownerId = () => String(useUserStore().userData?.id || '')
    const preferenceOwner = ref('')
    const persistPreference = (patch) => {
      const owner = ownerId()
      const operation = saveQueue.catch(() => {}).then(async () => {
        if (owner !== ownerId()) return
        const {data} = await axiosPatch('coach/team-preferences', patch)
        if (owner === ownerId()) { preferenceError.value = ''; return data.data }
      })
      saveQueue = operation
      return operation
    }
    const setTeam = (data) => {
      team.value = data || {}
      if (preferencesReady.value && preferenceOwner.value === ownerId() && active_team_id.value && teams.value.some(t => idOf(t) === idOf(data))) {
        persistPreference({active_team_id: active_team_id.value}).catch(() => {
          preferenceError.value = 'Team changed for this visit, but could not save your selection. Please retry.'
        })
      }
    }
    const initializePreferences = async ({login = false} = {}) => {
      if (useUserStore().userData?.type !== 'coach') return
      if (pendingPreferences && !login) return pendingPreferences
      const owner = ownerId()
      preferencesReady.value = false
      if (login || preferenceOwner.value !== owner) { team.value = {}; default_team_id.value = null }
      const task = (async () => {
        try {
          const [list, response] = await Promise.all([axiosGet('coach/teams'), axiosGet('coach/team-preferences')])
          if (owner !== ownerId()) return
          teams.value = (list.data?.data || []).map(t => ({...t, players: t.players || []}))
          const prefs = response.data.data
          default_team_id.value = prefs.default_team_id
          const desired = login ? prefs.initial_team_id : (active_team_id.value || prefs.active_team_id || prefs.initial_team_id)
          team.value = teams.value.find(t => idOf(t) === String(desired))
            || teams.value.find(t => idOf(t) === String(prefs.initial_team_id)) || teams.value[0] || {}
          preferenceOwner.value = owner
          preferencesReady.value = true
          preferenceError.value = ''
          if (active_team_id.value) await persistPreference({active_team_id: active_team_id.value})
        } catch {
          if (owner === ownerId()) preferenceError.value = 'Could not load team preferences. Please retry.'
        }
      })()
      pendingPreferences = task
      try { await task } finally { if (pendingPreferences === task) pendingPreferences = null }
    }
    const setDefaultTeam = async () => {
      if (!active_team_id.value || !preferencesReady.value) return
      const selected = active_team_id.value
      try {
        const prefs = await persistPreference({default_team_id: selected})
        if (prefs) default_team_id.value = prefs.default_team_id
      } catch { preferenceError.value = 'Could not save your default team. Please retry.' }
    }
    const setTeamA = (data)=> teamA.value = data
    const setTeamB = (data)=> teamB.value = data
    const setTeams = (data)=> teams.value = data

    const updateTeams = (newTeam) => {
      teams.value = teams.value.filter(team => team.id != newTeam.id)

      teams.value.push(newTeam)

      if (newTeam.id == team.value.id) {
        team.value = newTeam
      }
    }

    const removeTeam = (teamToRemove) => {
      teams.value = teams.value.filter(team => team.id != teamToRemove.id_team)
    }

    const getTeamsFromApi = async() => {
      try {
        const { data } = await axiosGet('coach/teams')
        // Normalize: dummy/auto-created teams can come back without a players array.
        return (data.data ?? []).map(team => ({ ...team, players: team.players ?? [] }))
      } catch (error) {
        console.log(error);
        return []
      }
    }


    return{
      default_team_id, active_team_id, preferenceError, preferencesReady,
      initializePreferences, setDefaultTeam,
      team,
      teamA,
      teamB,
      teams,
      setTeams,
      setTeam,
      setTeamA,
      setTeamB,
      updateTeams,
      removeTeam,
      getTeamsFromApi
    }
  },
  {
    persist:true,
    storage: sessionStorage
  });
