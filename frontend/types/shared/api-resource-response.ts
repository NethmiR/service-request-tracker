// Envelope of a single resource returned by a Laravel API Resource.
// The resource itself sits under the data key.

export interface ApiResourceResponse<Resource> {
  data: Resource;
}
