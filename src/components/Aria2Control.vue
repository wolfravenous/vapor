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

onMounted(() => {
  // Check if user is admin
  if (settings && settings.settings) {
    isAdmin.value = settings.settings.is_admin || false
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

  .aria2-error {
    color: #f44336;
    font-size: 0.875rem;
    margin-top: 0.5rem;
  }
}
</style>
