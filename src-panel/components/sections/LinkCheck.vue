<template>
  <k-section class="k-section-linkcheck" :label="label">

    <k-stack direction="row" gap="var(--spacing-2)" align="center" style="margin-bottom: var(--spacing-4)">
      <k-input :disabled="!editable || loading" :value="url" type="text" :placeholder="t('moinframe.linkcheck.url')"
        style="flex: 1;" @input="url = $event" />
      <k-input v-if="editable || sitemap" :disabled="!editable || loading" :value="sitemap" type="text"
        :placeholder="t('moinframe.linkcheck.sitemap')" style="flex: 1;" @input="sitemap = $event" />
      <k-button :text="t('moinframe.linkcheck.check')" icon="search" variant="filled" :disabled="loading"
        @click="checkLinks" />
    </k-stack>

    <div class="k-table">
      <table>
        <thead>
          <tr>
            <th class="k-table-index-column" data-mobile="true">#</th>
            <th data-mobile="true">{{ t("moinframe.linkcheck.col.source") }}</th>
            <th data-mobile="true">{{ t("moinframe.linkcheck.col.link") }}</th>
            <th>{{ t("moinframe.linkcheck.col.status") }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading" class="k-table-empty">
            <td colspan="3" data-mobile="true">
              <k-stack direction="row">
                <k-icon type="loader" /> {{ t("moinframe.linkcheck.checking") }}
              </k-stack>
            </td>
          </tr>
          <tr v-else-if="filteredResults.length === 0 && !results">
            <td class="k-table-empty" colspan="3" data-mobile="true">
              {{ t("moinframe.linkcheck.empty") }}
            </td>
          </tr>
          <tr v-else-if="filteredResults.length === 0 && results">
            <td class="k-table-empty" colspan="3" data-mobile="true">
              {{ t("moinframe.linkcheck.noIssues") }}
            </td>
          </tr>
          <tr v-else v-for="(row, index) in paginatedResults" :key="index" :class="statusClass(row.statusCode)">
            <td class="k-table-index-column" data-mobile="true">
              <span class="k-table-index">{{ (page - 1) * limit + index + 1 }}</span>
            </td>
            <td data-mobile="true">
              <a :href="row.sourceUrl" target="_blank" rel="noopener">
                {{ row.sourceUrl }}
              </a>
            </td>
            <td data-mobile="true">
              <a :href="row.linkedUrl" target="_blank" rel="noopener">
                {{ row.linkedUrl }}
              </a>
            </td>
            <td>{{ row.statusCode }}</td>
          </tr>
        </tbody>
        <tfoot v-if="results">
          <tr>
            <td colspan="4">
              {{
                t("moinframe.linkcheck.summary", { total: results.totalLinks, broken: results.brokenCount })
              }}
            </td>
          </tr>
        </tfoot>
      </table>
      <k-pagination class="k-table-pagination" :details="true" :limit="limit" :page="page"
        :total="filteredResults.length" @paginate="onPaginate" />
    </div>
  </k-section>
</template>

<script setup>

import { ref, useSection, useApi, computed, usePanel } from "kirbyuse";
import { section } from "kirbyuse/props";
const api = useApi();
const { t, notification } = usePanel();

const props = defineProps({ defaultUrl: String, defaultSitemap: String, siteUrl: String, editable: { type: Boolean, default: true }, ...section });

const url = ref("");
const sitemap = ref("");
const loading = ref(false);
const results = ref(null);
const label = ref("");
const editable = ref(true);
const page = ref(1);
const limit = 20;

const { load } = useSection();

(async () => {
  const response = await load({
    parent: props.parent,
    name: props.name,
  });

  label.value = response.label;
  editable.value = response.editable ?? true;
  if (!url.value) url.value = response.defaultUrl ?? response.siteUrl;
  if (!sitemap.value) sitemap.value = response.defaultSitemap;
})();

const filteredResults = computed(() => {
  if (!results.value?.results) return [];
  return results.value.results.filter(
    (r) => r.statusCode < 200 || r.statusCode >= 300
  );
});

const paginatedResults = computed(() => {
  const start = (page.value - 1) * limit;
  return filteredResults.value.slice(start, start + limit);
});

const onPaginate = (pagination) => {
  page.value = pagination.page;
};

const statusClass = (code) => {
  if (code === 0) return "status-error";
  if (code >= 400) return "status-broken";
  if (code >= 200 && code < 300) return "status-ok";
  return "status-redirect";
};

const checkLinks = async () => {

  if (!url.value) {
    notification.error(t("moinframe.linkcheck.error.url"));
    return;
  }

  loading.value = true;
  results.value = null;
  page.value = 1;

  try {
    const response = await api.post("moinframe-linkcheck/check", {
      url: url.value,
      sitemap: sitemap.value || null,
    });

    if (response.status === "complete") {
      results.value = response;
      notification.success(t("moinframe.linkcheck.success"));
    }
  } catch (error) {
    notification.error(error.message || "Failed to check links");
  } finally {
    loading.value = false;
  }
};
</script>

<style>
.k-section-linkcheck .k-table th:nth-child(1),
.k-section-linkcheck .k-table td:nth-child(1) {
  width: 3rem;
}

.k-section-linkcheck .k-table th:nth-child(4),
.k-section-linkcheck .k-table td:nth-child(4) {
  width: 5rem;
}

.k-section-linkcheck .k-table tfoot td {
  border-top: 1px solid var(--table-color-border)
}

.k-section-linkcheck .status-ok td:nth-child(4) {
  color: var(--color-green-600);
}

.k-section-linkcheck .status-broken td:nth-child(4) {
  color: var(--color-red-600);
}

.k-section-linkcheck .status-error td:nth-child(4) {
  color: var(--color-red-600);
  font-weight: 600;
}

.k-section-linkcheck .status-redirect td:nth-child(4) {
  color: var(--color-yellow-600);
}
</style>
