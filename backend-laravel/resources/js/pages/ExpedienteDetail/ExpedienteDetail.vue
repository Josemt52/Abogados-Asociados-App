<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import {
    ArrowLeft,
    CheckCircle2,
    Clock,
    Download,
    Edit,
    File as FileIcon,
    FilePlus2,
    Scale,
    Trash2,
    Upload,
    User,
} from '@lucide/vue';
import { expedientesAPI, type Expediente, type Resolucion } from '@/api';
import ExpedienteForm from '@/components/ExpedienteForm/ExpedienteForm.vue';
import FileUploader from '@/components/FileUploader/FileUploader.vue';
import Button from '@/components/UI/Button.vue';
import Modal from '@/components/UI/Modal.vue';
import { useToast } from '@/composables/useToast';
import { getApiErrorMessage } from '@/utils/apiError';
import { downloadBlob } from '@/utils/fileDownload';
import { isValidPdfBlob } from '@/utils/pdf';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const showEditModal = ref(false);
const showUploadModal = ref(false);
const showDeleteConfirm = ref(false);
const showUpdateStatusModal = ref(false);
const showInitialResolutionModal = ref(false);
const showCompleteResolutionModal = ref(false);
const statusText = ref('');
const statusLoading = ref(false);
const initialResolutionNumber = ref(0);
const initialResolutionLoading = ref(false);
const generatingResolution = ref(false);
const openingResolutionEditor = ref(false);
const completingResolution = ref(false);
const downloadingResolutionId = ref<number | null>(null);
const completionResolutionId = ref<number | null>(null);
const completionResolutionNumber = ref<number | null>(null);
const hasPromptedInitialResolution = ref(false);
const openEditorAfterInitialConfirmation = ref(false);
const loading = ref(false);
const isGenerating = ref(false);
const expediente = ref<Expediente | null>(null);
const resoluciones = ref<Resolucion[]>([]);
const expedienteLoading = ref(true);
const resolucionesLoading = ref(true);
const resolucionesError = ref<string | null>(null);
let refetchRequestId = 0;
let routeLoadRequestId = 0;

const rawId = computed(() => {
    const routeId = route.params.id;
    return Array.isArray(routeId) ? routeId[0] : routeId;
});
const expedienteId = computed(() => Number(rawId.value));
const sortedResoluciones = computed(() =>
    [...resoluciones.value].sort((a, b) => b.numero - a.numero),
);
const pendingResolution = computed(
    () => sortedResoluciones.value.find((resolucion) => resolucion.estado === 'pendiente') ?? null,
);
const resolutionHistoryReady = computed(
    () =>
        expediente.value !== null &&
        !resolucionesLoading.value &&
        resolucionesError.value === null,
);
const nextResolutionNumber = computed(() => (expediente.value?.ultima_resolucion ?? 0) + 1);
const lastResolutionLabel = computed(() => {
    if (!resolutionHistoryReady.value) {
        return resolucionesLoading.value ? 'Cargando' : 'No disponible';
    }

    const numero = expediente.value?.ultima_resolucion;

    if (numero == null) {
        return 'Por confirmar';
    }

    return numero === 0 ? 'Ninguna' : String(numero);
});
const formattedUpdatedAt = computed(() => {
    if (!expediente.value?.updated_at) {
        return 'Sin fecha';
    }

    const date = new Date(expediente.value.updated_at);
    return Number.isNaN(date.getTime()) ? 'Sin fecha' : date.toLocaleDateString('es-PE');
});

const isActiveRefetch = (requestId: number, requestedRawId: string): boolean =>
    requestId === refetchRequestId && rawId.value === requestedRawId;

