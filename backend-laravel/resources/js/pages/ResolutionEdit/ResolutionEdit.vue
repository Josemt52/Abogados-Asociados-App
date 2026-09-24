<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { JSONContent } from '@tiptap/core';
import { ArrowLeft, CircleCheck, FilePenLine, Save } from '@lucide/vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import {
    expedientesAPI,
    type ResolutionEditorHeaderData,
    type ResolutionEditorPayload,
} from '@/api';
import RichTextEditor from '@/components/RichTextEditor.vue';
import Button from '@/components/UI/Button.vue';
import { useToast } from '@/composables/useToast';
import { getApiErrorMessage } from '@/utils/apiError';

const emptyDocument = (): JSONContent => ({
    type: 'doc',
    content: [{ type: 'paragraph', attrs: { textAlign: 'left' } }],
});

const cloneContent = (content: JSONContent): JSONContent =>
    JSON.parse(JSON.stringify(content)) as JSONContent;

const emptyHeaderData = (): ResolutionEditorHeaderData => ({
    numero: '',
    materia: '',
    juzgado: '',
    especialista: '',
    tercero: '',
    demandado: '',
    demandante: '',
});

const cloneHeaderData = (header: ResolutionEditorHeaderData): ResolutionEditorHeaderData => ({
    ...header,
});

const headerFields: Array<{
    key: keyof ResolutionEditorHeaderData;
    label: string;
    required?: boolean;
    maxLength: number;
}> = [
    { key: 'numero', label: 'Expediente', required: true, maxLength: 100 },
    { key: 'materia', label: 'Materia', maxLength: 500 },
    { key: 'juzgado', label: 'Juzgado', maxLength: 255 },
    { key: 'especialista', label: 'Especialista', maxLength: 255 },
    { key: 'tercero', label: 'Tercero', maxLength: 255 },
    { key: 'demandado', label: 'Demandado', maxLength: 255 },
    { key: 'demandante', label: 'Demandante', maxLength: 255 },
];

const firstParam = (value: unknown): string => {
    if (Array.isArray(value)) {
        return String(value[0] ?? '');
    }

    return typeof value === 'string' ? value : '';
};

const route = useRoute();
const router = useRouter();
const toast = useToast();

const expedienteId = computed(() => Number(firstParam(route.params.expedienteId)));
const resolucionId = computed(() => Number(firstParam(route.params.resolucionId)));
const payload = ref<ResolutionEditorPayload | null>(null);
const content = ref<JSONContent>(emptyDocument());
const headerData = ref<ResolutionEditorHeaderData>(emptyHeaderData());
const savedSnapshot = ref('');
const loading = ref(true);
const saving = ref(false);
const finalizing = ref(false);
const loadError = ref<string | null>(null);
let loadRequestId = 0;
let allowLeave = false;

const isBusy = computed(() => loading.value || saving.value || finalizing.value);
const currentSnapshot = (): string =>
    JSON.stringify({ content: content.value, header_data: headerData.value });
const isDirty = computed(
    () => payload.value !== null && currentSnapshot() !== savedSnapshot.value,
);
const documentName = computed(
    () => payload.value?.document_name || `Resolución ${payload.value?.numero ?? ''}`,
);
const savedAtLabel = computed(() => {
    if (!payload.value?.saved_at) {
        return 'Borrador nuevo';
    }

    const date = new Date(payload.value.saved_at);
    return Number.isNaN(date.getTime())
        ? 'Guardado'
        : `Guardado ${date.toLocaleString('es-PE')}`;
});

const applyPayload = (editorPayload: ResolutionEditorPayload): void => {
    const normalizedContent = cloneContent(editorPayload.content || emptyDocument());
    payload.value = editorPayload;
    content.value = normalizedContent;
    headerData.value = cloneHeaderData(editorPayload.header_data);
    savedSnapshot.value = currentSnapshot();
};

const loadEditor = async (): Promise<void> => {
    const requestedExpedienteId = expedienteId.value;
    const requestedResolucionId = resolucionId.value;
    const requestId = ++loadRequestId;

    payload.value = null;
    content.value = emptyDocument();
    headerData.value = emptyHeaderData();
    savedSnapshot.value = '';
    loadError.value = null;
    allowLeave = false;

    if (
        !Number.isInteger(requestedExpedienteId) ||
        requestedExpedienteId < 1 ||
        !Number.isInteger(requestedResolucionId) ||
        requestedResolucionId < 1
    ) {
        loading.value = false;
        loadError.value = 'No se pudo identificar la resolución que desea editar.';
        return;
    }

    loading.value = true;

    try {
        const response = await expedientesAPI.getEditorResolucion(
            requestedExpedienteId,
            requestedResolucionId,
        );

        if (requestId !== loadRequestId) {
            return;
        }

        applyPayload(response);
    } catch (error) {
        if (requestId !== loadRequestId) {
            return;
        }

        loadError.value = await getApiErrorMessage(
            error,
            'No se pudo cargar el editor de la resolución.',
        );
    } finally {
        if (requestId === loadRequestId) {
            loading.value = false;
        }
    }
};

