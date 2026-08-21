<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { DocumentEditor, type IConfig } from '@onlyoffice/document-editor-vue';
import type {
    OnlyOfficeDocumentType,
    OnlyOfficeEditorSession,
    OnlyOfficeMode,
} from '@/api';
import Button from '@/components/UI/Button.vue';
import LoadingSpinner from '@/components/UI/LoadingSpinner.vue';
import { useOnlyOfficeEditor } from '@/composables/useOnlyOfficeEditor';

interface OnlyOfficeEvent {
    data?: unknown;
}

const props = withDefaults(
    defineProps<{
        documentType: OnlyOfficeDocumentType;
        documentId: number;
        mode?: OnlyOfficeMode;
    }>(),
    { mode: 'edit' },
);

const emit = defineEmits<{
    ready: [];
    error: [message: string];
    dirtyChange: [dirty: boolean];
    sessionLoaded: [session: OnlyOfficeEditorSession];
}>();

const editorReady = ref(false);
const runtimeError = ref<string | null>(null);
const { session, loading, error, reload, startHeartbeat, stopHeartbeat } = useOnlyOfficeEditor({
    documentType: () => props.documentType,
    documentId: () => props.documentId,
    mode: () => props.mode,
});

const editorId = computed(() => `onlyoffice-${props.documentType}-${props.documentId}`);

const callOriginalEvent = (
    name: keyof NonNullable<IConfig['events']>,
    event: object,
): void => {
    const handler = session.value?.config.events?.[name];

    if (typeof handler === 'function') {
        handler(event);
    }
};

const handleAppReady = (event: object = {}): void => {
    callOriginalEvent('onAppReady', event);
    editorReady.value = true;
    emit('ready');
};

const handleDocumentReady = (event: object = {}): void => {
    callOriginalEvent('onDocumentReady', event);
    editorReady.value = true;
    startHeartbeat();
    emit('ready');
};

const handleDocumentStateChange = (event: OnlyOfficeEvent = {}): void => {
    callOriginalEvent('onDocumentStateChange', event);
    emit('dirtyChange', event.data === true);
};

const handleEditorError = (event: OnlyOfficeEvent = {}): void => {
    callOriginalEvent('onError', event);

    const data = event.data;
    const description =
        data && typeof data === 'object' && 'errorDescription' in data
            ? String((data as { errorDescription?: unknown }).errorDescription ?? '')
            : '';
    const message = description || 'ONLYOFFICE informó un error al abrir el documento.';
    stopHeartbeat();
    runtimeError.value = message;
    emit('error', message);
};

const handleLoadComponentError = (_code: number, description: string): void => {
    const message = description || 'No se pudo conectar con el servidor de ONLYOFFICE.';
    stopHeartbeat();
    runtimeError.value = message;
    emit('error', message);
};

const editorConfig = computed<IConfig | null>(() => {
    if (!session.value) {
        return null;
    }

    return {
        ...session.value.config,
        height: '100%',
        width: '100%',
        events: {
            ...session.value.config.events,
            onAppReady: handleAppReady,
            onDocumentReady: handleDocumentReady,
            onDocumentStateChange: handleDocumentStateChange,
            onError: handleEditorError,
        },
    };
});

const retry = async (): Promise<void> => {
    stopHeartbeat();
    runtimeError.value = null;
    editorReady.value = false;
    emit('dirtyChange', false);
    await reload();
};

watch(session, (value) => {
    runtimeError.value = null;
    editorReady.value = false;
    emit('dirtyChange', false);

    if (value) {
        emit('sessionLoaded', value);
    }
});

watch(error, (message) => {
    if (message) {
        emit('error', message);
    }
});
</script>

<template>
    <div class="relative flex min-h-0 flex-1 bg-white">
        <div
            v-if="loading"
            class="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center"
            role="status"
        >
            <LoadingSpinner size="lg" />
            <div>
                <p class="text-lg font-semibold text-gray-900">Preparando el editor</p>
                <p class="mt-1 text-base text-gray-600">El documento se abrirá en unos segundos.</p>
            </div>
        </div>

        <div
            v-else-if="error || runtimeError"
            class="flex flex-1 flex-col items-center justify-center px-6 text-center"
            role="alert"
        >
            <div class="max-w-xl rounded-xl border border-gray-300 bg-white p-8 shadow-sm">
                <h2 class="text-xl font-bold text-gray-900">No se pudo abrir el editor</h2>
                <p class="mt-3 text-base leading-6 text-gray-700">
                    {{ runtimeError || error }}
                </p>
                <Button size="lg" class="mt-6 min-w-48" @click="retry">Intentar nuevamente</Button>
            </div>
        </div>

        <template v-else-if="session && editorConfig">
            <div
                v-if="!editorReady"
                class="absolute inset-0 z-10 flex items-center justify-center bg-white"
                role="status"
            >
                <div class="text-center">
                    <LoadingSpinner size="lg" />
                    <p class="mt-4 text-lg font-medium text-gray-800">Cargando el documento...</p>
                </div>
            </div>

            <DocumentEditor
                :id="editorId"
                class="h-full min-h-0 w-full flex-1"
                :document-server-url="session.documentServerUrl"
                :config="editorConfig"
                :shardkey="true"
                :on-load-component-error="handleLoadComponentError"
            />
        </template>
    </div>
</template>
