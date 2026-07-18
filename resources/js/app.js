/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

import './bootstrap';
import { createApp } from 'vue';
import Router, { routeEntitlement } from "../router";
import {createPinia} from "pinia";
import piniaPersisData from "pinia-plugin-persistedstate"
import { plugin, defaultConfig } from '@formkit/vue'
import { generateClasses } from '@formkit/themes'
import {themeFormkit} from "./utils/theme";
import { getUiTheme, applyUiTheme } from "./composables/useUiTheme";
import { getAuthToken, migrateLegacyAuthToken } from "./utils/authToken.js";
import 'vue3-carousel/dist/carousel.css'
import JsonExcel from "vue-json-excel3";
import VueApexCharts from 'vue3-apexcharts'
import { useAccessStore } from '@/store/access.js'

const safeParse = (value) => {
  try {
    return JSON.parse(value)
  } catch (_) {
    return null
  }
}

const sanitizePersistedStores = () => {
  migrateLegacyAuthToken()

  const keys = ['auth', 'user', 'teams', 'players', 'training', 'liveAB']

  for (const key of keys) {
    const raw = sessionStorage.getItem(key)
    if (!raw) continue

    const parsed = safeParse(raw)
    if (!parsed || typeof parsed !== 'object') {
      sessionStorage.removeItem(key)
    }
  }

}

sanitizePersistedStores()

const app = createApp();
applyUiTheme(getUiTheme())
const pinia = createPinia();
pinia.use(piniaPersisData);
app.use(pinia)
app.use(Router)

const access = useAccessStore(pinia)
let accessRefreshInFlight = false
const refreshAndEnforceAccess = async () => {
  if (accessRefreshInFlight || !getAuthToken()) return
  accessRefreshInFlight = true
  try {
    await access.refresh({ team_id: access.teamId })
    const required = routeEntitlement(Router.currentRoute.value)
    if (required && !access.canAccess(required)) {
      await Router.replace({ path: '/dashboard', query: { access_denied: required } })
    }
  } catch (_) {
    const required = routeEntitlement(Router.currentRoute.value)
    if (required) {
      await Router.replace({ path: '/dashboard', query: { access_denied: required } })
    }
  } finally {
    accessRefreshInFlight = false
  }
}

window.setInterval(refreshAndEnforceAccess, 30_000)
window.addEventListener('focus', refreshAndEnforceAccess)
document.addEventListener('visibilitychange', () => {
  if ('visible' === document.visibilityState) refreshAndEnforceAccess()
})
app.use(VueApexCharts)
app.use(plugin,
  defaultConfig({
    config: {
      classes: generateClasses(themeFormkit),
    },
  }))
  app.component("downloadExcel", JsonExcel);
  app.mount('#app');
