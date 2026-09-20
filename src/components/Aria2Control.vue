<template>
  <div class="aria2-control">
    <div class="aria2-status">
      <span class="status-label">{{ t('vapor', 'Aria2 Daemon') }}</span>
      <span class="status-indicator" :class="aria2Status">
        {{ aria2Status === 'running' ? t('vapor', 'Running') : t('vapor', 'Stopped') }}
      </span>
    </div>
    <button 
      v-if="isAdmin"
      class="aria2-button"
      :class="aria2Status"
      @click="toggleAria2"
      :disabled="loading"
    >
      {{ loading ? t('vapor', 'Loading...') : (aria2Status === 'running' ? t('vapor', 'Stop Aria2') : t('vapor', 'Start Aria2')) }}
    </button>
    <div v-if="error" class="aria2-error">
      {{ error }}
    </div>

    <div v-if="isAdmin" class="bt-toggle-section">
      <div class="bt-toggle">
        <label class="bt-label">
          <input 
            type="checkbox" 
            v-model="disableBtNonAdmin"
            @change="toggleDisableBt"
            class="bt-checkbox"
          />
          <span class="bt-text">{{ t('vapor', 'Disable BitTorrent for non-admin users') }}</span>
        </label>
        <p class="bt-description">{{ t('vapor', 'When enabled, only administrators can download via magnet links and torrent files') }}</p>
      </div>
      <div v-if="btError" class="aria2-error">
        {{ btError }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, inject, onMounted } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import helper from '../utils/helper'

const settings = inject('settings', {})
const aria2Status = ref('unknown')
const loading = ref(false)
const error = ref(null)
const isAdmin = ref(false)
const disableBtNonAdmin = ref(false)
const btError = ref(null)

onMounted(() => {
  // Check if user is admin
  if (settings && settings.settings) {
    isAdmin.value = settings.settings.is_admin || false
    
    // Load BT setting if admin
    if (isAdmin.value) {
      loadBtSetting()
    }
  }
  
  // Load initial aria2 status
  loadAria2Status()
})

const loadAria2Status = async () => {
  try {
    const response = await fetch(helper.generateUrl('/apps/vapor/api/v2/aria2/status'))
    const data = await response.json()
    aria2Status.value = data.running ? 'running' : 'stopped'
    error.value = null
  } catch (err) {
    console.error('Failed to load aria2 status:', err)
    aria2Status.value = 'unknown'
    error.value = t('vapor', 'Failed to load aria2 status')
  }
}

const loadBtSetting = async () => {
  try {
    const response = await fetch(helper.generateUrl('/apps/vapor/getsettings'), {
      method: 'POST'
    })
    const data = await response.json()
    if (data && data.settings) {
      disableBtNonAdmin.value = data.settings.ncd_disable_bt ? true : false
    }
  } catch (err) {
    console.error('Failed to load BT setting:', err)
    btError.value = t('vapor', 'Failed to load BitTorrent setting')
  }
}

const toggleAria2 = async () => {
  loading.value = true
  error.value = null
  
  try {
    const action = aria2Status.value === 'running' ? 'stop' : 'start'
    const response = await fetch(helper.generateUrl(`/apps/vapor/api/v2/aria2/${action}`), {
      method: 'POST'
    })
    
    if (!response.ok) {
      throw new Error(`Failed to ${action} aria2`)
    }
    
    await loadAria2Status()
    helper.message(t('vapor', `Aria2 ${action === 'start' ? 'started' : 'stopped'}`))
  } catch (err) {
    console.error(`Failed to toggle aria2:`, err)
    error.value = err.message
    helper.error(t('vapor', `Failed to ${aria2Status.value === 'running' ? 'stop' : 'start'} aria2`))
  } finally {
    loading.value = false
  }
}

const toggleDisableBt = async () => {
  btError.value = null
  
  try {
    const response = await fetch(helper.generateUrl('/apps/vapor/admin/save'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: `ncd_disable_bt=${disableBtNonAdmin.value ? '1' : '0'}`
    })
    
    if (!response.ok) {
      throw new Error('Failed to save BitTorrent setting')
    }
    
    const data = await response.json()
    helper.message(t('vapor', 'BitTorrent setting saved'))
  } catch (err) {
    console.error('Failed to toggle BT setting:', err)
    btError.value = err.message
    helper.error(t('vapor', 'Failed to save BitTorrent setting'))
    // Revert the toggle on error
    disableBtNonAdmin.value = !disableBtNonAdmin.value
  }
}
</script>

<style scoped lang="scss">
.aria2-control {
  padding: 1rem;
  border-top: 1px solid var(--color-border);
  margin-top: 1rem;

  .aria2-status {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;

    .status-label {
      font-weight: 600;
      font-size: 0.875rem;
    }

    .status-indicator {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      border-radius: 0.25rem;
      font-size: 0.75rem;
      font-weight: 600;

      &.running {
        background-color: #4caf50;
        color: white;
      }

      &.stopped {
        background-color: #f44336;
        color: white;
      }

      &.unknown {
        background-color: #9e9e9e;
        color: white;
      }
    }
  }

  .aria2-button {
    width: 100%;
    padding: 0.5rem;
    border: none;
    border-radius: 0.25rem;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.875rem;
    transition: background-color 0.2s ease;
    margin-bottom: 1rem;

    &.running {
      background-color: #f44336;
      color: white;

      &:hover:not(:disabled) {
        background-color: #d32f2f;
      }
    }

    &.stopped {
      background-color: #4caf50;
      color: white;

      &:hover:not(:disabled) {
        background-color: #45a049;
      }
    }

    &:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
  }

  .bt-toggle-section {
    border-top: 1px solid var(--color-border);
    padding-top: 1rem;
    margin-top: 1rem;

    .bt-toggle {
      .bt-label {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        cursor: pointer;
        margin-bottom: 0.5rem;

        .bt-checkbox {
          margin-top: 0.25rem;
          cursor: pointer;
        }

        .bt-text {
          font-weight: 600;
          font-size: 0.875rem;
          line-height: 1.4;
        }
      }

      .bt-description {
        margin: 0.5rem 0 0 1.5rem;
        font-size: 0.75rem;
        color: var(--color-text-maxcontrast);
        line-height: 1.4;
      }
    }
  }

  .aria2-error {
    color: #f44336;
    font-size: 0.875rem;
    margin-top: 0.5rem;
  }
}
</style>
