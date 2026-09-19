<template>
  <div class="app-content-list">
    <div v-if="loading" class="loading">
      <div class="spinner"></div>
      <p>{{ t('vapor', 'Loading downloads...') }}</p>
    </div>

    <div v-else-if="downloads.length === 0" class="empty-state">
      <p>{{ t('vapor', 'No failed downloads') }}</p>
    </div>

    <div v-else class="downloads-list">
      <div 
        v-for="download in downloads" 
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
          <button class="btn-retry" @click="retryDownload(download.gid)">
            {{ t('vapor', 'Retry') }}
          </button>
          <button class="btn-delete" @click="deleteDownload(download.gid)">
            {{ t('vapor', 'Delete') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { useDownloads } from '../stores/downloads'

const { fetchDownloads } = useDownloads()
const loading = ref(true)
const downloads = ref([])

const retryDownload = (gid) => {
  console.log('Retry download:', gid)
  // TODO: Implement retry functionality
}

const deleteDownload = (gid) => {
  console.log('Delete download:', gid)
  // TODO: Implement delete functionality
}

onMounted(async () => {
  loading.value = true
  await fetchDownloads('failed')
  // TODO: Get downloads from store
  downloads.value = []
  loading.value = false
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
    }

    .btn-retry {
      background-color: #2196f3;
      color: white;

      &:hover {
        background-color: #1976d2;
      }
    }

    .btn-delete {
      background-color: #f44336;
      color: white;

      &:hover {
        background-color: #d32f2f;
      }
    }
  }
}
</style>
