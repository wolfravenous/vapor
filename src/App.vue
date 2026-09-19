<template>
  <div id="app" class="app-container">
    <Sidebar />
    <main id="app-content" class="app-content">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { inject } from 'vue'
import Sidebar from './components/Sidebar.vue'
import { useDownloads } from './stores/downloads'

const settings = inject('settings', {})
const { fetchCounters } = useDownloads()

// Fetch downloads on app mount
fetchCounters()

// Poll for updates every 2 seconds
setInterval(() => {
  fetchCounters()
}, 2000)
</script>

<style lang="scss">
* {
  box-sizing: border-box;
}

html, body, #app {
  height: 100%;
  margin: 0;
  padding: 0;
}

.app-container {
  display: flex;
  height: 100%;
  width: 100%;
}

#app-navigation {
  width: 250px;
  border-right: 1px solid var(--color-border);
  background-color: var(--color-background-secondary);
  overflow-y: auto;
}

#app-content {
  flex: 1;
  overflow-y: auto;
  background-color: var(--color-background-primary);
}

@media (max-width: 768px) {
  .app-container {
    flex-direction: column;
  }

  #app-navigation {
    width: 100%;
    border-right: none;
    border-bottom: 1px solid var(--color-border);
  }
}
</style>