const refetch = async (): Promise<void> => {
    const requestedRawId = rawId.value;
    const requestId = ++refetchRequestId;

    if (!requestedRawId) {
        expediente.value = null;
        resoluciones.value = [];
        resolucionesError.value = null;
        expedienteLoading.value = false;
        resolucionesLoading.value = false;
        return;
    }

    const requestedExpedienteId = Number(requestedRawId);

    if (!Number.isInteger(requestedExpedienteId) || requestedExpedienteId < 1) {
        expediente.value = null;
        resoluciones.value = [];
        resolucionesError.value = null;
        expedienteLoading.value = false;
        resolucionesLoading.value = false;
        return;
    }

    expedienteLoading.value = true;
    resolucionesLoading.value = true;
    resolucionesError.value = null;

    try {
        const [expedienteResult, resolucionesResult] = await Promise.allSettled([
            expedientesAPI.getById(requestedExpedienteId),
            expedientesAPI.getResoluciones(requestedExpedienteId),
        ]);

        if (!isActiveRefetch(requestId, requestedRawId)) {
            return;
        }

        if (expedienteResult.status === 'rejected') {
            expediente.value = null;
            resoluciones.value = [];
            resolucionesError.value = null;
            return;
        }

        if (resolucionesResult.status === 'rejected') {
            expediente.value = expedienteResult.value;
            resoluciones.value = [];
            const message = await getApiErrorMessage(
                resolucionesResult.reason,
                'No se pudo cargar el historial de resoluciones.',
            );

            if (!isActiveRefetch(requestId, requestedRawId)) {
                return;
            }

            resolucionesError.value = message;
            showInitialResolutionModal.value = false;
            showCompleteResolutionModal.value = false;
            return;
        }

        const snapshot = resolucionesResult.value;
        const expedienteResponse: Expediente = {
            ...expedienteResult.value,
            ultima_resolucion: snapshot.ultima_resolucion,
            resolucion_detectada: snapshot.resolucion_detectada,
        };
        expediente.value = expedienteResponse;
        resoluciones.value = snapshot.resoluciones;

        if (
            expedienteResponse.ultima_resolucion == null &&
            !hasPromptedInitialResolution.value
        ) {
            const detected = Number(expedienteResponse.resolucion_detectada ?? 0);
            initialResolutionNumber.value = Number.isInteger(detected) && detected >= 0 ? detected : 0;
            hasPromptedInitialResolution.value = true;
            showInitialResolutionModal.value = true;
        }
    } finally {
        if (isActiveRefetch(requestId, requestedRawId)) {
            expedienteLoading.value = false;
            resolucionesLoading.value = false;
        }
    }
};

const handleFileUpload = async (
    file: File,
    onProgress?: (progress: number) => void,
): Promise<void> => {
    try {
        await expedientesAPI.uploadFile(expedienteId.value, file, onProgress);
        toast.success('Archivo subido correctamente');
        showUploadModal.value = false;
        hasPromptedInitialResolution.value = false;
        await refetch();

        if (expediente.value?.ultima_resolucion != null) {
            statusText.value = expediente.value?.estado || '';
            showUpdateStatusModal.value = true;
        }
    } catch (error) {
        toast.error('Error al subir el archivo');
        throw error;
    }
};

const handleDownloadFile = async (): Promise<void> => {
    try {
        loading.value = true;
        const blob = await expedientesAPI.downloadFile(expedienteId.value);
        const filename = expediente.value?.nombre_archivo || 'documento.docx';
        downloadBlob(blob, filename);
        toast.success('Archivo descargado correctamente');
    } catch {
        toast.error('Error al descargar el archivo');
    } finally {
        loading.value = false;
    }
};

const handleGeneratePdf = async (): Promise<void> => {
    try {
        isGenerating.value = true;
        const blob = await expedientesAPI.generatePdf(expedienteId.value);

        if (!(await isValidPdfBlob(blob))) {
            throw new Error('El servidor no devolvió un PDF válido.');
        }

        const filename = `expediente_${expediente.value?.numero || rawId.value}.pdf`;
        downloadBlob(blob, filename);
        toast.success('Documento PDF generado correctamente');
    } catch (error) {
        toast.error(await getApiErrorMessage(error, 'Error al generar documento PDF'));
        console.error(error);
    } finally {
        isGenerating.value = false;
    }
};

const handleConfirmInitialResolution = async (): Promise<void> => {
    if (!resolutionHistoryReady.value) {
        toast.error('Primero vuelve a cargar el historial de resoluciones.');
        return;
    }

    const numero = Number(initialResolutionNumber.value);

    if (!Number.isInteger(numero) || numero < 0) {
        toast.error('Ingrese un número de resolución válido. Use 0 si todavía no existe ninguna.');
        return;
    }

    try {
        initialResolutionLoading.value = true;
        await expedientesAPI.confirmarResolucionInicial(expedienteId.value, numero);
        showInitialResolutionModal.value = false;
        toast.success(
            numero === 0
                ? 'El expediente quedó listo para crear su primera resolución.'
                : `Última resolución confirmada: ${numero}.`,
        );
        await refetch();

        if (openEditorAfterInitialConfirmation.value) {
            openEditorAfterInitialConfirmation.value = false;
            await handleOpenResolutionEditor();
        }
    } catch (error) {
        console.error('Error confirming initial resolution', error);
        toast.error(
            await getApiErrorMessage(error, 'No se pudo confirmar el número de resolución.'),
        );
    } finally {
        initialResolutionLoading.value = false;
    }
};

