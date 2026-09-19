<template>
  <div class="app-content-list">
    <div v-if="loading" class="loading">
      <div class="spinner"></div>
      <p>{{ t('vapor', 'Loading downloads...') }}</p>
    </div>

    <div v-else-if="downloads.length === 0" class="empty-state">
      <p>{{ t('vapor', 'No waiting downloads') }}</p>
    </div>

    <div v-else class="downloads-list">
      <div 
        v-for="download in downloads" 
        :key="download.gid"
        class="download-item waiting"
        :class="{ 'ytdl': download.tool === 'ytdl', 'aria2': download.tool === 'aria2' }"
      >
        <div class="download-header">
          <span class="download-tool-badge" :title="download.tool">
            {{ download.tool === 'ytdl' ? '▶' : '⬇' }}
          </span>
          <span class="download-filename">{{ download.filename }}</span>
          <span class="download-status">{{ t('vapor', 'Waiting') }}</span>
        </div>

        <div class="download-details">
          <button class="btn-resume" @click="resumeDownload(download.gid)">
            {{ t('vapor', 'Resume') }}
          </button>
          <button class="btn-cancel" @click="cancelDownload(download.gid)">
            {{ t('vapor', 'Cancel') }}
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

const resumeDownload = (gid) => {
  console.log('Resume download:', gid)
  // TODO: Implement resume functionality
}

const cancelDownload = (gid) => {
  console.log('Cancel download:', gid)
  // TODO: Implement cancel functionality
}

onMounted(async () => {
  loading.value = true
  await fetchDownloads('waiting')
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
  border-left: 4px solid var(--color-primary);
  opacity: 0.75;

  &.waiting {
    border-left-color: #ffa500;
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
      color: #ffa500;
      font-size: 0.875rem;
      font-weight: 600;
    }
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

    .btn-resume {
      background-color: #4caf50;
      color: white;

      &:hover {
        background-color: #45a049;
      }
    }

    .btn-cancel {
      background-color: #f44336;
      color: white;

      &:hover {
        background-color: #d32f2f;
      }
    }
  }
}
</style>
