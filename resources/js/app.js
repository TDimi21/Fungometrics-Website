/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

import './bootstrap';
import { createApp } from 'vue';
import Router from "../router";
import {createPinia} from "pinia";
import piniaPersisData from "pinia-plugin-persistedstate"
import { plugin, defaultConfig } from '@formkit/vue'
import { generateClasses } from '@formkit/themes'
import {themeFormkit} from "./utils/theme";
import { getUiTheme, applyUiTheme } from "./composables/useUiTheme";
import 'vue3-carousel/dist/carousel.css'
import JsonExcel from "vue-json-excel3";
import VueApexCharts from 'vue3-apexcharts'

const safeParse = (value) => {
  try {
    return JSON.parse(value)
  } catch (_) {
    return null
  }
}

const sanitizePersistedStores = () => {
  const keys = ['auth', 'user', 'teams', 'players', 'training', 'liveAB']

  for (const key of keys) {
    const raw = sessionStorage.getItem(key)
    if (!raw) continue

    const parsed = safeParse(raw)
    if (!parsed || typeof parsed !== 'object') {
      sessionStorage.removeItem(key)
    }
  }

  try {
    localStorage.removeItem('auth')
  } catch (_) {}
}

sanitizePersistedStores()

const app = createApp();
applyUiTheme(getUiTheme())
const pinia = createPinia();
pinia.use(piniaPersisData);
app.use(pinia)
app.use(Router)
app.use(VueApexCharts)
app.use(plugin,
  defaultConfig({
    config: {
      classes: generateClasses(themeFormkit),
    },
  }))
  app.component("downloadExcel", JsonExcel);
  app.mount('#app');