const openPendingResolution = (resolucion: Resolucion): void => {
    completionResolutionId.value = resolucion.id;
    completionResolutionNumber.value = resolucion.numero;
    showCompleteResolutionModal.value = true;
};

const handleOpenResolutionEditor = async (): Promise<void> => {
    if (!resolutionHistoryReady.value) {
        toast.error('El historial de resoluciones no está disponible. Vuelve a intentarlo.');
        return;
    }

    if (expediente.value?.ultima_resolucion == null) {
        openEditorAfterInitialConfirmation.value = true;
        showInitialResolutionModal.value = true;
        return;
    }

    try {
        openingResolutionEditor.value = true;
        const editor = await expedientesAPI.iniciarEditorResolucion(expedienteId.value);

        await router.push({
            name: 'resolution-editor',
            params: {
                expedienteId: editor.expediente_id,
                resolucionId: editor.resolucion_id,
            },
        });
    } catch (error) {
        console.error('Error opening resolution editor', error);
        toast.error(
            await getApiErrorMessage(error, 'No se pudo abrir el editor de la resolución.'),
        );
    } finally {
        openingResolutionEditor.value = false;
    }
};

const handleDownloadResolutionTemplate = async (): Promise<void> => {
    if (!resolutionHistoryReady.value) {
        toast.error('El historial de resoluciones no está disponible. Vuelve a intentarlo.');
        return;
    }

    if (expediente.value?.ultima_resolucion == null) {
        showInitialResolutionModal.value = true;
        return;
    }

    try {
        generatingResolution.value = true;
        const plantilla = await expedientesAPI.generarSiguienteResolucion(expedienteId.value);
        downloadBlob(plantilla.blob, plantilla.filename);

        completionResolutionId.value = plantilla.resolucionId;
        completionResolutionNumber.value = plantilla.numero;
        showCompleteResolutionModal.value = true;
        toast.success(`Plantilla de la resolución ${plantilla.numero} descargada.`);
        await refetch();
    } catch (error) {
        console.error('Error downloading resolution template', error);
        toast.error(
            await getApiErrorMessage(error, 'No se pudo generar la siguiente resolución.'),
        );
    } finally {
        generatingResolution.value = false;
    }
};

const handleCompleteResolution = async (
    file: File,
    onProgress?: (progress: number) => void,
): Promise<void> => {
    if (!resolutionHistoryReady.value) {
        toast.error('Primero vuelve a cargar el historial de resoluciones.');
        return;
    }

    if (completionResolutionId.value == null) {
        toast.error('No se pudo identificar la resolución pendiente.');
        return;
    }

    try {
        completingResolution.value = true;
        await expedientesAPI.completarResolucion(
            expedienteId.value,
            completionResolutionId.value,
            file,
            onProgress,
        );
        const completedNumber = completionResolutionNumber.value;
        showCompleteResolutionModal.value = false;
        completionResolutionId.value = null;
        completionResolutionNumber.value = null;
        toast.success(`Resolución ${completedNumber} incorporada al expediente.`);
        await refetch();
    } catch (error) {
        console.error('Error completing resolution', error);
        toast.error(
            await getApiErrorMessage(error, 'No se pudo incorporar la resolución al expediente.'),
        );
        throw error;
    } finally {
        completingResolution.value = false;
    }
};

const formatResolutionDate = (dateValue: string): string => {
    const date = new Date(dateValue);
    return Number.isNaN(date.getTime()) ? 'Sin fecha' : date.toLocaleDateString('es-PE');
};

const handleDownloadResolution = async (resolucion: Resolucion): Promise<void> => {
    try {
        downloadingResolutionId.value = resolucion.id;
        const blob = await expedientesAPI.downloadResolucion(expedienteId.value, resolucion.id);
        downloadBlob(blob, resolucion.nombre_archivo || `resolucion_${resolucion.numero}.docx`);
        toast.success(`Resolución ${resolucion.numero} descargada.`);
    } catch (error) {
        toast.error(
            await getApiErrorMessage(error, 'No se pudo descargar el documento de la resolución.'),
        );
    } finally {
        downloadingResolutionId.value = null;
    }
};

