<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue';
import {
  setAdminKey,
  clearAdminKey,
  hasAdminKey,
  getHealth,
  getCoverage,
  getSyncRuns,
  getContractChanges,
  getQualityChecks,
  runPipeline,
  refreshBlock,
  refreshApartment,
  type HealthData,
  type CoverageData,
  type SyncRunItem,
  type ContractChangeItem,
  type QualityCheckItem,
  type PipelinePayload,
} from '../api/taAdmin';
import { refreshBlockDetail, refreshApartmentDetail } from '../api/ta';

const ADMIN_BASE = '/api/ta/admin';

const keyInput = ref('');
const keyConfigured = ref(hasAdminKey());
const errorMessage = ref('');
const successMessage = ref('');

const health = ref<HealthData | null>(null);
const healthLoading = ref(false);
const coverage = ref<CoverageData | null>(null);
const coverageLoading = ref(false);
const syncRuns = ref<SyncRunItem[]>([]);
const syncRunsLoading = ref(false);
const contractChanges = ref<ContractChangeItem[]>([]);
const contractChangesLoading = ref(false);
const qualityChecks = ref<QualityCheckItem[]>([]);
const qualityChecksLoading = ref(false);
const pipelineLoading = ref(false);
const pipelineResult = ref<{ queued: boolean; run_id: string } | null>(null);

const syncFilters = ref({ scope: '', status: '', since_hours: 24, limit: 50 });
const contractFilters = ref({ endpoint: '', since_hours: 168, limit: 100 });
const qualityFilters = ref({ scope: '', since_hours: 24, limit: 200, status: 'fail' });

const pipelineForm = ref<PipelinePayload>({
  city_id: '',
  lang: 'ru',
  blocks_count: 50,
  blocks_pages: 1,
  apartments_pages: 1,
  dispatch_details: true,
  detail_limit: 50,
});
const refreshBlockId = ref('');
const refreshApartmentId = ref('');

let pollTimer: ReturnType<typeof setInterval> | null = null;

function showError(msg: string) {
  errorMessage.value = msg;
  successMessage.value = '';
  setTimeout(() => { errorMessage.value = ''; }, 8000);
}
function showSuccess(msg: string) {
  successMessage.value = msg;
  errorMessage.value = '';
  setTimeout(() => { successMessage.value = ''; }, 4000);
}

function saveKey() {
  const k = keyInput.value?.trim();
  if (!k) {
    showError('Введите ключ');
    return;
  }
  setAdminKey(k);
  keyInput.value = '';
  keyConfigured.value = true;
  showSuccess('Ключ сохранён в sessionStorage');
  loadAll();
}

function clearKey() {
  clearAdminKey();
  keyConfigured.value = false;
  health.value = null;
  coverage.value = null;
  syncRuns.value = [];
  contractChanges.value = [];
  qualityChecks.value = [];
  showSuccess('Ключ удалён');
}

async function loadHealth() {
  if (!keyConfigured.value) return;
  healthLoading.value = true;
  try {
    const res = await getHealth();
    health.value = res.data;
    if (res.data.coverage) coverage.value = res.data.coverage;
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : 'Ошибка загрузки health';
    if (!msg.includes('key')) showError(msg);
  } finally {
    healthLoading.value = false;
  }
}

async function loadCoverage() {
  if (!keyConfigured.value) return;
  coverageLoading.value = true;
  try {
    const res = await getCoverage();
    coverage.value = res.data;
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : 'Ошибка загрузки coverage';
    if (!msg.includes('key')) showError(msg);
  } finally {
    coverageLoading.value = false;
  }
}

async function loadSyncRuns() {
  if (!keyConfigured.value) return;
  syncRunsLoading.value = true;
  try {
    const params: Record<string, string | number> = {
      since_hours: syncFilters.value.since_hours,
      limit: syncFilters.value.limit,
    };
    if (syncFilters.value.scope) params.scope = syncFilters.value.scope;
    if (syncFilters.value.status) params.status = syncFilters.value.status;
    const res = await getSyncRuns(params);
    syncRuns.value = res.data ?? [];
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : 'Ошибка загрузки sync runs';
    if (!msg.includes('key')) showError(msg);
  } finally {
    syncRunsLoading.value = false;
  }
}

