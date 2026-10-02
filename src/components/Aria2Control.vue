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
        <NcCheckboxRadioSwitch
          v-model="disableBtNonAdmin"
          type="switch"
          @update:model-value="toggleDisableBt"
        >
          {{ t('vapor', 'Disable BT for non-admins') }}
        </NcCheckboxRadioSwitch>
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
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'
import helper from '../utils/helper'

const settings = inject('settings', {})
const aria2Status = ref('unknown')
const loading = ref(false)
const error = ref(null)
const isAdmin = ref(false)
const disableBtNonAdmin = ref(false)
const btError = ref(null)

onMounted(() => {
  if (settings && settings.settings) {
    isAdmin.value = settings.settings.is_admin || false
    if (isAdmin.value) {
      loadBtSetting()
    }
  }
  loadAria2Status()
})

const loadAria2Status = () => {
  helper.httpClient(helper.generateUrl('/apps/vapor/api/v2/aria2/status'))
    .setMethod('GET')
    .setHandler((data) => {
      aria2Status.value = data && data.running ? 'running' : 'stopped'
      error.value = null
    })
    .setErrorHandler((err) => {
      console.error('Failed to load aria2 status:', err)
      aria2Status.value = 'unknown'
      error.value = t('vapor', 'Failed to load aria2 status')
    })
    .send()
}

const loadBtSetting = () => {
  helper.httpClient(helper.generateUrl('/apps/vapor/getsettings'))
    .setData({ name: 'ncd_admin_settings', type: 1, default: [] })
    .setHandler((data) => {
      if (data && typeof data === 'object' && 'ncd_disable_bt' in data) {
        disableBtNonAdmin.value = helper.str2Boolean(data.ncd_disable_bt)
      }
    })
    .setErrorHandler((err) => {
      console.error('Failed to load BT setting:', err)
    })
    .send()
}

const toggleAria2 = () => {
  loading.value = true
  error.value = null
  const action = aria2Status.value === 'running' ? 'stop' : 'start'
  helper.httpClient(helper.generateUrl(`/apps/vapor/api/v2/aria2/${action}`))
    .setData({})
    .setHandler((data) => {
      if (data && data.error) {
        error.value = data.error
        helper.error(t('vapor', `Failed to ${action} aria2`))
      } else {
        loadAria2Status()
        helper.message(t('vapor', `Aria2 ${action === 'start' ? 'started' : 'stopped'}`))
      }
      loading.value = false
    })
    .setErrorHandler((err) => {
      console.error('Failed to toggle aria2:', err)
      error.value = String(err)
      helper.error(t('vapor', `Failed to ${action} aria2`))
      loading.value = false
    })
    .send()
}

const toggleDisableBt = () => {
  btError.value = null
  helper.httpClient(helper.generateUrl('/apps/vapor/admin/save'))
    .setData({ ncd_disable_bt: disableBtNonAdmin.value ? 1 : 0 })
    .setHandler((data) => {
      if (data && data.status === false) {
        btError.value = data.error || t('vapor', 'Failed to save BitTorrent setting')
        helper.error(t('vapor', 'Failed to save BitTorrent setting'))
        disableBtNonAdmin.value = !disableBtNonAdmin.value
      } else {
        helper.message(t('vapor', 'BitTorrent setting saved'))
      }
    })
    .setErrorHandler((err) => {
      console.error('Failed to toggle BT setting:', err)
      btError.value = String(err)
      helper.error(t('vapor', 'Failed to save BitTorrent setting'))
      disableBtNonAdmin.value = !disableBtNonAdmin.value
    })
    .send()
}
</script>

<style scoped lang="scss">
.aria2-control {
  padding: 0.25rem 1rem;
  margin-top: 0;

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
    padding-top: 0.25rem;
    margin-top: 0.25rem;
  }

  .aria2-error {
    color: #f44336;
    font-size: 0.875rem;
    margin-top: 0.5rem;
  }
}
</style>