const handleDelete = async (): Promise<void> => {
    try {
        loading.value = true;
        await expedientesAPI.delete(expedienteId.value);
        toast.success('Expediente eliminado correctamente');
        await router.push('/expedientes');
    } catch {
        toast.error('Error al eliminar el expediente');
    } finally {
        loading.value = false;
        showDeleteConfirm.value = false;
    }
};

const handleEditSuccess = (): void => {
    showEditModal.value = false;
    void refetch();
};

const handleUpdateStatus = async (): Promise<void> => {
    try {
        statusLoading.value = true;
        await expedientesAPI.update(expedienteId.value, { estado: statusText.value });
        toast.success('Estado del expediente actualizado');
        showUpdateStatusModal.value = false;
        await refetch();
    } catch {
        toast.error('Error al actualizar el estado');
    } finally {
        statusLoading.value = false;
    }
};

const closeInitialResolutionModal = (): void => {
    openEditorAfterInitialConfirmation.value = false;
    showInitialResolutionModal.value = false;
};

const loadRouteAndMaybeOpenEditor = async (
    requestedRawId: string,
    shouldOpenEditor: boolean,
): Promise<void> => {
    const requestId = ++routeLoadRequestId;
    await refetch();

    if (
        !shouldOpenEditor ||
        requestId !== routeLoadRequestId ||
        rawId.value !== requestedRawId ||
        route.query.editor !== 'true'
    ) {
        return;
    }

    const query = { ...route.query };
    delete query.editor;
    await router.replace({ query });

    if (requestId === routeLoadRequestId && rawId.value === requestedRawId) {
        await handleOpenResolutionEditor();
    }
};

watch(
    rawId,
    (id) => {
        if (!id) {
            refetchRequestId += 1;
            void router.push('/expedientes');
            return;
        }

        hasPromptedInitialResolution.value = false;
        openEditorAfterInitialConfirmation.value = false;
        showInitialResolutionModal.value = false;
        showCompleteResolutionModal.value = false;
        void loadRouteAndMaybeOpenEditor(id, route.query.editor === 'true');
    },
    { immediate: true },
);

watch(
    () => route.query.editor,
    (editor, previousEditor) => {
        if (editor === 'true' && previousEditor !== 'true' && rawId.value) {
            void loadRouteAndMaybeOpenEditor(rawId.value, true);
        }
    },
);
</script>

