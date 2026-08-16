import { isAxiosError } from 'axios';

interface ApiErrorPayload {
    error?: string;
    message?: string;
    errors?: Record<string, string | string[]>;
}

const messageFromPayload = (payload: ApiErrorPayload): string | null => {
    if (typeof payload.message === 'string' && payload.message.trim()) {
        return payload.message;
    }

    if (typeof payload.error === 'string' && payload.error.trim()) {
        return payload.error;
    }

    const firstError = payload.errors ? Object.values(payload.errors)[0] : null;
    if (Array.isArray(firstError) && firstError[0]) {
        return String(firstError[0]);
    }

    return typeof firstError === 'string' && firstError.trim() ? firstError : null;
};

export const getApiErrorMessage = async (error: unknown, fallback: string): Promise<string> => {
    if (!isAxiosError(error)) {
        return error instanceof Error && error.message ? error.message : fallback;
    }

    const data: unknown = error.response?.data;

    if (data instanceof Blob) {
        try {
            const text = await data.text();
            const payload = JSON.parse(text) as ApiErrorPayload;
            return messageFromPayload(payload) ?? fallback;
        } catch {
            return fallback;
        }
    }

    if (data && typeof data === 'object') {
        return messageFromPayload(data as ApiErrorPayload) ?? fallback;
    }

    return fallback;
};
