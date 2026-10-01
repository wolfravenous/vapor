<template>
  <section id="vapor-settings-collapsible-container">
    <div class="vapor-settings-item" :data-tippy-content="errorTooltip">
      <NcCheckboxRadioSwitch
        v-model="toggleStatus"
        type="switch"
        name="ncd_hide_errors"
        @update:model-value="(value) => toggle('ncd_hide_errors', value)"
      >
        {{ errorText }}
      </NcCheckboxRadioSwitch>
    </div>

    <div class="vapor-settings-item">
      <a :href="personal.url" title="">
        <button>{{ personal.title }}</button>
      </a>
    </div>
    <div class="vapor-settings-item" v-if="isAdmin">
      <a :href="admin.url" :title="admin.title">
        <button>{{ admin.title }}</button>
      </a>
    </div>
  </section>
</template>

<script>
import helper from "./utils/helper";
import { translate as t } from "@nextcloud/l10n";
import { NcCheckboxRadioSwitch } from "@nextcloud/vue";
const basePath = "/apps/vapor";

export default {
  name: "settingsBar",
  inject: ["settings"],
  data() {
    let personal = {
      title: t("vapor", "Personal Settings"),
      url: this.settings.settings.personal_url,
    };
    let admin = {
      title: t("vapor", "Admin Settings"),
      url: this.settings.settings.admin_url,
    };
    return {
      personal: personal,
      admin: admin,
      isAdmin: this.settings.settings.is_admin,
      sectionName: t("vapor", "Settings"),
      errorText: t("vapor", "Hide Errors"),
      toggleStatus: helper.str2Boolean(this.settings.settings.ncd_hide_errors),
      errorTooltip: t("vapor", "Enable this to hide errors"),
    };
  },
  methods: {
    toggle(name, value) {
      let data = {};
      data[name] = value ? 1 : 0;
      let path = "/personal/save";
      const url = helper.generateUrl(basePath + path);
      helper.httpClient(url)
        .setData(data)
        .setHandler((resp) => {
          if (resp["message"]) {
            helper.message(t("vapor", resp["message"]), 1000);
          }
        })
        .send();
    },
  },
  components: {
    NcCheckboxRadioSwitch,
  },
};
</script>

<style lang="scss">
@import "css/variables.scss";
#vapor-settings-collapsible-container {
  display: flex;
  flex-flow: column wrap;
}
</style>
