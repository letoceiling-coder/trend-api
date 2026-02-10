// TypeScript-like описания основных сущностей TrendAgent.
// Источник: docs/trendagent/05-domain-model.md

export interface Block {
  id: string;
  guid: string; // slug в URL /object/{slug}
  name: string;
  type: string; // жилой, загородный, коммерческий, парковочный и т.п.
  city_id: string;
  location?: {
    lat: number;
    lng: number;
    address?: string;
  };
  developer?: {
    id: string;
    name: string;
  };
  deadlines?: string; // обобщённое представление сроков сдачи
  class?: string; // комфорт, бизнес и т.д.
  attributes?: Record<string, unknown>;
}

export interface Apartment {
  id: string;
  block_id: string;
  number: string;
  rooms: number;
  area_total: number;
  area_kitchen?: number;
  area_living?: number;
  price: number;
  floor: number;
  floors_total: number;
  balcony_type?: string;
  finishing?: string;
  window_type?: string;
  view?: string;
  status: 'free' | 'reserved' | 'sold' | string;
  contract_type?: string;
  payment_types?: string[];
  mortgage_types?: string[];
}

export interface CommercePremise {
  id: string;
  block_id: string;
  number: string;
  purpose: string;
  area_total: number;
  price: number;
  floor: number;
  levels?: number;
  ceiling_height?: number;
  entrances?: number;
  ventilation_type?: string;
  piping_type?: string;
  finishing_type?: string;
  window_type?: string;
  view?: string;
  contract_type?: string;
  payment_types?: string[];
  buyer_requirements?: string[];
  status: 'free' | 'rented' | 'sold' | string;
}

export interface ParkingBlock {
  id: string;
  block_id: string; // связанный ЖК
  parking_type: string;
  floors_total?: number;
  capacity?: number;
}

export interface ParkingPlace {
  id: string;
  parking_block_id: string;
  block_id: string;
  number: string;
  area?: number;
  floor?: number;
  floors_total?: number;
  place_type?: string;
  has_storage_box?: boolean;
  has_lift?: boolean;
  has_ev_socket?: boolean;
  price: number;
  price_per_m2?: number;
  status: 'free' | 'reserved' | 'sold' | string;
  contract_type?: string;
  payment_types?: string[];
}

export interface Village {
  id: string;
  name: string;
  city_id: string;
  location?: string; // текстовое описание локации/шоссе
  developer?: {
    id: string;
    name: string;
  };
  purpose_types?: string[];
  infrastructure?: string[];
  communications?: string[]; // вода, канализация, электричество, газ
  contract_type?: string;
  payment_types?: string[];
}

export interface Plot {
  id: string;
  village_id: string;
  cadastral_number?: string;
  area_sotka: number;
  price: number;
  land_purpose?: string;
  water?: string;
  sewerage?: string;
  electricity?: string;
  gas?: string;
  road_type?: string;
  status: 'free' | 'reserved' | 'sold' | string;
  contract_type?: string;
  payment_types?: string[];
  escrow?: boolean;
}

export interface HouseProject {
  id: string;
  name: string;
  area_total?: number;
  floors?: number;
  bedrooms?: number;
  price_from?: number;
  description?: string;
}

