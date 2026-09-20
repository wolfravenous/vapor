<template>
  <div id="app" class="app-container">
    <Sidebar />
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
      uris: {
        ytd_url: helper.generateUrl('/apps/vapor/ytdl/new'),
        aria2_url: helper.generateUrl('/apps/vapor/new'),
        search_url: helper.generateUrl('/apps/vapor/search'),
        upload_url: helper.generateUrl('/apps/vapor/upload'),
      },
    }
  },
  methods: {
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
        helper.pollingYtdl()
        helper.setContentTableType('ytdl-downloads')
      } else {
        helper.polling()
        helper.setContentTableType('active-downloads')
      }

      if (message) {
        helper.info(message)
      }

      let url = formWrapper.getAttribute('action')
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
  mounted() {
    // Poll for updates
    setInterval(() => {
      // Update counters
    }, 2000)
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
}

#app-navigation {
  width: 250px;
  border-right: 1px solid var(--color-border);
  background-color: var(--color-background-secondary);
  overflow-y: auto;
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

  #app-navigation {
    width: 100%;
    border-right: none;
    border-bottom: 1px solid var(--color-border);
  }
}
</style>
