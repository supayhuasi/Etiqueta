import axios, { AxiosRequestConfig } from 'axios';

export function apiBaseUrl(serverUrl: string): string {
  return serverUrl.replace(/\/+$/, '') + '/ecommerce/api/nube';
}

export class ApiError extends Error {}

export async function apiRequest<T>(
  serverUrl: string,
  token: string | null,
  path: string,
  config: AxiosRequestConfig = {}
): Promise<T> {
  const headers: Record<string, string> = { ...(config.headers as Record<string, string> | undefined) };
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  try {
    const response = await axios.request<T & { success: boolean; message?: string }>({
      url: `${apiBaseUrl(serverUrl)}/${path}`,
      ...config,
      headers,
    });

    if (response.data && response.data.success === false) {
      throw new ApiError(response.data.message || 'Error desconocido');
    }

    return response.data;
  } catch (error) {
    if (error instanceof ApiError) {
      throw error;
    }
    if (axios.isAxiosError(error)) {
      const message = (error.response?.data as { message?: string } | undefined)?.message;
      throw new ApiError(message || error.message);
    }
    throw error;
  }
}
