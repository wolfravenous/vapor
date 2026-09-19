import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import tippy, { delegate } from 'tippy.js'
import 'tippy.js/dist/tippy.css'
import './css/style.scss'
import './css/table.scss'

const basePath = "/apps/vapor"

window.addEventListener('DOMContentLoaded', function () {
  const dataContainerID = "app-settings-data"
  const dataContainer = document.getElementById(dataContainerID)
  
  let settings = {}
  try {
    let settingsStr = dataContainer.getAttribute("data-settings")
    let searchSites = dataContainer.getAttribute("data-search-sites")
    settings = JSON.parse(settingsStr)
    settings.search_sites = JSON.parse(searchSites)
  } catch (e) {
    console.error('Failed to parse settings:', e)
  }

  const app = createApp(App)
  
  // Provide settings to all components
  app.provide('settings', settings)
  
  // Use router
  app.use(router)
  
  // Mount app
  app.mount('#app-vapor-wrapper')
  
  // Initialize tooltips
  delegate('#app-vapor-wrapper', {
    target: '[data-tippy-content]'
  })
})
