import { ref, computed } from 'vue'

export function useDownloads() {
  const downloads = ref({
    active: [],
    waiting: [],
    failed: [],
    complete: []
  })

  const counters = computed(() => ({
    active: downloads.value.active.length,
    waiting: downloads.value.waiting.length,
    failed: downloads.value.failed.length,
    complete: downloads.value.complete.length,
    total: downloads.value.active.length + downloads.value.waiting.length + 
           downloads.value.failed.length + downloads.value.complete.length
  }))

  const fetchDownloads = async (status = 'active') => {
    try {
      const response = await fetch(`/apps/vapor/api/v2/downloads/${status}`)
      const data = await response.json()
      
      if (data.status === 'success' && data.downloads) {
        downloads.value[status] = data.downloads
      }
    } catch (error) {
      console.error(`Failed to fetch ${status} downloads:`, error)
    }
  }

  const fetchCounters = async () => {
    try {
      const response = await fetch('/apps/vapor/api/v2/counters')
      const data = await response.json()
      
      if (data.status === 'success' && data.counters) {
        // Fetch full data for each status to populate downloads array
        for (const status in data.counters) {
          await fetchDownloads(status)
        }
      }
    } catch (error) {
      console.error('Failed to fetch counters:', error)
    }
  }

  const fetchAll = async () => {
    try {
      const response = await fetch('/apps/vapor/api/v2/downloads')
      const data = await response.json()
      
      if (data.status === 'success' && data.downloads) {
        // Sort downloads by status
        downloads.value = {
          active: data.downloads.filter(d => d.status === 'active'),
          waiting: data.downloads.filter(d => d.status === 'waiting'),
          failed: data.downloads.filter(d => d.status === 'failed'),
          complete: data.downloads.filter(d => d.status === 'complete')
        }
      }
    } catch (error) {
      console.error('Failed to fetch all downloads:', error)
    }
  }

  return {
    downloads,
    counters,
    fetchDownloads,
    fetchCounters,
    fetchAll
  }
}
