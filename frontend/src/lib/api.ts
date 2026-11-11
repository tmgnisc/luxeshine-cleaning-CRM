import axios, { AxiosError, AxiosRequestConfig } from "axios";

export type ValidationErrors = Record<string, string[]>;

export class ApiError extends Error {
  status: number;
  errors?: ValidationErrors;

  constructor(message: string, status: number, errors?: ValidationErrors) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
  }
}

export const http = axios.create({
  baseURL: "/bff",
  headers: { Accept: "application/json" },
});

http.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ message?: string; errors?: ValidationErrors }>) => {
    if (error.response) {
      const { status, data } = error.response;

      // Session expired / unauthenticated — bounce to login (browser only).
      if (status === 401 && typeof window !== "undefined") {
        const path = window.location.pathname;
        if (!path.startsWith("/login") && !path.startsWith("/register")) {
          window.location.href = `/login?expired=1`;
        }
      }

      throw new ApiError(
        data?.message ?? "Something went wrong. Please try again.",
        status,
        data?.errors,
      );
    }

    throw new ApiError("Unable to reach the server. Check your connection.", 0);
  },
);

/** Envelope-aware GET returning the `data` payload. */
export async function apiGet<T>(url: string, config?: AxiosRequestConfig): Promise<T> {
  const res = await http.get(url, config);
  return (res.data?.data ?? res.data) as T;
}

/** GET returning the full envelope (data + meta) for paginated lists. */
export async function apiGetPage<T>(
  url: string,
  config?: AxiosRequestConfig,
): Promise<{ data: T[]; meta: PaginationMeta }> {
  const res = await http.get(url, config);
  return { data: res.data?.data ?? [], meta: res.data?.meta };
}

export async function apiSend<T>(
  method: "post" | "put" | "patch" | "delete",
  url: string,
  body?: unknown,
  config?: AxiosRequestConfig,
): Promise<T> {
  const res = await http.request({ method, url, data: body, ...config });
  return (res.data?.data ?? res.data) as T;
}

export type PaginationMeta = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number | null;
  to: number | null;
};
