<template>
  <nav id="app-navigation" class="sidebar">
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

    <div class="sidebar-settings">
      <button class="settings-toggle" @click="toggleSettings">
        <span class="settings-icon">⚙</span>
        <span class="settings-label">{{ t('vapor', 'Settings') }}</span>
        <span class="toggle-icon" :class="{ open: settingsOpen }">▼</span>
      </button>

      <div v-if="settingsOpen" class="settings-content">
        <Aria2Control />
      </div>
    </div>
  </nav>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { translate as t } from '@nextcloud/l10n'
import { useDownloads } from '../stores/downloads'
import Aria2Control from './Aria2Control.vue'

const route = useRoute()
const { downloads, counters, fetchCounters } = useDownloads()
const settingsOpen = ref(false)

const toggleSettings = () => {
  settingsOpen.value = !settingsOpen.value
}

// Fetch initial counters
fetchCounters()
</script>

<style scoped lang="scss">
.sidebar {
  width: 250px;
  background-color: var(--color-background-secondary);
  border-right: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow-y: auto;

  .sidebar-menu {
    flex: 1;
    padding: 1rem 0;

    .menu-header {
      padding: 0 1rem;
      margin-bottom: 1rem;

      h2 {
        font-size: 0.875rem;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--color-text-maxcontrast);
        margin: 0;
        padding: 0;
      }
    }

    .menu-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem 1rem;
      color: var(--color-text);
      text-decoration: none;
      transition: background-color 0.2s ease;
      cursor: pointer;

      &:hover {
        background-color: var(--color-background-hover);
      }

      &.active {
        background-color: var(--color-primary-light);
        color: var(--color-primary);
        font-weight: 600;
      }

      .menu-icon {
        font-size: 1rem;
        width: 1.5rem;
        text-align: center;
      }

      .menu-label {
        flex: 1;
        font-size: 0.875rem;
      }

      .menu-badge {
        background-color: var(--color-primary);
        color: white;
        border-radius: 50%;
        width: 1.5rem;
        height: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
      }
    }
  }

  .sidebar-settings {
    border-top: 1px solid var(--color-border);
    padding: 0;

    .settings-toggle {
      width: 100%;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 1rem;
      background: none;
      border: none;
      color: var(--color-text);
      cursor: pointer;
      font-size: 0.875rem;
      font-weight: 600;
      transition: background-color 0.2s ease;

      &:hover {
        background-color: var(--color-background-hover);
      }

      .settings-icon {
        font-size: 1rem;
      }

      .settings-label {
        flex: 1;
        text-align: left;
      }

      .toggle-icon {
        transition: transform 0.2s ease;

        &.open {
          transform: rotate(180deg);
        }
      }
    }

    .settings-content {
      padding: 0;
    }
  }
}
</style>
