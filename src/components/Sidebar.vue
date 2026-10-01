<template>
  <NcAppNavigation>
    <template #list>
      <NcAppNavigationItem
        :name="t('vapor', 'Active Downloads')"
        icon="icon-download"
        to="/active"
      >
        <template #counter>
          <NcCounterBubble v-if="counters.active" :count="counters.active" />
        </template>
      </NcAppNavigationItem>

      <NcAppNavigationItem
        :name="t('vapor', 'Waiting Downloads')"
        to="/waiting"
      >
        <template #icon>
          <NcAppNavigationIconBullet color="0082c9" />
        </template>
        <template #counter>
          <NcCounterBubble v-if="counters.waiting" :count="counters.waiting" />
        </template>
      </NcAppNavigationItem>

      <NcAppNavigationItem
        :name="t('vapor', 'Failed Downloads')"
        icon="icon-error"
        to="/failed"
      >
        <template #counter>
          <NcCounterBubble v-if="counters.failed" :count="counters.failed" />
        </template>
      </NcAppNavigationItem>

      <NcAppNavigationItem
        :name="t('vapor', 'Complete Downloads')"
        icon="icon-checkmark"
        to="/complete"
      >
        <template #counter>
          <NcCounterBubble v-if="counters.complete" :count="counters.complete" />
        </template>
      </NcAppNavigationItem>
    </template>

    <template #footer>
      <NcAppNavigationSettings :name="t('vapor', 'Settings')">
        <Aria2Control />
        <settingsBar />
      </NcAppNavigationSettings>
    </template>
  </NcAppNavigation>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { NcAppNavigation, NcAppNavigationItem, NcCounterBubble, NcAppNavigationIconBullet, NcAppNavigationSettings } from '@nextcloud/vue'
import { useDownloads } from '../stores/downloads'
import Aria2Control from './Aria2Control.vue'
import settingsBar from '../settingsBar.vue'

const { counters, fetchCounters } = useDownloads()

let countersInterval = null

onMounted(() => {
  fetchCounters()
  countersInterval = setInterval(fetchCounters, 3000)
})

onUnmounted(() => {
  if (countersInterval) {
    clearInterval(countersInterval)
  }
})
</script>