async function loadContractChanges() {
  if (!keyConfigured.value) return;
  contractChangesLoading.value = true;
  try {
    const params: Record<string, string | number> = {
      since_hours: contractFilters.value.since_hours,
      limit: contractFilters.value.limit,
    };
    if (contractFilters.value.endpoint) params.endpoint = contractFilters.value.endpoint;
    const res = await getContractChanges(params);
    contractChanges.value = res.data ?? [];
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : 'Ошибка загрузки contract changes';
    if (!msg.includes('key')) showError(msg);
  } finally {
    contractChangesLoading.value = false;
  }
}

async function loadQualityChecks() {
  if (!keyConfigured.value) return;
  qualityChecksLoading.value = true;
  try {
    const params: Record<string, string | number> = {
      since_hours: qualityFilters.value.since_hours,
      limit: qualityFilters.value.limit,
      status: qualityFilters.value.status,
    };
    if (qualityFilters.value.scope) params.scope = qualityFilters.value.scope;
    const res = await getQualityChecks(params);
    qualityChecks.value = res.data ?? [];
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : 'Ошибка загрузки quality checks';
    if (!msg.includes('key')) showError(msg);
  } finally {
    qualityChecksLoading.value = false;
  }
}

function loadAll() {
  loadHealth();
  loadCoverage();
  loadSyncRuns();
  loadContractChanges();
  loadQualityChecks();
}

async function runPipelineAction() {
  if (!keyConfigured.value) return;
  pipelineLoading.value = true;
  pipelineResult.value = null;
  try {
    const payload = { ...pipelineForm.value };
    if (!payload.city_id) delete payload.city_id;
    const res = await runPipeline(payload);
    pipelineResult.value = res.data;
    showSuccess(res.data.queued ? 'Pipeline поставлен в очередь' : 'Pipeline выполнен');
    loadHealth();
    loadSyncRuns();
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : 'Ошибка pipeline';
    if (!msg.includes('key')) showError(msg);
  } finally {
    pipelineLoading.value = false;
  }
}

async function doRefreshBlock() {
  const id = refreshBlockId.value?.trim();
  if (!id) {
    showError('Введите block_id');
    return;
  }
  try {
    if (keyConfigured.value) {
      await refreshBlock(id);
    } else {
      await refreshBlockDetail(id);
    }
    showSuccess('Refresh block поставлен в очередь');
    refreshBlockId.value = '';
    loadHealth();
    loadSyncRuns();
  } catch (e: unknown) {
    showError(e instanceof Error ? e.message : 'Ошибка refresh block');
  }
}

async function doRefreshApartment() {
  const id = refreshApartmentId.value?.trim();
  if (!id) {
    showError('Введите apartment_id');
    return;
  }
  try {
    if (keyConfigured.value) {
      await refreshApartment(id);
    } else {
      await refreshApartmentDetail(id);
    }
    showSuccess('Refresh apartment поставлен в очередь');
    refreshApartmentId.value = '';
    loadHealth();
    loadSyncRuns();
  } catch (e: unknown) {
    showError(e instanceof Error ? e.message : 'Ошибка refresh apartment');
  }
}

function copyCurl(endpoint: string, method: string, body?: string) {
  const origin = typeof window !== 'undefined' ? window.location.origin : '';
  let cmd = `curl -H "X-Internal-Key: YOUR_KEY" ${origin}${endpoint}`;
  if (method === 'POST') {
    cmd = `curl -X POST -H "Content-Type: application/json" -H "X-Internal-Key: YOUR_KEY" -d '${body ?? '{}'}' ${origin}${endpoint}`;
  }
  navigator.clipboard.writeText(cmd).then(() => showSuccess('Curl скопирован'));
}

