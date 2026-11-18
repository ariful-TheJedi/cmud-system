import axios, { type AxiosError, type AxiosRequestConfig } from 'axios';
import { useCallback } from 'react';

export type ApiValidationErrors = Record<string, string[]>;

export type ApiRequestConfig = Omit<AxiosRequestConfig, 'baseURL' | 'url'>;

export type ApiResponse<T> = {
    data: T;
    status: number;
};

/** Carries the HTTP status and Laravel validation errors through failed requests. */
export class ApiRequestError extends Error {
    constructor(
        message: string,
        public readonly status: number | null,
        public readonly errors?: ApiValidationErrors,
    ) {
        super(message);
        this.name = 'ApiRequestError';
    }
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function isValidationErrors(value: unknown): value is ApiValidationErrors {
    return (
        isRecord(value) &&
        Object.values(value).every(
            (messages) =>
                Array.isArray(messages) &&
                messages.every((message) => typeof message === 'string'),
        )
    );
}

function toApiRequestError(error: AxiosError<unknown>): ApiRequestError {
    const status = error.response?.status ?? null;
    const payload = error.response?.data;
    const message =
        isRecord(payload) && typeof payload.message === 'string'
            ? payload.message
            : status === null
              ? error.message || 'Unable to reach the API.'
              : `API request failed with status ${status}.`;
    const errors =
        isRecord(payload) && isValidationErrors(payload.errors)
            ? payload.errors
            : undefined;

    return new ApiRequestError(message, status, errors);
}

const configuredPrefix = import.meta.env.VITE_API_PREFIX?.trim().replace(
    /\/+$/,
    '',
);
const apiClient = axios.create({
    baseURL: configuredPrefix || '/api/v1',
    headers: { Accept: 'application/json' },
    withCredentials: true,
});

/** Sends requests to endpoints relative to the configured API prefix. */
export function useApi() {
    const request = useCallback(async function sendRequest<T>(
        endpoint: string,
        config: ApiRequestConfig = {},
    ): Promise<ApiResponse<T>> {
        try {
            const response = await apiClient.request<T>({
                ...config,
                url: endpoint,
            });

            return { data: response.data, status: response.status };
        } catch (error) {
            if (axios.isAxiosError(error)) {
                throw toApiRequestError(error);
            }

            throw error;
        }
    }, []);

    return { request };
}