<template>
    <div v-if="expedienteLoading" class="flex h-64 items-center justify-center">
        <div class="animate-pulse text-gray-500">Cargando expediente...</div>
    </div>

    <div v-else-if="!expediente" class="py-12 text-center">
        <Scale class="mx-auto mb-4 h-12 w-12 text-gray-400" />
        <p class="text-gray-500">Expediente no encontrado</p>
        <Button variant="outline" class="mt-4" @click="router.push('/expedientes')">
            Volver a expedientes
        </Button>
    </div>

    <div v-else class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <RouterLink
                    to="/expedientes"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Volver a expedientes
                </RouterLink>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Expediente #{{ expediente.numero }}</h1>
                    <p class="text-gray-600">{{ expediente.materia }}</p>
                </div>
            </div>
            <div class="flex space-x-3">
                <Button variant="outline" @click="showEditModal = true">
                    <template #icon>
                        <Edit class="h-4 w-4" />
                    </template>
                    Editar
                </Button>
                <Button
                    v-if="resolutionHistoryReady && sortedResoluciones.length === 0"
                    variant="outline"
                    @click="showUploadModal = true"
                >
                    <template #icon>
                        <Upload class="h-4 w-4" />
                    </template>
                    {{ expediente.archivo ? 'Reemplazar documento inicial' : 'Subir documento inicial' }}
                </Button>
            </div>
        </div>

        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
            <div class="flex items-center space-x-2">
                <Clock class="h-5 w-5 text-blue-600" />
                <span class="text-sm font-medium text-blue-900">
                    Estado: {{ expediente.estado || 'En proceso' }}
                </span>
                <span class="text-sm text-blue-700">
                    • Última actualización: {{ formattedUpdatedAt }}
                </span>
                <span class="text-sm font-medium text-blue-900">
                    • Última resolución: {{ lastResolutionLabel }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="mb-4 flex items-center text-lg font-semibold text-gray-900">
                        <Scale class="mr-2 h-5 w-5 text-blue-600" />
                        Información del Expediente
                    </h2>
                    <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Número</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900">{{ expediente.numero }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Materia</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ expediente.materia }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Juzgado</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ expediente.juzgado }}</dd>
                        </div>
                        <div>
                            <dt class="flex items-center text-sm font-medium text-gray-500">
                                <User class="mr-1 h-4 w-4" />
                                Especialista
                            </dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ expediente.especialista }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Terceros</dt>
                            <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ expediente.tercero || 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Demandados</dt>
                            <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ expediente.demandado || 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Demandantes</dt>
                            <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ expediente.demandante || 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Archivo</dt>
                            <dd class="mt-1">
                                <span
                                    v-if="expediente.archivo"
                                    class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800"
                                >
                                    ✓ {{ expediente.nombre_archivo || 'Disponible' }}
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800"
                                >
                                    Sin archivo
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <div
                    v-if="expediente.estado"
                    class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
                >
                    <h2 class="mb-4 text-lg font-semibold text-gray-900">Estado Actual</h2>
                    <div class="prose max-w-none rounded-lg bg-gray-50 p-4 text-sm text-gray-700">
                        {{ expediente.estado }}
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Historial de resoluciones</h2>
                            <p class="text-sm text-gray-500">
                                Documentos incorporados y resoluciones pendientes de completar.
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-800"
                        >
                            Última: {{ lastResolutionLabel }}
                        </span>
                    </div>

                    <div
                        v-if="resolucionesLoading"
                        class="rounded-lg bg-gray-50 px-4 py-8 text-center text-sm text-gray-500"
                    >
                        Cargando historial de resoluciones...
                    </div>

                    <div
                        v-else-if="resolucionesError"
                        class="rounded-lg border border-red-200 bg-red-50 px-4 py-6 text-center"
                    >
                        <p class="font-medium text-red-800">No se pudo cargar el historial de resoluciones.</p>
                        <p class="mt-1 text-sm text-red-700">{{ resolucionesError }}</p>
                        <Button variant="outline" size="sm" class="mt-4" @click="refetch">
                            Reintentar
                        </Button>
                    </div>

                    <div v-else-if="sortedResoluciones.length" class="divide-y divide-gray-200">
                        <div
                            v-for="resolucion in sortedResoluciones"
                            :key="resolucion.id"
                            class="flex items-center justify-between py-4"
                        >
                            <div class="flex items-center space-x-3">
                                <div
                                    class="rounded-full p-2"
                                    :class="
                                        resolucion.estado === 'completada'
                                            ? 'bg-green-100 text-green-700'
                                            : resolucion.estado === 'base'
                                              ? 'bg-blue-100 text-blue-700'
                                              : 'bg-amber-100 text-amber-700'
                                    "
                                >
                                    <CheckCircle2
                                        v-if="resolucion.estado === 'completada'"
                                        class="h-5 w-5"
                                    />
                                    <FileIcon v-else-if="resolucion.estado === 'base'" class="h-5 w-5" />
                                    <Clock v-else class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">
                                        {{
                                            resolucion.estado === 'base' && resolucion.numero === 0
                                                ? 'Documento base (sin resoluciones)'
                                                : `Resolución N.º ${resolucion.numero}`
                                        }}
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        {{
                                            resolucion.estado === 'completada'
                                                ? resolucion.nombre_archivo || 'Documento incorporado'
                                                : resolucion.estado === 'base'
                                                  ? 'Documento original usado como base del expediente'
                                                  : 'Pendiente de editar o subir el Word terminado'
                                        }}
                                        •
                                        {{
                                            formatResolutionDate(
                                                resolucion.completada_at || resolucion.created_at,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <Button
                                    v-if="resolucion.estado === 'pendiente'"
                                    variant="primary"
                                    size="sm"
                                    :loading="openingResolutionEditor"
                                    :disabled="generatingResolution"
                                    @click="handleOpenResolutionEditor"
                                >
                                    <template #icon>
                                        <Edit class="h-4 w-4" />
                                    </template>
                                    Editar en línea
                                </Button>
                                <Button
                                    v-if="resolucion.estado === 'pendiente'"
                                    variant="outline"
                                    size="sm"
                                    :loading="generatingResolution"
                                    :disabled="openingResolutionEditor"
                                    @click="handleDownloadResolutionTemplate"
                                >
                                    <template #icon>
                                        <Download class="h-4 w-4" />
                                    </template>
                                    Descargar Word
                                </Button>
                                <Button
                                    v-else-if="resolucion.nombre_archivo"
                                    variant="outline"
                                    size="sm"
                                    :loading="downloadingResolutionId === resolucion.id"
                                    @click="handleDownloadResolution(resolucion)"
                                >
                                    <template #icon>
                                        <Download class="h-4 w-4" />
                                    </template>
                                    Descargar
                                </Button>
                                <Button
                                    v-if="resolucion.estado === 'pendiente'"
                                    variant="outline"
                                    size="sm"
                                    @click="openPendingResolution(resolucion)"
                                >
                                    Subir documento
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="rounded-lg bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
                        Todavía no hay resoluciones registradas individualmente.
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Acciones Rápidas</h3>
                    <div class="space-y-3">
                        <Button
                            variant="primary"
                            :loading="openingResolutionEditor"
                            :disabled="!resolutionHistoryReady || generatingResolution"
                            class="w-full justify-start"
                            @click="handleOpenResolutionEditor"
                        >
                            <template #icon>
                                <FilePlus2 class="h-4 w-4" />
                            </template>
                            <template v-if="resolucionesLoading">Cargando resoluciones...</template>
                            <template v-else-if="resolucionesError">Historial no disponible</template>
                            <template v-else-if="pendingResolution">
                                Continuar resolución {{ pendingResolution.numero }}
                            </template>
                            <template v-else-if="expediente.ultima_resolucion == null">
                                Configurar resoluciones
                            </template>
                            <template v-else>
                                Redactar resolución {{ nextResolutionNumber }}
                            </template>
                        </Button>

                        <Button
                            v-if="resolutionHistoryReady && expediente.ultima_resolucion != null"
                            variant="outline"
                            :loading="generatingResolution"
                            :disabled="openingResolutionEditor"
                            class="w-full justify-start"
                            @click="handleDownloadResolutionTemplate"
                        >
                            <template #icon>
                                <Download class="h-4 w-4" />
                            </template>
                            Descargar plantilla Word
                            {{ pendingResolution?.numero ?? nextResolutionNumber }}
                        </Button>

                        <Button
                            v-if="expediente.archivo"
                            variant="outline"
                            :loading="loading"
                            class="w-full justify-start"
                            @click="handleDownloadFile"
                        >
                            <template #icon>
                                <Download class="h-4 w-4" />
                            </template>
                            Descargar Archivo
                        </Button>

                        <Button
                            v-if="expediente.archivo"
                            variant="outline"
                            :loading="isGenerating"
                            class="w-full justify-start"
                            @click="handleGeneratePdf"
                        >
                            <template #icon>
                                <FileIcon class="h-4 w-4" />
                            </template>
                            Generar PDF
                        </Button>
                    </div>
                </div>

                <div
                    v-if="expediente.archivo"
                    class="rounded-lg border border-green-200 bg-green-50 p-6"
                >
                    <h3 class="mb-3 text-sm font-semibold text-green-900">Archivo Adjunto</h3>
                    <div class="space-y-2">
                        <p class="text-sm text-green-700">
                            <strong>Nombre:</strong> {{ expediente.nombre_archivo || 'documento.docx' }}
                        </p>
                        <p class="text-sm text-green-700"><strong>Estado:</strong> Disponible</p>
                    </div>
                </div>

                <div class="rounded-lg border border-red-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-red-900">Zona de Peligro</h3>
                    <Button
                        variant="danger"
                        class="w-full justify-start"
                        @click="showDeleteConfirm = true"
                    >
                        <template #icon>
                            <Trash2 class="h-4 w-4" />
                        </template>
                        Eliminar Expediente
                    </Button>
                </div>
            </div>
        </div>

        <Modal :open="showEditModal" title="Editar Expediente" size="xl" @close="showEditModal = false">
            <ExpedienteForm
                :expediente="expediente"
                @success="handleEditSuccess"
                @cancel="showEditModal = false"
            />
        </Modal>

        <Modal :open="showUploadModal" title="Subir Archivo" size="md" @close="showUploadModal = false">
            <FileUploader
                :on-upload="handleFileUpload"
                accept=".pdf,.doc,.docx"
                :loading="loading"
            />
        </Modal>

        <Modal
            :open="showInitialResolutionModal"
            title="Confirmar última resolución"
            size="md"
            @close="closeInitialResolutionModal"
        >
            <div class="space-y-5">
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                    <template v-if="expediente.resolucion_detectada != null">
                        Detectamos que el documento llega hasta la resolución
                        <strong>N.º {{ expediente.resolucion_detectada }}</strong>. Confirme el número o
                        corríjalo antes de continuar.
                    </template>
                    <template v-else-if="expediente.archivo">
                        No pudimos detectar con seguridad la última resolución del documento. Indique el número
                        correcto para continuar la serie.
                    </template>
                    <template v-else>
                        Este expediente todavía no tiene resoluciones. Mantenga el valor en 0 para que la primera
                        plantilla sea la Resolución N.º 1.
                    </template>
                </div>

                <div>
                    <label for="initial-resolution" class="mb-1 block text-sm font-medium text-gray-700">
                        Última resolución completada
                    </label>
                    <input
                        id="initial-resolution"
                        v-model.number="initialResolutionNumber"
                        type="number"
                        min="0"
                        step="1"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    />
                    <p class="mt-1 text-xs text-gray-500">Use 0 si todavía no existe ninguna resolución.</p>
                </div>

                <div class="flex justify-end space-x-3">
                    <Button variant="outline" @click="closeInitialResolutionModal">Ahora no</Button>
                    <Button
                        variant="primary"
                        :loading="initialResolutionLoading"
                        @click="handleConfirmInitialResolution"
                    >
                        Confirmar número
                    </Button>
                </div>
            </div>
        </Modal>

        <Modal
            :open="showCompleteResolutionModal"
            :title="`Completar Resolución N.º ${completionResolutionNumber ?? ''}`"
            size="md"
            @close="showCompleteResolutionModal = false"
        >
            <div class="space-y-5">
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    La plantilla ya fue descargada. Termine de redactarla y arrastre aquí el documento Word. El
                    número del expediente se actualizará únicamente cuando la carga finalice correctamente.
                </div>

                <FileUploader
                    :on-upload="handleCompleteResolution"
                    accept=".doc,.docx"
                    :loading="completingResolution || !resolutionHistoryReady"
                />

                <p class="text-xs text-gray-500">
                    Conservaremos el documento de esta resolución en el historial y actualizaremos el expediente
                    consolidado.
                </p>
            </div>
        </Modal>

        <Modal
            :open="showUpdateStatusModal"
            title="Actualizar estado del expediente"
            size="md"
            @close="showUpdateStatusModal = false"
        >
            <div class="space-y-4">
                <p class="text-sm text-gray-600">
                    ¿Desea actualizar el estado del expediente ahora? Puede dejar una nota o pegar el texto del
                    estado.
                </p>
                <textarea
                    v-model="statusText"
                    rows="6"
                    class="w-full rounded-md border border-gray-300 p-2 text-sm"
                ></textarea>
                <div class="flex justify-end space-x-3">
                    <Button variant="outline" @click="showUpdateStatusModal = false">No, luego</Button>
                    <Button variant="primary" :loading="statusLoading" @click="handleUpdateStatus">
                        Guardar estado
                    </Button>
                </div>
            </div>
        </Modal>

        <Modal
            :open="showDeleteConfirm"
            title="Confirmar Eliminación"
            size="md"
            @close="showDeleteConfirm = false"
        >
            <div class="space-y-4">
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <Trash2 class="h-6 w-6 text-red-600" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-900">¿Está seguro que desea eliminar este expediente?</p>
                        <p class="text-sm text-gray-500">Esta acción no se puede deshacer.</p>
                    </div>
                </div>
                <div class="rounded-md border border-red-200 bg-red-50 p-3">
                    <p class="text-sm text-red-700">
                        <strong>Expediente:</strong> #{{ expediente.numero }} - {{ expediente.materia }}
                    </p>
                </div>
                <div class="flex justify-end space-x-3">
                    <Button variant="outline" @click="showDeleteConfirm = false">Cancelar</Button>
                    <Button variant="danger" :loading="loading" @click="handleDelete">
                        Eliminar Expediente
                    </Button>
                </div>
            </div>
        </Modal>
    </div>
</template>
