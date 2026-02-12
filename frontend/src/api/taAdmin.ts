import axios, { type AxiosInstance } from 'axios';

const baseURL =
  (import.meta.env.VITE_API_URL as string) ||
  (typeof window !== 'undefined' ? '' : 'http://localhost:3000');

const ADMIN_STORAGE_KEY = 'TA_ADMIN_KEY';

/**
 * Get admin key for X-Internal-Key header.
 * - dev: from sessionStorage(TA_ADMIN_KEY) if set
 * - prod: from import.meta.env.VITE_TA_ADMIN_KEY if set
 * Throws if key is not configured (do not log or expose the key).
 */
export function getAdminKey(): string {
  const isDev = import.meta.env.DEV;
  if (isDev && typeof window !== 'undefined') {
    const stored = sessionStorage.getItem(ADMIN_STORAGE_KEY);
    if (stored) return stored;
  }
  const envKey = import.meta.env.VITE_TA_ADMIN_KEY as string | undefined;
  if (envKey && envKey.trim() !== '') return envKey.trim();
  throw new Error('Admin key not configured');
}

/**
 * Set admin key in sessionStorage (dev only). Do not use in prod.
 */
export function setAdminKey(key: string): void {
  if (typeof window === 'undefined') return;
  sessionStorage.setItem(ADMIN_STORAGE_KEY, key.trim());
}

/**
 * Clear admin key from sessionStorage.
 */
export function clearAdminKey(): void {
  if (typeof window === 'undefined') return;
  sessionStorage.removeItem(ADMIN_STORAGE_KEY);
}

/**
 * Check if admin key is available (for UI gate).
 */
export function hasAdminKey(): boolean {
  try {
    getAdminKey();
    return true;
  } catch {
    return false;
  }
}

function createClient(): AxiosInstance {
  const key = getAdminKey();
  return axios.create({
    baseURL: `${baseURL}/api/ta/admin`,
    headers: {
      'Content-Type': 'application/json',
      'X-Internal-Key': key,
    },
  });
}

export interface AdminResponse<T> {
  data: T;
  meta?: Record<string, unknown>;
}

export interface HealthSyncScope {
  last_success_at: string | null;
}

export interface CoverageData {
  blocks_total: number;
  blocks_with_detail_fresh: number;
  blocks_without_detail: number;
  apartments_total: number;
  apartments_with_detail_fresh: number;
  apartments_without_detail: number;
}

export interface HealthData {
  sync: {
    blocks: HealthSyncScope;
    apartments: HealthSyncScope;
    block_detail: HealthSyncScope;
    apartment_detail: HealthSyncScope;
  };
  contract_changes_last_24h_count: number;
  quality_fail_last_24h_count: number;
  queue: { connection: string; queue_name: string };
  coverage?: CoverageData;
}

export interface SyncRunItem {
  id: number;
  scope: string;
  status: string;
  items_fetched: number;
  items_saved: number;
  error_message: string | null;
  created_at: string | null;
  finished_at: string | null;
}

export interface SyncRunsParams {
  scope?: string;
  status?: string;
  since_hours?: number;
  limit?: number;
}

export interface ContractChangeItem {
  endpoint: string;
  city_id: string | null;
  lang: string | null;
  old_hash: string;
  new_hash: string;
  old_top_keys: string[] | null;
  new_top_keys: string[] | null;
  detected_at: string | null;
}

export interface ContractChangesParams {
  endpoint?: string;
  since_hours?: number;
  limit?: number;
}

export interface QualityCheckItem {
  scope: string;
  entity_id: string | null;
  check_name: string;
  status: string;
  message: string;
  created_at: string | null;
}

export interface QualityChecksParams {
  scope?: string;
  status?: string;
  since_hours?: number;
  limit?: number;
}

export interface PipelinePayload {
  city_id?: string;
  lang?: string;
  blocks_count?: number;
  blocks_pages?: number;
  apartments_pages?: number;
  dispatch_details?: boolean;
  detail_limit?: number;
}

export interface PipelineResult {
  queued: boolean;
  run_id: string;
}

export async function getHealth(): Promise<AdminResponse<HealthData>> {
  const client = createClient();
  const { data } = await client.get<AdminResponse<HealthData>>('/health');
  return data;
}

export async function getCoverage(): Promise<AdminResponse<CoverageData>> {
  const client = createClient();
  const { data } = await client.get<AdminResponse<CoverageData>>('/coverage');
  return data;
}

export async function getSyncRuns(
  params?: SyncRunsParams
): Promise<AdminResponse<SyncRunItem[]>> {
  const client = createClient();
  const { data } = await client.get<AdminResponse<SyncRunItem[]>>('/sync-runs', {
    params,
  });
  return data;
}

export async function getContractChanges(
  params?: ContractChangesParams
): Promise<AdminResponse<ContractChangeItem[]>> {
  const client = createClient();
  const { data } = await client.get<AdminResponse<ContractChangeItem[]>>(
    '/contract-changes',
    { params }
  );
  return data;
}

export async function getQualityChecks(
  params?: QualityChecksParams
): Promise<AdminResponse<QualityCheckItem[]>> {
  const client = createClient();
  const { data } = await client.get<AdminResponse<QualityCheckItem[]>>(
    '/quality-checks',
    { params }
  );
  return data;
}

export async function runPipeline(
  payload: PipelinePayload
): Promise<AdminResponse<PipelineResult>> {
  const client = createClient();
  const { data } = await client.post<AdminResponse<PipelineResult>>(
    '/pipeline/run',
    payload
  );
  return data;
}

/** TA-UI refresh with X-Internal-Key. Use when hasAdminKey() is true. */
export async function refreshBlock(blockId: string): Promise<AdminResponse<{ queued: boolean }>> {
  const key = getAdminKey();
  const { data } = await axios.post<AdminResponse<{ queued: boolean }>>(
    `${baseURL}/api/ta-ui/blocks/${encodeURIComponent(blockId)}/refresh`,
    {},
    {
      headers: {
        'Content-Type': 'application/json',
        'X-Internal-Key': key,
      },
    }
  );
  return data;
}

/** TA-UI refresh with X-Internal-Key. Use when hasAdminKey() is true. */
export async function refreshApartment(apartmentId: string): Promise<AdminResponse<{ queued: boolean }>> {
  const key = getAdminKey();
  const { data } = await axios.post<AdminResponse<{ queued: boolean }>>(
    `${baseURL}/api/ta-ui/apartments/${encodeURIComponent(apartmentId)}/refresh`,
    {},
    {
      headers: {
        'Content-Type': 'application/json',
        'X-Internal-Key': key,
      },
    }
  );
  return data;
}
