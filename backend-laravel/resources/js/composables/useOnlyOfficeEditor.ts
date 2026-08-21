import {
    onScopeDispose,
    readonly,
    ref,
    shallowRef,
    toValue,
    watch,
    type MaybeRefOrGetter,
} from 'vue';
import { isAxiosError } from 'axios';
import {
    expedientesAPI,
    type OnlyOfficeDocumentType,
    type OnlyOfficeEditorSession,
    type OnlyOfficeMode,
} from '@/api';
import { getApiErrorMessage } from '@/utils/apiError';

interface UseOnlyOfficeEditorOptions {
    documentType: MaybeRefOrGetter<OnlyOfficeDocumentType>;
    documentId: MaybeRefOrGetter<number>;
    mode?: MaybeRefOrGetter<OnlyOfficeMode>;
}

export const useOnlyOfficeEditor = (options: UseOnlyOfficeEditorOptions) => {
    const session = shallowRef<OnlyOfficeEditorSession | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);
    let requestId = 0;
    let heartbeatTimer: ReturnType<typeof setInterval> | null = null;
    let heartbeatInFlight = false;
    let heartbeatCycle = 0;
    let activeHeartbeatToken: string | null = null;

    const stopHeartbeat = (): void => {
        heartbeatCycle += 1;
        activeHeartbeatToken = null;
        heartbeatInFlight = false;

        if (heartbeatTimer !== null) {
            clearInterval(heartbeatTimer);
            heartbeatTimer = null;
        }
    };

    const sendHeartbeat = async (cycle: number): Promise<void> => {
        const currentSession = session.value;
        const editingSession = currentSession?.session;

        if (
            cycle !== heartbeatCycle ||
            heartbeatInFlight ||
            !currentSession ||
            !editingSession ||
            currentSession.config.editorConfig?.mode !== 'edit'
        ) {
            return;
        }

        heartbeatInFlight = true;

        try {
            await expedientesAPI.heartbeatOnlyOfficeSession(
                currentSession.document.type,
                currentSession.document.id,
                editingSession.token,
            );
        } catch (heartbeatError) {
            if (
                cycle === heartbeatCycle &&
                isAxiosError(heartbeatError) &&
                heartbeatError.response &&
                [401, 403, 404, 409, 410, 422].includes(heartbeatError.response.status)
            ) {
                // The callback closed the editor, the source/version changed,
                // or the browser token expired. Do not revive that lease.
                stopHeartbeat();
            }
            // Network and transient server failures stay silent. A later tick
            // can renew the lease if connectivity returns.
        } finally {
            if (cycle === heartbeatCycle) {
                heartbeatInFlight = false;
            }
        }
    };

    const startHeartbeat = (): void => {
        const currentSession = session.value;
        const editingSession = currentSession?.session;

        if (
            !currentSession ||
            !editingSession ||
            currentSession.config.editorConfig?.mode !== 'edit'
        ) {
            stopHeartbeat();
            return;
        }

        if (heartbeatTimer !== null && activeHeartbeatToken === editingSession.token) {
            return;
        }

        stopHeartbeat();
        activeHeartbeatToken = editingSession.token;
        const cycle = heartbeatCycle;
        const intervalMilliseconds =
            Math.max(30, Math.min(300, editingSession.heartbeatIntervalSeconds)) * 1000;

        void sendHeartbeat(cycle);
        heartbeatTimer = setInterval(() => void sendHeartbeat(cycle), intervalMilliseconds);
    };

    const load = async (): Promise<void> => {
        const activeRequestId = ++requestId;
        const documentType = toValue(options.documentType);
        const documentId = Number(toValue(options.documentId));
        const mode = options.mode ? toValue(options.mode) : 'edit';

        stopHeartbeat();
        session.value = null;
        error.value = null;

        if (!Number.isInteger(documentId) || documentId < 1) {
            error.value = 'No se pudo identificar el documento que desea abrir.';
            return;
        }

        loading.value = true;

        try {
            const response = await expedientesAPI.getOnlyOfficeConfig(
                documentType,
                documentId,
                mode,
            );

            if (activeRequestId === requestId) {
                session.value = response;
            }
        } catch (loadError) {
            const message = await getApiErrorMessage(
                loadError,
                'No se pudo preparar el editor de documentos.',
            );

            if (activeRequestId === requestId) {
                error.value = message;
            }
        } finally {
            if (activeRequestId === requestId) {
                loading.value = false;
            }
        }
    };

    watch(
        [
            () => toValue(options.documentType),
            () => toValue(options.documentId),
            () => (options.mode ? toValue(options.mode) : 'edit'),
        ],
        () => void load(),
        { immediate: true },
    );

    onScopeDispose(() => {
        requestId += 1;
        stopHeartbeat();
    });

    return {
        session,
        loading: readonly(loading),
        error: readonly(error),
        reload: load,
        startHeartbeat,
        stopHeartbeat,
    };
};
