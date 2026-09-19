import { createRouter, createWebHashHistory } from 'vue-router'

import ActiveDownloads from './views/ActiveDownloads.vue'
import WaitingDownloads from './views/WaitingDownloads.vue'
import FailedDownloads from './views/FailedDownloads.vue'
import CompleteDownloads from './views/CompleteDownloads.vue'

const routes = [
  {
    path: '/',
    redirect: '/active'
  },
  {
    path: '/active',
    name: 'active',
    component: ActiveDownloads
  },
  {
    path: '/waiting',
    name: 'waiting',
    component: WaitingDownloads
  },
  {
    path: '/failed',
    name: 'failed',
    component: FailedDownloads
  },
  {
    path: '/complete',
    name: 'complete',
    component: CompleteDownloads
  }
]

const router = createRouter({
  history: createWebHashHistory('/apps/vapor/'),
  routes
})

export default router
