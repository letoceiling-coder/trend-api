import axios from 'axios';

const baseURL =
  (import.meta.env.VITE_API_URL as string) ||
  (typeof window !== 'undefined' ? '' : 'http://localhost:3000');

export const taApi = axios.create({
  baseURL: `${baseURL}/api/ta`,
  headers: { 'Content-Type': 'application/json' },
});

/** For refresh from frontend (no internal key). Rate-limited on backend. */
export const taUiApi = axios.create({
  baseURL: `${baseURL}/api/ta-ui`,
  headers: { 'Content-Type': 'application/json' },
});

export interface PaginationMeta {
  total?: number;
  count?: number;
  offset?: number;
}

export interface ApiResponse<T> {
  data: T;
  meta?: { pagination?: PaginationMeta };
}

export interface BlockItem {
  id: string;
  block_id: string;
  title?: string;
  city_id?: string;
  lang?: string;
  min_price?: number;
  max_price?: number;
  deadline?: string;
  developer_name?: string;
  location?: string;
  image_url?: string | null;
  fetched_at?: string;
}

/** Block detail from ta_block_details (unified, advantages, nearby_places, bank, geo_buildings, apartments_min_price) */
export interface BlockDetailPayload {
  block_id?: string;
  city_id?: string;
  lang?: string;
  unified_payload?: Record<string, unknown>;
  advantages_payload?: unknown;
  nearby_places_payload?: unknown;
  bank_payload?: unknown;
  geo_buildings_payload?: unknown;
  apartments_min_price_payload?: unknown;
  fetched_at?: string;
}

export interface BlockItemWithDetail extends BlockItem {
  detail?: BlockDetailPayload;
}

export async function getBlocks(params?: {
  city_id?: string;
  lang?: string;
  count?: number;
  offset?: number;
  sort?: string;
  sort_order?: 'asc' | 'desc';
  show_type?: string;
}) {
  const { data } = await taApi.get<ApiResponse<BlockItem[]>>('/blocks', {
    params,
  });
  return data;
}

export async function getBlock(blockId: string) {
  const { data } = await taApi.get<ApiResponse<BlockItem>>(
    `/blocks/${encodeURIComponent(blockId)}`
  );
  return data;
}

/** GET /api/ta/blocks/{id} — block with optional detail from ta_block_details */
export async function getBlockDetail(blockId: string) {
  const { data } = await taApi.get<ApiResponse<BlockItemWithDetail>>(
    `/blocks/${encodeURIComponent(blockId)}`
  );
  return data;
}

/** POST /api/ta-ui/blocks/{id}/refresh — queue block detail sync (no internal key) */
export async function refreshBlockDetail(blockId: string) {
  const { data } = await taUiApi.post<ApiResponse<{ queued: boolean }>>(
    `blocks/${encodeURIComponent(blockId)}/refresh`
  );
  return data;
}

/** POST /api/ta-ui/apartments/{id}/refresh — queue apartment detail sync (no internal key) */
export async function refreshApartmentDetail(apartmentId: string) {
  const { data } = await taUiApi.post<ApiResponse<{ queued: boolean }>>(
    `apartments/${encodeURIComponent(apartmentId)}/refresh`
  );
  return data;
}

export interface ApartmentItem {
  id: number;
  apartment_id: string;
  block_id?: string;
  title?: string;
  rooms?: number;
  area_total?: number;
  floor?: number;
  price?: number;
  status?: string;
  city_id?: string;
  lang?: string;
  fetched_at?: string;
}

/** Apartment detail from ta_apartment_details (unified, prices_totals, prices_graph) */
export interface ApartmentDetailPayload {
  apartment_id?: string;
  city_id?: string;
  lang?: string;
  unified_payload?: Record<string, unknown>;
  prices_totals_payload?: unknown;
  prices_graph_payload?: unknown;
  fetched_at?: string;
}

export interface ApartmentItemWithDetail extends ApartmentItem {
  detail?: ApartmentDetailPayload;
}

export async function getApartments(params?: {
  city_id?: string;
  lang?: string;
  block_id?: string;
  count?: number;
  offset?: number;
  sort?: string;
  sort_order?: 'asc' | 'desc';
}) {
  const { data } = await taApi.get<ApiResponse<ApartmentItem[]>>(
    '/apartments',
    { params }
  );
  return data;
}

export async function getApartment(apartmentId: string) {
  const { data } = await taApi.get<ApiResponse<ApartmentItem>>(
    `/apartments/${encodeURIComponent(apartmentId)}`
  );
  return data;
}

/** GET /api/ta/apartments/{id} — apartment with optional detail from ta_apartment_details */
export async function getApartmentDetail(apartmentId: string) {
  const { data } = await taApi.get<ApiResponse<ApartmentItemWithDetail>>(
    `/apartments/${encodeURIComponent(apartmentId)}`
  );
  return data;
}

export async function getDirectories(type?: string) {
  const { data } = await taApi.get<ApiResponse<unknown[]>>('/directories', {
    params: type ? { type } : undefined,
  });
  return data;
}

export async function getUnitMeasurements() {
  const { data } = await taApi.get<ApiResponse<unknown[]>>('/unit-measurements');
  return data;
}
