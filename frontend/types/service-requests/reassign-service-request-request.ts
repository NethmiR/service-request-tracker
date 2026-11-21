// Body of the call that reassigns a service request to another technician or admin.
// Only the current assignee can send it, and the remark is mandatory.

export interface ReassignServiceRequestRequest {
  assignee_id: number;
  remark: string;
}
