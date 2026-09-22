<template>
  <div class="app-content-list">
    <div v-if="loading" class="loading">
      <div class="spinner"></div>
      <p>{{ t('vapor', 'Loading downloads...') }}</p>
    </div>

    <div v-else-if="displayDownloads.length === 0" class="empty-state">
      <p>{{ t('vapor', 'No active downloads') }}</p>
    </div>

    <div v-else class="downloads-list">
      <div 
        v-for="download in displayDownloads" 
        :key="download.gid"
        class="download-item"
        :class="{ 'ytdl': download.tool === 'ytdl', 'aria2': download.tool === 'aria2' }"
      >
        <div class="download-header">
          <span class="download-tool-badge" :title="download.tool">
            {{ download.tool === 'ytdl' ? '▶' : '⬇' }}
          </span>
          <span class="download-filename">{{ download.filename }}</span>
          <span class="download-progress-percent">{{ download.progress }}%</span>
        </div>

        <div class="download-progress-bar">
          <div class="progress" :style="{ width: download.progress + '%' }"></div>
        </div>

        <div class="download-details">
          <span v-if="download.speed !== null" class="detail">
            {{ formatSpeed(download.speed) }}
          </span>
          <span v-if="download.totalSize !== null" class="detail">
            {{ formatBytes(download.completedSize) }} / {{ formatBytes(download.totalSize) }}
          </span>
          <span v-else class="detail">{{ t('vapor', 'Processing...') }}</span>
          <button 
            class="btn-cancel" 
            @click="cancelDownload(download.gid)"
            :disabled="actionLoading[download.gid]"
          >
            {{ actionLoading[download.gid] ? t('vapor', 'Cancelling...') : t('vapor', 'Cancel') }}
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

const formatSpeed = (bytesPerSec) => {
  if (!bytesPerSec) return '0 B/s'
  return formatBytes(bytesPerSec) + '/s'
}

const cancelDownload = async (gid) => {
  actionLoading.value[gid] = true
  try {
    const response = await fetch(helper.generateUrl(`/apps/vapor/api/v2/downloads/${gid}/cancel`), {
      method: 'POST'
    })
    
    if (!response.ok) {
      throw new Error('Failed to cancel download')
    }
    
    const data = await response.json()
    helper.message(t('vapor', 'Download cancelled'))
    
    // Refresh downloads list
    await fetchDownloads('active')
    displayDownloads.value = downloads.value.active || []
  } catch (error) {
    console.error('Failed to cancel download:', error)
    helper.error(t('vapor', 'Failed to cancel download'))
  } finally {
    actionLoading.value[gid] = false
  }
}

const loadDownloads = async () => {
  loading.value = true
  try {
    await fetchDownloads('active')
    displayDownloads.value = downloads.value.active || []
  } catch (error) {
    console.error('Failed to load active downloads:', error)
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
    await fetchDownloads('active')
    displayDownloads.value = downloads.value.active || []
  }, 2000)
})

onUnmounted(() => {
  // Clean up interval on component unmount
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
  border-left: 4px solid var(--color-primary);
  transition: box-shadow 0.2s ease;

  &:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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

    .download-progress-percent {
      font-weight: 600;
      color: var(--color-primary);
      min-width: 3rem;
      text-align: right;
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
      background-color: var(--color-primary);
      transition: width 0.3s ease;
    }
  }

  .download-details {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 0.875rem;
    color: var(--color-text-maxcontrast);

    .detail {
      flex: 1;
    }

    .btn-cancel {
      padding: 0.25rem 0.75rem;
      background-color: #f44336;
      color: white;
      border: none;
      border-radius: 0.25rem;
      cursor: pointer;
      font-size: 0.875rem;
      transition: background-color 0.2s ease;
      white-space: nowrap;

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
