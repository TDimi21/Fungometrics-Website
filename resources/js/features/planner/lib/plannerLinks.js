import {localDateKey, parseCalendarDate} from './calendar'
export const validPlannerDate = value => typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) && localDateKey(parseCalendarDate(value)) === value
export const plannerLink = (date, action = 'view', planId = null) => ({
  name: 'workout.planner',
  query: {date, ...(action !== 'view' ? {action} : {}), ...(planId ? {plan: String(planId)} : {})},
})
export const teamPlannerRows = (rows, teamId) => !teamId ? [] : rows.filter(row => String(row.team_id) === String(teamId) && row.status !== 'template')
