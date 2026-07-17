/**
 * useUserTimezone — display timestamps in the logged-in user's timezone.
 *
 * Derives the timezone from the user's saved ZIP code (Eastern default) and exposes
 * a `format` helper. Display-only: never mutates stored data. See utils/timezone.js.
 */
import { computed } from 'vue'
import { useUserStore } from '@/store/user'
import { zipToTimezone, formatInZone, shortZoneLabel, DEFAULT_TIMEZONE } from '@/utils/timezone'

export function useUserTimezone() {
  const { userData } = useUserStore()

  const zip = computed(() => userData.value?.zip ?? userData.value?.profile?.zip ?? '')
  const timezone = computed(() => zipToTimezone(zip.value) || DEFAULT_TIMEZONE)
  const zoneLabel = computed(() => shortZoneLabel(timezone.value))

  // Format a timestamp in the user's timezone. `opts` are Intl.DateTimeFormat options.
  const format = (input, opts = {}) => formatInZone(input, zip.value, opts)
  const formatDate = (input) => formatInZone(input, zip.value, { hour: undefined, minute: undefined })
  const formatTime = (input) => formatInZone(input, zip.value, { month: undefined, day: undefined, year: undefined })

  return { zip, timezone, zoneLabel, format, formatDate, formatTime }
}