const saveDraft = async (showSuccess = true): Promise<boolean> => {
    const currentPayload = payload.value;

    if (!currentPayload) {
        return false;
    }

    if (!isDirty.value) {
        return true;
    }

    if (!headerData.value.numero.trim()) {
        toast.error('El número de expediente es obligatorio.');
        return false;
    }

    saving.value = true;

    try {
        const response = await expedientesAPI.guardarEditorResolucion(
            expedienteId.value,
            resolucionId.value,
            cloneContent(content.value),
            cloneHeaderData(headerData.value),
            currentPayload.version,
        );
        applyPayload(response);

        if (showSuccess) {
            toast.success('Borrador guardado correctamente.');
        }

        return true;
    } catch (error) {
        toast.error(await getApiErrorMessage(error, 'No se pudo guardar la resolución.'));
        return false;
    } finally {
        saving.value = false;
    }
};

const handleSave = (): void => {
    void saveDraft();
};

const handleFinalize = async (): Promise<void> => {
    if (!payload.value || finalizing.value) {
        return;
    }

    if (
        !window.confirm(
            '¿Confirma que terminó de redactar esta resolución? Al finalizar se incorporará al expediente.',
        )
    ) {
        return;
    }

    finalizing.value = true;

    try {
        if (isDirty.value && !(await saveDraft(false))) {
            return;
        }

        const currentPayload = payload.value;

        if (!currentPayload) {
            return;
        }

        await expedientesAPI.finalizarEditorResolucion(
            expedienteId.value,
            resolucionId.value,
            currentPayload.version,
        );
        savedSnapshot.value = currentSnapshot();
        toast.success(`Resolución ${currentPayload.numero} incorporada al expediente.`);
        allowLeave = true;
        await router.push({
            name: 'expediente-detail',
            params: { id: expedienteId.value },
        });
    } catch (error) {
        toast.error(await getApiErrorMessage(error, 'No se pudo finalizar la resolución.'));
    } finally {
        finalizing.value = false;
    }
};

const handleClose = async (): Promise<void> => {
    if (
        isDirty.value &&
        !window.confirm('Hay cambios sin guardar. ¿Desea cerrar el editor y descartarlos?')
    ) {
        return;
    }

    allowLeave = true;

    if (Number.isInteger(expedienteId.value) && expedienteId.value > 0) {
        await router.push({
            name: 'expediente-detail',
            params: { id: expedienteId.value },
        });
        return;
    }

    await router.push({ name: 'expedientes' });
};

const handleBeforeUnload = (event: BeforeUnloadEvent): void => {
    if (!isDirty.value) {
        return;
    }

    event.preventDefault();
    event.returnValue = '';
};

onBeforeRouteLeave(() => {
    if (allowLeave || !isDirty.value) {
        return true;
    }

    return window.confirm('Hay cambios sin guardar. ¿Desea salir y descartarlos?');
});

watch([expedienteId, resolucionId], () => void loadEditor(), { immediate: true });
onMounted(() => window.addEventListener('beforeunload', handleBeforeUnload));
onBeforeUnmount(() => {
    loadRequestId += 1;
    window.removeEventListener('beforeunload', handleBeforeUnload);
});
</script>

