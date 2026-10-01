<template>
  <NcAppNavigation>
    <template #list>
      <div class="sidebar-menu">
        <div class="menu-header">
          <h2>{{ t('vapor', 'Downloads') }}</h2>
        </div>

        <router-link
          to="/active"
          class="menu-item"
          :class="{ active: $route.path === '/active' }"
        >
          <span class="menu-icon">▶</span>
          <span class="menu-label">{{ t('vapor', 'Active Downloads') }}</span>
          <span v-if="counters.active" class="menu-badge">{{ counters.active }}</span>
        </router-link>

        <router-link
          to="/waiting"
          class="menu-item"
          :class="{ active: $route.path === '/waiting' }"
        >
          <span class="menu-icon">⏸</span>
          <span class="menu-label">{{ t('vapor', 'Waiting Downloads') }}</span>
          <span v-if="counters.waiting" class="menu-badge">{{ counters.waiting }}</span>
        </router-link>

        <router-link
          to="/failed"
          class="menu-item"
          :class="{ active: $route.path === '/failed' }"
        >
          <span class="menu-icon">✕</span>
          <span class="menu-label">{{ t('vapor', 'Failed Downloads') }}</span>
          <span v-if="counters.failed" class="menu-badge">{{ counters.failed }}</span>
        </router-link>

        <router-link
          to="/complete"
          class="menu-item"
          :class="{ active: $route.path === '/complete' }"
        >
          <span class="menu-icon">✓</span>
          <span class="menu-label">{{ t('vapor', 'Complete Downloads') }}</span>
          <span v-if="counters.complete" class="menu-badge">{{ counters.complete }}</span>
        </router-link>
      </div>
    </template>

    <template #footer>
      <div class="sidebar-settings">
        <button class="settings-toggle" @click="toggleSettings">
          <span class="settings-icon">⚙</span>
          <span class="settings-label">{{ t('vapor', 'Settings') }}</span>
          <span class="toggle-icon" :class="{ open: settingsOpen }">▼</span>
        </button>

        <div v-if="settingsOpen" class="settings-content">
          <Aria2Control />
          <settingsBar />
        </div>
      </div>
    </template>
  </NcAppNavigation>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { translate as t } from '@nextcloud/l10n'
import { NcAppNavigation } from '@nextcloud/vue'
import { useDownloads } from '../stores/downloads'
import Aria2Control from './Aria2Control.vue'
import settingsBar from '../settingsBar.vue'

const route = useRoute()
const { downloads, counters, fetchCounters } = useDownloads()
const settingsOpen = ref(false)

const toggleSettings = () => {
  settingsOpen.value = !settingsOpen.value
}

let countersInterval = null

onMounted(() => {
  fetchCounters()
  countersInterval = setInterval(fetchCounters, 3000)
})

onUnmounted(() => {
  if (countersInterval) {
    clearInterval(countersInterval)
  }
})
</script>
