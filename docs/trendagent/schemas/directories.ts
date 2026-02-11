// TypeScript-like описания справочников и фильтров.
// Источник: docs/trendagent/04-filters-and-directories.md

export interface DirectoryItem {
  id: number | string;
  code?: string | null;
  name: string;
}

export interface ApartmentDirectories {
  rooms: DirectoryItem[];
  balcony_types: DirectoryItem[];
  banks: DirectoryItem[];
  building_types: DirectoryItem[];
  cardinals: DirectoryItem[];
  contracts: DirectoryItem[];
  deadlines: DirectoryItem[];
  deadline_keys: DirectoryItem[];
  delta_prices: DirectoryItem[];
  region_registrations: DirectoryItem[];
  subway_distances: DirectoryItem[];
  elevator_types: DirectoryItem[];
  escrow_banks: DirectoryItem[];
  finishings: DirectoryItem[];
  without_initial_fee: DirectoryItem[];
  installment_tags: DirectoryItem[];
  level_types: DirectoryItem[];
  locations: DirectoryItem[];
  mortgage_types: DirectoryItem[];
  nearby_place_types: DirectoryItem[];
  parking_types: DirectoryItem[];
  payment_types: DirectoryItem[];
  premise_types: DirectoryItem[];
  regions: DirectoryItem[];
  sales_start: DirectoryItem[];
  subways: DirectoryItem[];
  view_places: DirectoryItem[];
  window_views: DirectoryItem[];
  window_types: DirectoryItem[];
}

export interface ParkingsEnums {
  contract_types: DirectoryItem[];
  parking_types: DirectoryItem[];
  payment_types: DirectoryItem[];
  place_types: DirectoryItem[];
}

export interface ParkingsDirectories {
  deadlines: DirectoryItem[];
  sales_start: DirectoryItem[];
}

export interface CommerceFilters {
  buildings?: DirectoryItem[];
  window_view_types?: DirectoryItem[];
  cardinals?: DirectoryItem[];
  property_types?: DirectoryItem[];
  building_types?: DirectoryItem[];
  start_sales?: DirectoryItem[];
  deadline_keys?: DirectoryItem[];
  entrances?: DirectoryItem[];
  finishing_types?: DirectoryItem[];
  bathroom_types?: DirectoryItem[];
  balconies_types?: DirectoryItem[];
  window_types?: DirectoryItem[];
  piping_types?: DirectoryItem[];
  levels?: DirectoryItem[];
  ventilation_types?: DirectoryItem[];
  payment_types?: DirectoryItem[];
  banks?: DirectoryItem[];
  contract_types?: DirectoryItem[];
  buyer_requirements?: DirectoryItem[];
  deadlines?: DirectoryItem[];
  purposes?: DirectoryItem[];
  ceiling_heights?: DirectoryItem[];
  level_types?: DirectoryItem[];
}

