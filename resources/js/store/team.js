import {defineStore} from "pinia";
import {ref} from "vue";
import { useAxiosAuth } from '@/composables/axios-auth.js'

export const useTeamStore = defineStore('teams',()=>{
  const { axiosGet } = useAxiosAuth()
    const team = ref('');
    const teamA = ref('');
    const teamB = ref('');
    const teams = ref('');
    const setTeam = (data)=> team.value = data
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
        return data.data
      } catch (error) {
        console.log(error);
        return []
      }
    }


    return{
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
