<template>
  <nav id="app-navigation" class="app-navigation">
    <ul class="app-navigation-list">
      <li class="app-navigation-entry" v-for="item in menuItems" :key="item.name">
        <router-link 
          :to="{ name: item.name }" 
          class="app-navigation-entry-link"
          :class="{ active: $route.name === item.name }"
        >
          <span class="app-navigation-entry-icon" :class="item.icon"></span>
          <span class="app-navigation-entry-title">{{ item.label }}</span>
          <span class="app-navigation-entry-utils" v-if="counters[item.name] > 0">
            <span class="app-navigation-entry-utils-counter">
              {{ counters[item.name] }}
            </span>
          </span>
        </router-link>
      </li>
    </ul>

    <!-- Settings Section -->
    <div class="app-navigation-settings">
      <button 
        class="app-navigation-settings-button"
        @click="toggleSettings"
      >
        <span class="icon-settings"></span>
        <span>{{ t('vapor', 'Settings') }}</span>
      </button>
      
      <div v-show="showSettings" class="app-navigation-settings-content">
        <div class="settings-item">
          <label for="hide-errors">{{ t('vapor', 'Hide Errors') }}</label>
          <input 
            id="hide-errors" 
            type="checkbox" 
            v-model="settings.hideErrors"
            @change="saveSetting('hideErrors')"
          >
        </div>
        <div class="settings-item">
          <label for="disable-bt">{{ t('vapor', 'Disable BT for non-admin users') }}</label>
          <input 
            id="disable-bt" 
            type="checkbox" 
            v-model="settings.disableBt"
            @change="saveSetting('disableBt')"
          >
        </div>
        <hr>
        <div class="settings-item">
          <span class="settings-label">{{ t('vapor', 'Personal Settings') }}</span>
        </div>
        <div class="settings-item">
          <span class="settings-label">{{ t('vapor', 'Admin Settings') }}</span>
        </div>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { ref, computed } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { useDownloads } from '../stores/downloads'
import { useRoute } from 'vue-router'

const route = useRoute()
const { counters } = useDownloads()

const showSettings = ref(false)

const settings = ref({
  hideErrors: false,
  disableBt: false
})

const menuItems = [
  { name: 'active', label: t('vapor', 'Active Downloads'), icon: 'icon-play' },
  { name: 'waiting', label: t('vapor', 'Waiting Downloads'), icon: 'icon-pause' },
  { name: 'failed', label: t('vapor', 'Failed Downloads'), icon: 'icon-close' },
  { name: 'complete', label: t('vapor', 'Complete Downloads'), icon: 'icon-checkmark' }
]

const toggleSettings = () => {
  showSettings.value = !showSettings.value
}

const saveSetting = (key) => {
  console.log(`Saved setting: ${key} = ${settings.value[key]}`)
  // TODO: Persist to backend
}
</script>

<style scoped lang="scss">
.app-navigation {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow-y: auto;
}

.app-navigation-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.app-navigation-entry {
  margin: 0;
  
  .app-navigation-entry-link {
    display: flex;
    align-items: center;
    padding: 0.5rem 1rem;
    color: var(--color-text-maxcontrast);
    text-decoration: none;
    transition: background-color 0.2s ease;

    &:hover {
      background-color: var(--color-background-hover);
    }

    &.active {
      background-color: var(--color-background-hover);
      color: var(--color-primary);
      font-weight: 600;
    }

    .app-navigation-entry-icon {
      display: inline-block;
      width: 1.5rem;
      height: 1.5rem;
      margin-right: 0.75rem;
      flex-shrink: 0;
    }

    .app-navigation-entry-title {
      flex: 1;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .app-navigation-entry-utils {
      display: flex;
      align-items: center;

      .app-navigation-entry-utils-counter {
        background-color: var(--color-primary);
        color: white;
        border-radius: 999px;
        padding: 0.125rem 0.5rem;
        font-size: 0.75rem;
        font-weight: 600;
        margin-left: 0.5rem;
      }
    }
  }
}

.app-navigation-settings {
  margin-top: auto;
  border-top: 1px solid var(--color-border);
  padding: 0.5rem 0;

  .app-navigation-settings-button {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 0.5rem 1rem;
    background: none;
    border: none;
    color: var(--color-text-maxcontrast);
    cursor: pointer;
    text-align: left;
    transition: background-color 0.2s ease;

    &:hover {
      background-color: var(--color-background-hover);
    }

    .icon-settings {
      display: inline-block;
      width: 1.5rem;
      height: 1.5rem;
      margin-right: 0.75rem;
      flex-shrink: 0;
    }
  }

  .app-navigation-settings-content {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;

    .settings-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.5rem 0;

      label {
        cursor: pointer;
        flex: 1;
      }

      input[type="checkbox"] {
        margin-left: 0.5rem;
      }

      .settings-label {
        font-weight: 600;
        color: var(--color-text);
      }
    }

    hr {
      margin: 0.5rem 0;
      border: none;
      border-top: 1px solid var(--color-border);
    }
  }
}
</style>
