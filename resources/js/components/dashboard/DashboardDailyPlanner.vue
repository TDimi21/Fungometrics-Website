<script setup>
import {ref} from 'vue'
import {useRouter} from 'vue-router'
import TodayTraining from '@/components/planner/TodayTraining.vue'
import CoachWorkoutPlayers from '@/components/planner/CoachWorkoutPlayers.vue'
import {localDateKey} from '@/features/planner/lib/calendar'
import {plannerLink} from '@/features/planner/lib/plannerLinks'
const router=useRouter(), date=ref(localDateKey()), reviewPlan=ref(null), reviewPlayer=ref(null)
function review(plan, player) {reviewPlan.value=plan;reviewPlayer.value=player}
</script>
<template>
 <CoachWorkoutPlayers v-if="reviewPlan" :plan="reviewPlan" :initial-player-id="reviewPlayer" @back="reviewPlan=null" />
 <TodayTraining v-else :date="date" @date="date=$event" @review="review" @calendar="router.push(plannerLink(date,'calendar'))" @library="router.push('/workout-library')" @advanced="router.push(plannerLink(date,'build'))" @edit="router.push(plannerLink(date,'edit',$event.id))" />
</template>