function startPolling() {
  if (pollTimer) return;
  pollTimer = setInterval(() => {
    if (document.visibilityState === 'visible' && keyConfigured.value) {
      loadHealth();
      loadSyncRuns();
    }
  }, 15000);
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

watch(keyConfigured, (v) => {
  if (v) {
    loadAll();
    startPolling();
  } else {
    stopPolling();
  }
});

onMounted(() => {
  if (keyConfigured.value) {
    loadAll();
    startPolling();
  }
});

onUnmounted(() => {
  stopPolling();
});
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-semibold">TA Admin</h1>

    <div v-if="errorMessage" class="rounded bg-red-900/50 px-4 py-2 text-red-200">
      {{ errorMessage }}
    </div>
    <div v-if="successMessage" class="rounded bg-green-900/50 px-4 py-2 text-green-200">
      {{ successMessage }}
    </div>

    <!-- AdminKeyGate -->
    <section v-if="!keyConfigured" class="rounded-lg border border-slate-700 bg-slate-800/50 p-4">
      <h2 class="mb-3 text-lg font-medium">Ключ доступа (X-Internal-Key)</h2>
      <p class="mb-3 text-sm text-slate-400">
        В dev ключ сохраняется только в sessionStorage. Не используйте localStorage.
      </p>
      <div class="flex flex-wrap items-end gap-3">
        <input
          v-model="keyInput"
          type="password"
          placeholder="Введите ключ"
          class="rounded border border-slate-600 bg-slate-900 px-3 py-2 text-sm"
          @keydown.enter="saveKey"
        />
        <button
          type="button"
          class="rounded bg-slate-600 px-4 py-2 text-sm font-medium hover:bg-slate-500"
          @click="saveKey"
        >
          Save
        </button>
      </div>
    </section>

    <template v-else>
      <section class="flex flex-wrap items-center gap-2">
        <span class="text-sm text-slate-400">Ключ задан</span>
        <button
          type="button"
          class="rounded bg-slate-700 px-3 py-1 text-sm hover:bg-slate-600"
          @click="clearKey"
        >
          Clear key
        </button>
      </section>

      <!-- HealthCard -->
      <section class="rounded-lg border border-slate-700 bg-slate-800/50 p-4">
        <div class="mb-2 flex items-center justify-between">
          <h2 class="text-lg font-medium">Health</h2>
          <div class="flex gap-2">
            <button
              type="button"
              class="rounded bg-slate-600 px-3 py-1 text-sm hover:bg-slate-500"
              @click="loadHealth"
            >
              Refresh
            </button>
            <button
              type="button"
              class="rounded bg-slate-700 px-3 py-1 text-sm hover:bg-slate-600"
              @click="copyCurl(`${ADMIN_BASE}/health`, 'GET')"
            >
              Copy curl
            </button>
          </div>
        </div>
        <div v-if="healthLoading" class="text-slate-400">Loading…</div>
        <div v-else-if="health" class="grid gap-2 text-sm">
          <div><span class="text-slate-400">Sync last_success_at:</span> blocks {{ health.sync?.blocks?.last_success_at ?? '—' }}, apartments {{ health.sync?.apartments?.last_success_at ?? '—' }}, block_detail {{ health.sync?.block_detail?.last_success_at ?? '—' }}, apartment_detail {{ health.sync?.apartment_detail?.last_success_at ?? '—' }}</div>
          <div><span class="text-slate-400">contract_changes_last_24h:</span> {{ health.contract_changes_last_24h_count }}</div>
          <div><span class="text-slate-400">quality_fail_last_24h:</span> {{ health.quality_fail_last_24h_count }}</div>
          <div><span class="text-slate-400">queue:</span> {{ health.queue?.connection }} / {{ health.queue?.queue_name }}</div>
        </div>
      </section>

      <!-- CoverageCard -->
      <section class="rounded-lg border border-slate-700 bg-slate-800/50 p-4">
        <div class="mb-2 flex items-center justify-between">
          <h2 class="text-lg font-medium">Coverage</h2>
          <button
            type="button"
            class="rounded bg-slate-600 px-3 py-1 text-sm hover:bg-slate-500"
            :disabled="coverageLoading"
            @click="loadCoverage"
          >
            {{ coverageLoading ? '…' : 'Refresh' }}
          </button>
        </div>
        <div v-if="coverageLoading && !coverage" class="text-slate-400">Loading…</div>
        <div v-else-if="coverage" class="grid gap-2 text-sm">
          <div><span class="text-slate-400">blocks_total:</span> {{ coverage.blocks_total }} · <span class="text-slate-400">with_detail_fresh:</span> {{ coverage.blocks_with_detail_fresh }} · <span class="text-slate-400">without_detail:</span> {{ coverage.blocks_without_detail }}</div>
          <div><span class="text-slate-400">apartments_total:</span> {{ coverage.apartments_total }} · <span class="text-slate-400">with_detail_fresh:</span> {{ coverage.apartments_with_detail_fresh }} · <span class="text-slate-400">without_detail:</span> {{ coverage.apartments_without_detail }}</div>
        </div>
        <div v-else class="text-slate-400">Нет данных. Нажмите Refresh или обновите Health.</div>
      </section>

      <!-- ActionsPanel -->
      <section class="rounded-lg border border-slate-700 bg-slate-800/50 p-4">
        <h2 class="mb-3 text-lg font-medium">Actions</h2>
        <div class="space-y-4">
          <div class="grid max-w-2xl grid-cols-2 gap-2 text-sm sm:grid-cols-3">
            <input v-model="pipelineForm.city_id" placeholder="city_id" class="rounded border border-slate-600 bg-slate-900 px-2 py-1" />
            <input v-model="pipelineForm.lang" placeholder="lang" class="rounded border border-slate-600 bg-slate-900 px-2 py-1" />
            <input v-model.number="pipelineForm.blocks_count" type="number" placeholder="blocks_count" class="rounded border border-slate-600 bg-slate-900 px-2 py-1" />
            <input v-model.number="pipelineForm.blocks_pages" type="number" placeholder="blocks_pages" class="rounded border border-slate-600 bg-slate-900 px-2 py-1" />
            <input v-model.number="pipelineForm.apartments_pages" type="number" placeholder="apartments_pages" class="rounded border border-slate-600 bg-slate-900 px-2 py-1" />
            <label class="flex items-center gap-1">
              <input v-model="pipelineForm.dispatch_details" type="checkbox" />
              dispatch_details
            </label>
            <input v-model.number="pipelineForm.detail_limit" type="number" placeholder="detail_limit" class="rounded border border-slate-600 bg-slate-900 px-2 py-1" />
          </div>
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              :disabled="pipelineLoading"
              class="rounded bg-emerald-700 px-4 py-2 text-sm font-medium hover:bg-emerald-600 disabled:opacity-50"
              @click="runPipelineAction"
            >
              Run pipeline
            </button>
            <button
              type="button"
              class="rounded bg-slate-700 px-3 py-1 text-sm hover:bg-slate-600"
              @click="copyCurl(`${ADMIN_BASE}/pipeline/run`, 'POST', JSON.stringify(pipelineForm))"
            >
              Copy curl
            </button>
          </div>
          <div v-if="pipelineResult" class="text-sm text-slate-300">
            queued: {{ pipelineResult.queued }}, run_id: {{ pipelineResult.run_id }}
          </div>
          <div class="flex flex-wrap items-center gap-2 border-t border-slate-700 pt-3">
            <input v-model="refreshBlockId" placeholder="block_id" class="w-40 rounded border border-slate-600 bg-slate-900 px-2 py-1 text-sm" />
            <button type="button" class="rounded bg-slate-600 px-3 py-1 text-sm hover:bg-slate-500" @click="doRefreshBlock">Refresh block (ta-ui)</button>
            <input v-model="refreshApartmentId" placeholder="apartment_id" class="w-40 rounded border border-slate-600 bg-slate-900 px-2 py-1 text-sm" />
            <button type="button" class="rounded bg-slate-600 px-3 py-1 text-sm hover:bg-slate-500" @click="doRefreshApartment">Refresh apartment (ta-ui)</button>
          </div>
        </div>
      </section>

      <!-- SyncRunsTable -->
      <section class="rounded-lg border border-slate-700 bg-slate-800/50 p-4">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-lg font-medium">Sync Runs</h2>
          <div class="flex flex-wrap gap-2">
            <input v-model="syncFilters.scope" placeholder="scope" class="w-24 rounded border border-slate-600 bg-slate-900 px-2 py-1 text-sm" />
            <input v-model="syncFilters.status" placeholder="status" class="w-24 rounded border border-slate-600 bg-slate-900 px-2 py-1 text-sm" />
            <input v-model.number="syncFilters.since_hours" type="number" class="w-20 rounded border border-slate-600 bg-slate-900 px-2 py-1 text-sm" />
            <input v-model.number="syncFilters.limit" type="number" class="w-16 rounded border border-slate-600 bg-slate-900 px-2 py-1 text-sm" />
            <button type="button" class="rounded bg-slate-600 px-2 py-1 text-sm hover:bg-slate-500" @click="loadSyncRuns">Apply</button>
            <button type="button" class="rounded bg-slate-700 px-2 py-1 text-sm hover:bg-slate-600" @click="copyCurl(`${ADMIN_BASE}/sync-runs?since_hours=24&limit=50`, 'GET')">Copy curl</button>
          </div>
        </div>
        <div v-if="syncRunsLoading" class="text-slate-400">Loading…</div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-left text-sm">
            <thead>
              <tr class="border-b border-slate-600">
                <th class="p-2">id</th>
                <th class="p-2">scope</th>
                <th class="p-2">status</th>
                <th class="p-2">fetched</th>
                <th class="p-2">saved</th>
                <th class="p-2">error_message</th>
                <th class="p-2">finished_at</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="r in syncRuns"
                :key="r.id"
                :class="r.status === 'failed' || r.status === 'error' ? 'bg-red-900/20' : ''"
                class="border-b border-slate-700"
              >
                <td class="p-2">{{ r.id }}</td>
                <td class="p-2">{{ r.scope }}</td>
                <td class="p-2">{{ r.status }}</td>
                <td class="p-2">{{ r.items_fetched }}</td>
                <td class="p-2">{{ r.items_saved }}</td>
                <td class="max-w-xs truncate p-2">{{ r.error_message ?? '—' }}</td>
                <td class="p-2">{{ r.finished_at ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ContractChangesTable -->
      <section class="rounded-lg border border-slate-700 bg-slate-800/50 p-4">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-lg font-medium">Contract Changes</h2>
          <div class="flex flex-wrap gap-2">
            <input v-model="contractFilters.endpoint" placeholder="endpoint" class="w-40 rounded border border-slate-600 bg-slate-900 px-2 py-1 text-sm" />
            <input v-model.number="contractFilters.since_hours" type="number" class="w-20 rounded border border-slate-600 bg-slate-900 px-2 py-1 text-sm" />
            <input v-model.number="contractFilters.limit" type="number" class="w-16 rounded border border-slate-600 bg-slate-900 px-2 py-1 text-sm" />
            <button type="button" class="rounded bg-slate-600 px-2 py-1 text-sm hover:bg-slate-500" @click="loadContractChanges">Apply</button>
            <button type="button" class="rounded bg-slate-700 px-2 py-1 text-sm hover:bg-slate-600" @click="copyCurl(`${ADMIN_BASE}/contract-changes?since_hours=168&limit=100`, 'GET')">Copy curl</button>
          </div>
        </div>
        <div v-if="contractChangesLoading" class="text-slate-400">Loading…</div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-left text-sm">
            <thead>
              <tr class="border-b border-slate-600">
                <th class="p-2">endpoint</th>
                <th class="p-2">city_id</th>
                <th class="p-2">lang</th>
                <th class="p-2">old_hash</th>
                <th class="p-2">new_hash</th>
                <th class="p-2">detected_at</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(c, i) in contractChanges" :key="i" class="border-b border-slate-700">
                <td class="max-w-[200px] truncate p-2">{{ c.endpoint }}</td>
                <td class="p-2">{{ c.city_id ?? '—' }}</td>
                <td class="p-2">{{ c.lang ?? '—' }}</td>
                <td class="max-w-[80px] truncate p-2">{{ c.old_hash }}</td>
                <td class="max-w-[80px] truncate p-2">{{ c.new_hash }}</td>
                <td class="p-2">{{ c.detected_at ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- QualityFailsTable -->
      <section class="rounded-lg border border-slate-700 bg-slate-800/50 p-4">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-lg font-medium">Quality (fail)</h2>
          <div class="flex flex-wrap gap-2">
            <input v-model="qualityFilters.scope" placeholder="scope" class="w-24 rounded border border-slate-600 bg-slate-900 px-2 py-1 text-sm" />
            <input v-model.number="qualityFilters.since_hours" type="number" class="w-20 rounded border border-slate-600 bg-slate-900 px-2 py-1 text-sm" />
            <input v-model.number="qualityFilters.limit" type="number" class="w-16 rounded border border-slate-600 bg-slate-900 px-2 py-1 text-sm" />
            <button type="button" class="rounded bg-slate-600 px-2 py-1 text-sm hover:bg-slate-500" @click="loadQualityChecks">Apply</button>
            <button type="button" class="rounded bg-slate-700 px-2 py-1 text-sm hover:bg-slate-600" @click="copyCurl(`${ADMIN_BASE}/quality-checks?status=fail&since_hours=24&limit=200`, 'GET')">Copy curl</button>
          </div>
        </div>
        <div v-if="qualityChecksLoading" class="text-slate-400">Loading…</div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-left text-sm">
            <thead>
              <tr class="border-b border-slate-600">
                <th class="p-2">scope</th>
                <th class="p-2">entity_id</th>
                <th class="p-2">check_name</th>
                <th class="p-2">status</th>
                <th class="p-2">message</th>
                <th class="p-2">created_at</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(q, i) in qualityChecks" :key="i" class="border-b border-slate-700">
                <td class="p-2">{{ q.scope }}</td>
                <td class="p-2">{{ q.entity_id ?? '—' }}</td>
                <td class="p-2">{{ q.check_name }}</td>
                <td class="p-2">{{ q.status }}</td>
                <td class="max-w-xs truncate p-2">{{ q.message }}</td>
                <td class="p-2">{{ q.created_at ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>