<template>
    <main class="flex h-screen h-dvh flex-col overflow-hidden bg-gray-100" aria-label="Editor de resolución">
        <header class="relative z-20 shrink-0 border-b border-gray-400 bg-white text-gray-950">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 lg:flex-row lg:items-center lg:justify-between lg:px-6">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-gray-700">
                        <FilePenLine class="h-4 w-4" aria-hidden="true" />
                        Actualizar expediente · Redactar resolución
                    </div>
                    <h1 class="mt-1 truncate text-xl font-bold sm:text-2xl">{{ documentName }}</h1>
                    <p
                        class="mt-2 inline-flex rounded-full px-3 py-1 text-sm font-bold"
                        :class="isDirty ? 'bg-gray-300 text-gray-950' : 'bg-gray-300 text-gray-950'"
                        aria-live="polite"
                    >
                        {{ isDirty ? 'Cambios sin guardar' : savedAtLabel }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button
                        variant="outline"
                        size="lg"
                        :disabled="saving || finalizing"
                        @click="handleClose"
                    >
                        <template #icon><ArrowLeft class="h-5 w-5" /></template>
                        Volver al expediente
                    </Button>
                    <Button
                        variant="outline"
                        size="lg"
                        :loading="saving"
                        :disabled="!payload || !isDirty || finalizing"
                        @click="handleSave"
                    >
                        <template #icon><Save class="h-5 w-5" /></template>
                        Guardar
                    </Button>
                    <Button
                        variant="primary"
                        size="lg"
                        :loading="finalizing"
                        :disabled="!payload || saving"
                        @click="handleFinalize"
                    >
                        <template #icon><CircleCheck class="h-5 w-5" /></template>
                        Terminar e incorporar
                    </Button>
                </div>
            </div>
        </header>

        <div v-if="loading" class="flex min-h-0 flex-1 items-center justify-center overflow-y-auto px-4">
            <div class="text-center" role="status" aria-live="polite">
                <div class="mx-auto mb-4 h-12 w-12 animate-spin rounded-full border-4 border-gray-300 border-t-gray-700" />
                <p class="text-lg font-bold text-gray-800">Preparando la resolución...</p>
            </div>
        </div>

        <div v-else-if="loadError" class="min-h-0 flex-1 overflow-y-auto">
            <div class="mx-auto max-w-xl px-4 py-20 text-center">
                <h2 class="text-xl font-bold text-gray-900">No se pudo abrir el editor</h2>
                <p class="mt-3 text-gray-600">{{ loadError }}</p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <Button variant="outline" size="lg" @click="handleClose">Volver al expediente</Button>
                    <Button variant="primary" size="lg" @click="loadEditor">Reintentar</Button>
                </div>
            </div>
        </div>

        <div
            v-else-if="payload"
            data-testid="resolution-editor-scroll"
            class="min-h-0 flex-1 overflow-y-auto"
        >
            <div class="mx-auto max-w-7xl p-4 sm:p-6">
                <div class="mb-4 flex flex-col gap-2 rounded-2xl border border-gray-200 bg-gray-50 px-5 py-4 text-gray-950 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="font-bold">Antes de terminar</p>
                        <p class="mt-1 text-sm">Revise la cabecera y el texto. Use <strong>Guardar</strong> si continuará más tarde; use <strong>Terminar e incorporar</strong> solo cuando esté listo.</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-white px-3 py-1 text-sm font-bold text-gray-800 shadow-sm">Resolución {{ payload.numero }}</span>
                </div>
                <RichTextEditor
                    v-model="content"
                    :disabled="isBusy"
                    :aria-label="`Contenido de la resolución ${payload.numero}`"
                >
                    <template #before-content>
                        <div class="mb-8 font-[Arial] text-[12pt] text-gray-950">
                            <div class="ml-auto w-full max-w-xl rounded-xl border-2 border-gray-100 bg-gray-50 p-5 shadow-sm" aria-label="Cabecera editable del expediente">
                                <p class="mb-1 text-base font-bold text-gray-950">
                                    Datos que aparecerán en la cabecera
                                </p>
                                <p class="mb-4 text-sm text-gray-800">Complete o corrija únicamente los datos necesarios.</p>
                                <div
                                    v-for="field in headerFields"
                                    :key="field.key"
                                    class="mb-2 grid grid-cols-[minmax(7rem,auto)_1rem_minmax(0,1fr)] items-center gap-x-1"
                                >
                                    <label :for="`header-${field.key}`">
                                        {{ field.label }}<span v-if="field.required" aria-hidden="true"> *</span>
                                    </label>
                                    <span aria-hidden="true">:</span>
                                    <input
                                        :id="`header-${field.key}`"
                                        v-model="headerData[field.key]"
                                        type="text"
                                        :required="field.required"
                                        :maxlength="field.maxLength"
                                        :disabled="isBusy"
                                        autocomplete="off"
                                        class="min-h-10 w-full rounded-lg border-2 border-gray-300 bg-white px-3 py-1 font-[Arial] text-[12pt] uppercase text-gray-950 shadow-sm focus:border-gray-600 focus:outline-none focus:ring-4 focus:ring-gray-100 disabled:bg-gray-100"
                                    />
                                </div>
                                <p class="mt-3 text-sm text-gray-600">
                                    Los campos vacíos no aparecerán en el documento generado.
                                </p>
                            </div>

                            <h2 class="mt-8 font-bold">RESOLUCIÓN N° {{ payload.numero }}</h2>
                        </div>
                    </template>
                </RichTextEditor>
            </div>
        </div>
    </main>
</template>
