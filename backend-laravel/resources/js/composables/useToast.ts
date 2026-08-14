export type ToastType = 'success' | 'error' | 'info';

const notify = (type: ToastType, message: string): void => {
    window.dispatchEvent(
        new CustomEvent('app:toast', {
            detail: { type, message },
        }),
    );
};

export const toast = {
    success: (message: string) => notify('success', message),
    error: (message: string) => notify('error', message),
    info: (message: string) => notify('info', message),
};

export const useToast = () => toast;
