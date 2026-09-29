<template>
  <div id="app" class="app-container" :class="{ 'sidebar-closed': !sidebarOpen }">
    <Sidebar />
    <button
      type="button"
      class="sidebar-toggle"
      @click="toggleSidebar"
      :title="sidebarOpen ? t('vapor', 'Close navigation') : t('vapor', 'Open navigation')"
      :aria-label="sidebarOpen ? t('vapor', 'Close navigation') : t('vapor', 'Open navigation')"
    >
      <svg v-if="sidebarOpen" class="toggle-icon" viewBox="0 0 20 20" width="20" height="20" aria-hidden="true">
        <path fill="currentColor" d="M3 4h11v2H3V4zm0 5h11v2H3V9zm0 5h11v2H3v-2zm12.5-6.5L14 9l1.5 1.5L14 12l4-3.5-2.5-1.5z"/>
      </svg>
      <svg v-else class="toggle-icon" viewBox="0 0 20 20" width="20" height="20" aria-hidden="true">
        <path fill="currentColor" d="M3 4h14v2H3V4zm0 5h14v2H3V9zm0 5h14v2H3v-2z"/>
      </svg>
    </button>
    <main id="app-content" class="app-content">
      <div class="app-header">
        <mainForm
          @download="download"
          @search="search"
          @uploadfile="uploadFile"
          :uris="uris"
        ></mainForm>
      </div>
      <router-view />
    </main>
  </div>
</template>

<script>
import { mapState } from 'vuex'
import Sidebar from './components/Sidebar.vue'
import mainForm from './components/mainForm'
import helper from './utils/helper'
import { translate as t } from '@nextcloud/l10n'
import { useDownloads } from './stores/downloads'


const successCallback = (data, element) => {
  if (!data) {
    helper.error(t('vapor', 'Something must have gone wrong!'))
    return
  }
  if (data.hasOwnProperty('error')) {
    helper.error(t('vapor', data.error))
  } else if (data.hasOwnProperty('duplicate')) {
    helper._message(t('vapor', data.message), {
      duration: 15000,
      backgroundColor: '#1976d2',
    })
  } else if (data.hasOwnProperty('message')) {
    helper.message(t('vapor', data.message))
  } else if (data.hasOwnProperty('file')) {
    helper.message(t('vapor', 'Downloading' + ' ' + data.file))
  }
}

export default {
  name: 'mainApp',
  components: {
    Sidebar,
    mainForm,
  },
  inject: ['settings'],
  provide() {
    return {
      search_sites: this.settings.search_sites,
    }
  },
  data() {
    return {
      display: { download: true, search: false },
      // TODO: Replace this custom sidebar-open toggle with Nextcloud's
      // standard <NcAppNavigation> component, which provides the same
      // toggle affordance plus automatic state persistence. Tracked as
      // a follow-up refactor — this is a temporary implementation.
      sidebarOpen: true,
      uris: {
        ytd_url: helper.generateUrl('/apps/vapor/ytdl/new'),
        aria2_url: helper.generateUrl('/apps/vapor/new'),
        search_url: helper.generateUrl('/apps/vapor/search'),
        upload_url: helper.generateUrl('/apps/vapor/upload'),
      },
    }
  },
  methods: {
    t,
    toggleSidebar() {
      this.sidebarOpen = !this.sidebarOpen
    },

    download(event) {
      let element = event.target
      let formWrapper = element.closest('form')
      let formData = helper.getData(formWrapper)
      let inputValue = formData['text-input-value'].trim()
      let message

      if (!helper.isURL(inputValue) && !helper.isMagnetURI(inputValue)) {
        helper.error(t('vapor', inputValue + ' is Invalid'))
        return
      }

      if (formData.type === 'ytdl') {
        formData['extension'] = ''

        if (formData['select-value-extension'] !== 'defaultext') {
          formData['extension'] = formData['select-value-extension']
        }
        message = helper.t('Download task started!')
      }

      if (message) {
        helper.info(message)
      }

      let url = formWrapper.getAttribute('action')
      console.log('Form action attribute:', url)
      console.log('Download type:', formData.type)
      formData['url'] = formData['text-input-value']
      delete formData['text-input-value']

      helper
        .httpClient(url)
        .setData(formData)
        .setHandler(function (data) {
          successCallback(data, element)
        })
        .send()
    },
    search(event) {
      // TODO: Implement search
      console.log('Search:', event)
    },
    uploadFile(event) {
      // TODO: Implement upload
      console.log('Upload:', event)
    },
  },
}
</script>

<style lang="scss">
* {
  box-sizing: border-box;
}

html,
body,
#app {
  height: 100%;
  margin: 0;
  padding: 0;
}

.app-container {
  display: flex;
  height: 100%;
  width: 100%;
  position: relative;
  // Force this container to form its own stacking context so that the
  // toggle button's z-index competes only against siblings inside it,
  // not against anything in the page's outer stacking hierarchy.
  z-index: 1;
  isolation: isolate;
}

// Temporary sidebar toggle. Placed here (not inside Sidebar.vue) so it stays
// visible when the sidebar collapses to zero width. See TODO in data() above.
.sidebar-toggle {
  position: absolute;
  top: 0.75rem;
  left: 250px;                  /* starts at the sidebar's right edge */
  z-index: 9999;                /* well above any sibling */
  width: 2rem;
  height: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-main-background);
  border: 1px solid var(--color-border-dark);
  border-radius: 0.25rem;
  color: var(--color-main-text);
  cursor: pointer;
  padding: 0;
  transition: left 0.2s ease, background-color 0.2s ease;

  &:hover {
    background-color: var(--color-background-hover);
  }

  .toggle-icon {
    display: block;
  }
}

.app-container.sidebar-closed .sidebar-toggle {
  left: 0.5rem;                 /* floats near the left edge when sidebar is hidden */
}

// Width and collapse rules for the sidebar. Using the class (not the
// id="app-navigation" that Nextcloud core reserves) avoids the core
// 300px default from overriding our 250px.
.sidebar {
  width: 250px;
  border-right: 1px solid var(--color-border);
  background-color: var(--color-background-secondary);
  overflow-y: auto;
  transition: width 0.2s ease, min-width 0.2s ease;
  flex-shrink: 0;
}

// TODO: Temporary sidebar collapse. See TODO in App.vue's data() block —
// this should eventually be replaced by NcAppNavigation.
.app-container.sidebar-closed .sidebar {
  width: 0;
  min-width: 0;
  overflow: hidden;
  border-right: none;
}

#app-content {
  flex: 1;
  overflow-y: auto;
  background-color: var(--color-background-primary);
  display: flex;
  flex-direction: column;
}

.app-header {
  flex-shrink: 0;
  border-bottom: 1px solid var(--color-border);
  background-color: var(--color-background-secondary);
  padding: 1rem;
}

.main-form {
  max-width: 1200px;
  margin: 0 auto;
  width: 100%;
}

@media (max-width: 768px) {
  .app-container {
    flex-direction: column;
  }

  .sidebar {
    width: 100%;
    border-right: none;
    border-bottom: 1px solid var(--color-border);
  }

  .sidebar-toggle {
    top: auto;
    bottom: 1rem;
    left: 1rem;
  }

  .app-container.sidebar-closed .sidebar-toggle {
    left: 1rem;
  }
}
</style>
