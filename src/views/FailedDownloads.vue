<template>
  <div class="app-content-list">
    <div v-if="loading" class="loading">
      <div class="spinner"></div>
      <p>{{ t('vapor', 'Loading downloads...') }}</p>
    </div>

    <div v-else-if="displayDownloads.length === 0" class="empty-state">
      <p>{{ t('vapor', 'No failed downloads') }}</p>
    </div>

    <div v-else class="downloads-list">
      <div 
        v-for="download in displayDownloads" 
        :key="download.gid"
        class="download-item failed"
        :class="{ 'ytdl': download.tool === 'ytdl', 'aria2': download.tool === 'aria2' }"
      >
        <div class="download-header">
          <span class="download-tool-badge" :title="download.tool">
            {{ download.tool === 'ytdl' ? '▶' : '⬇' }}
          </span>
          <span class="download-filename">{{ download.filename }}</span>
          <span class="download-status">{{ t('vapor', 'Failed') }}</span>
        </div>

        <div v-if="download.errorMessage" class="error-message">
          {{ download.errorMessage }}
        </div>

        <div class="download-details">
          <button 
            v-if="download.tool === 'ytdl'"
            class="btn-retry" 
            @click="retryDownload(download.gid)"
            :disabled="actionLoading[download.gid]"
          >
            {{ actionLoading[download.gid] ? t('vapor', 'Retrying...') : t('vapor', 'Retry') }}
          </button>
          <button 
            class="btn-delete" 
            @click="deleteDownload(download.gid)"
            :disabled="actionLoading[download.gid]"
          >
            {{ actionLoading[download.gid] ? t('vapor', 'Deleting...') : t('vapor', 'Delete') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { useDownloads } from '../stores/downloads'
import helper from '../utils/helper'

const { downloads, fetchDownloads } = useDownloads()
const loading = ref(true)
const displayDownloads = ref([])
const actionLoading = ref({})
let pollInterval = null

const retryDownload = async (gid) => {
  actionLoading.value[gid] = true
  try {
    const response = await fetch(helper.generateUrl(`/apps/vapor/api/v2/downloads/${gid}/retry`), {
      method: 'POST'
    })
    
    if (!response.ok) {
      throw new Error('Failed to retry download')
    }

    helper.message(t('vapor', 'Download retry started'))
    
    // Refresh downloads list
    await fetchDownloads('failed')
    displayDownloads.value = downloads.value.failed || []
  } catch (error) {
    console.error('Failed to retry download:', error)
    helper.error(t('vapor', 'Failed to retry download'))
  } finally {
    actionLoading.value[gid] = false
  }
}

const deleteDownload = async (gid) => {
  actionLoading.value[gid] = true
  try {
    const response = await fetch(helper.generateUrl(`/apps/vapor/api/v2/downloads/${gid}/delete`), {
      method: 'POST'
    })
    
    if (!response.ok) {
      throw new Error('Failed to delete download')
    }

    helper.message(t('vapor', 'Download deleted'))
    
    // Refresh downloads list
    await fetchDownloads('failed')
    displayDownloads.value = downloads.value.failed || []
  } catch (error) {
    console.error('Failed to delete download:', error)
    helper.error(t('vapor', 'Failed to delete download'))
  } finally {
    actionLoading.value[gid] = false
  }
}

const loadDownloads = async () => {
  loading.value = true
  try {
    await fetchDownloads('failed')
    displayDownloads.value = downloads.value.failed || []
  } catch (error) {
    console.error('Failed to load failed downloads:', error)
    displayDownloads.value = []
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  // Load initial data
  await loadDownloads()
  
  // Poll for updates every 2 seconds
  pollInterval = setInterval(async () => {
    await fetchDownloads('failed')
    displayDownloads.value = downloads.value.failed || []
  }, 2000)
})

onUnmounted(() => {
  if (pollInterval) {
    clearInterval(pollInterval)
  }
})
</script>

<style scoped lang="scss">
.app-content-list {
  padding: 1rem;
  max-width: 1200px;
}

.loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1rem;
  color: var(--color-text-maxcontrast);

  .spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--color-border);
    border-top-color: var(--color-primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
  }

  p {
    margin-top: 1rem;
  }
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-state {
  text-align: center;
  padding: 3rem 1rem;
  color: var(--color-text-maxcontrast);
}

.downloads-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.download-item {
  background-color: var(--color-background-secondary);
  border-radius: 0.5rem;
  padding: 1rem;
  border-left: 4px solid #f44336;

  &.failed {
    border-left-color: #f44336;
  }

  &.ytdl {
    border-left-color: #ff9800;
  }

  &.aria2 {
    border-left-color: #2196f3;
  }

  .download-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;

    .download-tool-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 1.5rem;
      height: 1.5rem;
      border-radius: 50%;
      background-color: var(--color-background-hover);
      font-weight: bold;
      font-size: 0.875rem;
    }

    .download-filename {
      flex: 1;
      font-weight: 500;
      word-break: break-word;
    }

    .download-status {
      color: #f44336;
      font-size: 0.875rem;
      font-weight: 600;
    }
  }

  .error-message {
    background-color: rgba(244, 67, 54, 0.1);
    border-left: 2px solid #f44336;
    padding: 0.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    color: #d32f2f;
  }

  .download-details {
    display: flex;
    gap: 0.5rem;

    button {
      padding: 0.25rem 0.75rem;
      border: none;
      border-radius: 0.25rem;
      cursor: pointer;
      font-size: 0.875rem;
      transition: background-color 0.2s ease;
      white-space: nowrap;
    }

    .btn-retry {
      background-color: #2196f3;
      color: white;

      &:hover:not(:disabled) {
        background-color: #1976d2;
      }

      &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
      }
    }

    .btn-delete {
      background-color: #f44336;
      color: white;

      &:hover:not(:disabled) {
        background-color: #d32f2f;
      }

      &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
      }
    }
  }
}
</style>
