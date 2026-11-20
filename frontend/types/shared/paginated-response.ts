// Envelope of a paginated Laravel API Resource collection.
// Field names mirror the Laravel 13 wire format, where the ellipsis link in meta carries no page number.

export interface PaginationLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

export interface PaginationMetaLink {
  url: string | null;
  label: string;
  page?: number | null;
  active: boolean;
}

export interface PaginationMeta {
  current_page: number;
  from: number | null;
  last_page: number;
  path: string;
  per_page: number;
  to: number | null;
  total: number;
  links: PaginationMetaLink[];
}

export interface PaginatedResponse<Resource> {
  data: Resource[];
  links: PaginationLinks;
  meta: PaginationMeta;
}
