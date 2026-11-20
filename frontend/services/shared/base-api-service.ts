// Base class for every feature service, wrapping the shared HTTP client.
// Components call feature services and never import Axios directly.

import type { AxiosInstance, AxiosRequestConfig } from "axios";
import { httpClient } from "@/lib/http/http-client";

export abstract class BaseApiService {
  constructor(protected readonly client: AxiosInstance = httpClient) {}

  protected async get<ResponseBody>(
    url: string,
    config?: AxiosRequestConfig,
  ): Promise<ResponseBody> {
    const response = await this.client.get<ResponseBody>(url, config);
    return response.data;
  }

  protected async post<ResponseBody, RequestBody = undefined>(
    url: string,
    body?: RequestBody,
    config?: AxiosRequestConfig<RequestBody>,
  ): Promise<ResponseBody> {
    const response = await this.client.post<ResponseBody>(url, body, config);
    return response.data;
  }

  protected async patch<ResponseBody, RequestBody = undefined>(
    url: string,
    body?: RequestBody,
    config?: AxiosRequestConfig<RequestBody>,
  ): Promise<ResponseBody> {
    const response = await this.client.patch<ResponseBody>(url, body, config);
    return response.data;
  }

  protected async delete<ResponseBody>(
    url: string,
    config?: AxiosRequestConfig,
  ): Promise<ResponseBody> {
    const response = await this.client.delete<ResponseBody>(url, config);
    return response.data;
  }
}
