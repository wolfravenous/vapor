<template>
  <div class="app-content-list">
    <div v-if="loading" class="loading">
      <div class="spinner"></div>
      <p>{{ t('vapor', 'Loading downloads...') }}</p>
    </div>

    <div v-else-if="displayDownloads.length === 0" class="empty-state">
      <p>{{ t('vapor', 'No completed downloads') }}</p>
    </div>

    <div v-else class="downloads-list">
      <div 
        v-for="download in displayDownloads" 
        :key="download.gid"
        class="download-item complete"
        :class="{ 'ytdl': download.tool === 'ytdl', 'aria2': download.tool === 'aria2' }"
      >
        <div class="download-header">
          <span class="download-tool-badge" :title="download.tool">
            {{ download.tool === 'ytdl' ? '▶' : '⬇' }}
          </span>
          <span class="download-filename">{{ download.filename }}</span>
          <span class="download-status">{{ t('vapor', 'Complete') }}</span>
        </div>

        <div class="download-progress-bar">
          <div class="progress" style="width: 100%"></div>
        </div>

        <div class="download-details">
          <span v-if="download.totalSize !== null" class="detail">
            {{ formatBytes(download.totalSize) }}
          </span>
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

const formatBytes = (bytes) => {
  if (!bytes) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}


const deleteDownload = async (gid) => {
  actionLoading.value[gid] = true
  try {
    const response = await fetch(helper.generateUrl(`/apps/vapor/api/v2/downloads/${gid}/delete`), {
      method: 'POST'
    })

    let data = {}
    try {
      data = await response.json()
    } catch (e) {
      // non-JSON response; fall through to !response.ok check below
    }

    if (!response.ok || data.status === 'error') {
      throw new Error(data.error || data.message || 'Failed to delete download')
    }

    if (data.fileDeleted) {
      helper.message(t('vapor', 'Download and file deleted'))
    } else {
      helper.warn(t('vapor', 'Removed from list — file was not found on disk'))
    }

    // Refresh downloads list
    await fetchDownloads('complete')
    displayDownloads.value = downloads.value.complete || []
  } catch (error) {
    console.error('Failed to delete download:', error)
    helper.error(t('vapor', 'Failed to delete download: ') + error.message)
  } finally {
    actionLoading.value[gid] = false
  }
}



const loadDownloads = async () => {
  loading.value = true
  try {
    await fetchDownloads('complete')
    displayDownloads.value = downloads.value.complete || []
  } catch (error) {
    console.error('Failed to load complete downloads:', error)
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
    await fetchDownloads('complete')
    displayDownloads.value = downloads.value.complete || []
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
  border-left: 4px solid #4caf50;

  &.complete {
    border-left-color: #4caf50;
    opacity: 0.85;
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
      color: #4caf50;
      font-size: 0.875rem;
      font-weight: 600;
    }
  }

  .download-progress-bar {
    width: 100%;
    height: 0.5rem;
    background-color: var(--color-border);
    border-radius: 0.25rem;
    overflow: hidden;
    margin-bottom: 0.5rem;

    .progress {
      height: 100%;
      background-color: #4caf50;
    }
  }

  .download-details {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--color-text-maxcontrast);

    .detail {
      flex: 1;
    }

    button {
      padding: 0.25rem 0.75rem;
      border: none;
      border-radius: 0.25rem;
      cursor: pointer;
      font-size: 0.875rem;
      transition: background-color 0.2s ease;
      white-space: nowrap;
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
