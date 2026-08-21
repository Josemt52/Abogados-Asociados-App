<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { isAxiosError } from 'axios';
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
const showFinalizeOnlineModal = ref(false);
const statusText = ref('');
const statusLoading = ref(false);
const initialResolutionNumber = ref(0);
const initialResolutionLoading = ref(false);
const generatingResolution = ref(false);
const completingResolution = ref(false);
const finalizingOnlineResolution = ref(false);
const downloadingResolutionId = ref<number | null>(null);
const completionResolutionId = ref<number | null>(null);
const completionResolutionNumber = ref<number | null>(null);
const onlineFinalizationResolutionId = ref<number | null>(null);
const onlineFinalizationResolutionNumber = ref<number | null>(null);
const onlineFinalizationError = ref<string | null>(null);
const retryingMasterPdf = ref(false);
const hasPromptedInitialResolution = ref(false);
const loading = ref(false);
const isGenerating = ref(false);
const expediente = ref<Expediente | null>(null);
const resoluciones = ref<Resolucion[]>([]);
const expedienteLoading = ref(true);
const resolucionesLoading = ref(true);
const resolucionesError = ref<string | null>(null);
let refetchRequestId = 0;
let masterPdfPollTimer: ReturnType<typeof setTimeout> | null = null;
let masterPdfPollGeneration = 0;
let masterPdfVerificationUntil = 0;

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
const onlineFinalizationResolution = computed(
    () =>
        resoluciones.value.find(
            (resolucion) => resolucion.id === onlineFinalizationResolutionId.value,
        ) ?? null,
);
const resolutionHistoryReady = computed(
    () =>
        expediente.value !== null &&
        !resolucionesLoading.value &&
        resolucionesError.value === null,
);
const nextResolutionNumber = computed(() => (expediente.value?.ultima_resolucion ?? 0) + 1);
const masterPdfRebuildStatus = computed(
    () => expediente.value?.master_pdf_rebuild_status ?? 'ready',
);
const masterPdfReady = computed(() => masterPdfRebuildStatus.value === 'ready');
const masterPdfRebuildStale = computed(() => {
    if (masterPdfRebuildStatus.value !== 'pending') {
        return false;
    }

    const requestedAt = expediente.value?.master_pdf_rebuild_requested_at;
    const timestamp = requestedAt ? new Date(requestedAt).getTime() : Number.NaN;

    return !Number.isFinite(timestamp) || Date.now() - timestamp >= 10 * 60 * 1000;
});
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

const clearMasterPdfPollTimer = (): void => {
    if (masterPdfPollTimer !== null) {
        clearTimeout(masterPdfPollTimer);
        masterPdfPollTimer = null;
    }
};

const stopMasterPdfPolling = (): void => {
    masterPdfPollGeneration += 1;
    clearMasterPdfPollTimer();
};

const refreshMasterPdfState = async (
    requestedExpedienteId: number,
    generation: number,
): Promise<void> => {
    try {
        const refreshed = await expedientesAPI.getById(requestedExpedienteId, { silent: true });

        if (
            generation !== masterPdfPollGeneration ||
            expedienteId.value !== requestedExpedienteId ||
            expediente.value === null
        ) {
            return;
        }

        expediente.value = {
            ...expediente.value,
            archivo: refreshed.archivo,
            nombre_archivo: refreshed.nombre_archivo,
            master_pdf_rebuild_version: refreshed.master_pdf_rebuild_version,
            master_pdf_rebuild_status: refreshed.master_pdf_rebuild_status,
            master_pdf_rebuild_error: refreshed.master_pdf_rebuild_error,
            master_pdf_rebuild_requested_at: refreshed.master_pdf_rebuild_requested_at,
            master_pdf_rebuilt_at: refreshed.master_pdf_rebuilt_at,
            updated_at: refreshed.updated_at,
        };
    } catch {
        // A transient polling failure must not interrupt the user's work.
    }
};

const shouldPollMasterPdf = (): boolean =>
    masterPdfRebuildStatus.value === 'pending' ||
    (masterPdfRebuildStatus.value !== 'failed' && Date.now() < masterPdfVerificationUntil);

const scheduleMasterPdfPoll = (): void => {
    clearMasterPdfPollTimer();

    if (!shouldPollMasterPdf()) {
        return;
    }

    const requestedExpedienteId = expedienteId.value;
    const generation = masterPdfPollGeneration;

    if (!Number.isInteger(requestedExpedienteId) || requestedExpedienteId < 1) {
        return;
    }

    masterPdfPollTimer = setTimeout(async () => {
        masterPdfPollTimer = null;
        await refreshMasterPdfState(requestedExpedienteId, generation);

        if (
            generation === masterPdfPollGeneration &&
            expedienteId.value === requestedExpedienteId &&
            shouldPollMasterPdf()
        ) {
            scheduleMasterPdfPoll();
        }
    }, 3000);
};

const handleRetryMasterPdf = async (): Promise<void> => {
    try {
        retryingMasterPdf.value = true;
        await expedientesAPI.retryMasterPdf(expedienteId.value);

        if (expediente.value) {
            expediente.value = {
                ...expediente.value,
                master_pdf_rebuild_status: 'pending',
                master_pdf_rebuild_error: null,
            };
        }

        toast.success('La actualización del PDF se programó nuevamente.');
        scheduleMasterPdfPoll();
    } catch (error) {
        toast.error(
            await getApiErrorMessage(error, 'No se pudo reintentar la actualización del PDF.'),
        );
        await refetch();
    } finally {
        retryingMasterPdf.value = false;
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
    if (!masterPdfReady.value) {
        toast.error('El PDF consolidado todavía no está disponible.');
        return;
    }

    try {
        loading.value = true;
        const blob = await expedientesAPI.downloadFile(expedienteId.value);
        const filename = expediente.value?.nombre_archivo || 'documento.docx';
        downloadBlob(blob, filename);
        toast.success('Archivo descargado correctamente');
    } catch (error) {
        toast.error(await getApiErrorMessage(error, 'Error al descargar el archivo'));
    } finally {
        loading.value = false;
    }
};

const handleGeneratePdf = async (): Promise<void> => {
    if (!masterPdfReady.value) {
        toast.error('El PDF consolidado todavía no está disponible.');
        return;
    }

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

const openOnlyOfficeEditor = async (
    type: 'expediente' | 'resolucion',
    documentId: number,
): Promise<void> => {
    await router.push({
        name: 'onlyoffice-editor',
        params: {
            expedienteId: expedienteId.value,
            type,
            documentId,
        },
    });
};

const openSourceDocumentEditor = (): void => {
    void openOnlyOfficeEditor('expediente', expedienteId.value);
};

const openResolutionEditor = (resolucion: Resolucion): void => {
    void openOnlyOfficeEditor('resolucion', resolucion.id);
};

const openOnlineFinalization = (resolucion: Resolucion): void => {
    onlineFinalizationResolutionId.value = resolucion.id;
    onlineFinalizationResolutionNumber.value = resolucion.numero;
    onlineFinalizationError.value = null;
    showFinalizeOnlineModal.value = true;
};

const closeOnlineFinalization = (): void => {
    if (finalizingOnlineResolution.value) {
        return;
    }

    showFinalizeOnlineModal.value = false;
    onlineFinalizationError.value = null;
};

const handleFinalizeOnlineResolution = async (): Promise<void> => {
    const resolucionId = onlineFinalizationResolutionId.value;

    if (resolucionId == null) {
        onlineFinalizationError.value = 'No se pudo identificar la resolución pendiente.';
        return;
    }

    try {
        finalizingOnlineResolution.value = true;
        onlineFinalizationError.value = null;
        await expedientesAPI.completarResolucionOnline(expedienteId.value, resolucionId);
        const resolutionNumber = onlineFinalizationResolutionNumber.value;
        showFinalizeOnlineModal.value = false;
        onlineFinalizationResolutionId.value = null;
        onlineFinalizationResolutionNumber.value = null;
        toast.success(`Resolución ${resolutionNumber} finalizada e incorporada al expediente.`);
        await refetch();
    } catch (error) {
        const status = isAxiosError(error) ? error.response?.status : null;
        onlineFinalizationError.value =
            status === 409 || status === 422
                ? await getApiErrorMessage(
                      error,
                      'ONLYOFFICE aún está guardando; espere y reintente.',
                  )
                : await getApiErrorMessage(
                      error,
                      'No se pudo finalizar la resolución. Intente nuevamente.',
                  );
    } finally {
        finalizingOnlineResolution.value = false;
    }
};

const handleGenerateNextResolution = async (): Promise<void> => {
    if (!resolutionHistoryReady.value) {
        toast.error('El historial de resoluciones no está disponible. Vuelve a intentarlo.');
        return;
    }

    if (expediente.value?.ultima_resolucion == null) {
        showInitialResolutionModal.value = true;
        return;
    }

    if (pendingResolution.value) {
        openResolutionEditor(pendingResolution.value);
        return;
    }

    try {
        generatingResolution.value = true;
        const plantilla = await expedientesAPI.generarSiguienteResolucion(expedienteId.value);
        toast.success(`Resolución ${plantilla.numero} creada. Ya puede redactarla en el navegador.`);
        await openOnlyOfficeEditor('resolucion', plantilla.resolucionId);
    } catch (error) {
        console.error('Error generating next resolution', error);
        toast.error(
            await getApiErrorMessage(error, 'No se pudo generar la siguiente resolución.'),
        );
    } finally {
        generatingResolution.value = false;
    }
};

const handleDownloadPendingTemplate = async (): Promise<void> => {
    if (!pendingResolution.value) {
        toast.error('No hay una resolución pendiente para descargar.');
        return;
    }

    try {
        generatingResolution.value = true;
        const plantilla = await expedientesAPI.generarSiguienteResolucion(expedienteId.value);
        downloadBlob(plantilla.blob, plantilla.filename);
        toast.success(`Plantilla de la resolución ${plantilla.numero} descargada.`);
    } catch (error) {
        toast.error(
            await getApiErrorMessage(error, 'No se pudo descargar la plantilla de la resolución.'),
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

const hasEditableWordSource = (resolucion: Resolucion): boolean => {
    const fileName = resolucion.nombre_archivo?.trim().toLowerCase() ?? '';
    return fileName.endsWith('.doc') || fileName.endsWith('.docx');
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
        await router.push('/main');
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

watch(
    rawId,
    (id) => {
        if (!id) {
            refetchRequestId += 1;
            void router.push('/expedientes');
            return;
        }

        masterPdfVerificationUntil = 0;

        try {
            const verificationKey = `onlyoffice:verify-save:${id}`;
            const returnedAt = Number(sessionStorage.getItem(verificationKey));
            sessionStorage.removeItem(verificationKey);

            if (
                Number.isFinite(returnedAt) &&
                returnedAt > 0 &&
                Date.now() - returnedAt <= 2 * 60 * 1000
            ) {
                masterPdfVerificationUntil = Date.now() + 60 * 1000;
            }
        } catch {
            // The backend independently blocks stale downloads while the
            // ONLYOFFICE session or PDF rebuild remains active.
        }

        hasPromptedInitialResolution.value = false;
        showInitialResolutionModal.value = false;
        showCompleteResolutionModal.value = false;
        showFinalizeOnlineModal.value = false;
        void refetch();
    },
    { immediate: true },
);

watch(
    [rawId, () => route.query.editDocument],
    ([id, editDocument]) => {
        const requested = Array.isArray(editDocument) ? editDocument[0] : editDocument;
        const requestedId = Number(id);

        if (
            (requested === 'true' || requested === '1') &&
            Number.isInteger(requestedId) &&
            requestedId > 0
        ) {
            void router.replace({
                name: 'onlyoffice-editor',
                params: {
                    expedienteId: requestedId,
                    type: 'expediente',
                    documentId: requestedId,
                },
            });
        }
    },
    { immediate: true },
);

watch(
    [rawId, masterPdfRebuildStatus],
    () => {
        stopMasterPdfPolling();
        scheduleMasterPdfPoll();
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    refetchRequestId += 1;
    stopMasterPdfPolling();
});
</script>

<template>
    <div v-if="expedienteLoading" class="flex h-64 items-center justify-center">
        <div class="animate-pulse text-gray-500">Cargando expediente...</div>
    </div>

    <div v-else-if="!expediente" class="py-12 text-center">
        <Scale class="mx-auto mb-4 h-12 w-12 text-gray-400" />
        <p class="text-gray-500">Expediente no encontrado</p>
        <Button variant="outline" class="mt-4" @click="router.push('/main')">
            Volver al Menú
        </Button>
    </div>

    <div v-else class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <RouterLink
                    to="/main"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Volver al Menú
                </RouterLink>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Expediente #{{ expediente.numero }}</h1>
                    <p class="text-gray-600">{{ expediente.materia }}</p>
                </div>
            </div>
            <div class="flex space-x-3">
                <Button v-if="expediente.archivo" variant="primary" @click="openSourceDocumentEditor">
                    <template #icon>
                        <FileIcon class="h-4 w-4" />
                    </template>
                    Editar documento
                </Button>
                <Button variant="outline" @click="showEditModal = true">
                    <template #icon>
                        <Edit class="h-4 w-4" />
                    </template>
                    Editar datos
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

        <div
            v-if="masterPdfRebuildStatus === 'pending'"
            aria-live="polite"
            class="rounded-lg border border-amber-300 bg-amber-50 p-4"
        >
            <div class="flex items-start gap-3">
                <Clock class="mt-0.5 h-5 w-5 shrink-0 text-amber-700" />
                <div>
                    <p class="font-semibold text-amber-950">Actualizando el PDF consolidado</p>
                    <p class="mt-1 text-sm text-amber-900">
                        El documento Word ya se guardó. El sistema está preparando la versión actualizada para
                        verla y descargarla; esta pantalla se actualizará automáticamente.
                    </p>
                    <Button
                        v-if="masterPdfRebuildStale"
                        variant="outline"
                        size="sm"
                        class="mt-3"
                        :loading="retryingMasterPdf"
                        @click="handleRetryMasterPdf"
                    >
                        Reintentar actualización del PDF
                    </Button>
                </div>
            </div>
        </div>

        <div
            v-else-if="masterPdfRebuildStatus === 'failed'"
            role="alert"
            class="rounded-lg border border-red-300 bg-red-50 p-4"
        >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-semibold text-red-950">No se pudo actualizar el PDF consolidado</p>
                    <p class="mt-1 text-sm text-red-900">
                        {{
                            expediente.master_pdf_rebuild_error ||
                            'El documento Word quedó guardado y puede volver a intentar la actualización.'
                        }}
                    </p>
                </div>
                <Button
                    variant="outline"
                    class="shrink-0"
                    :loading="retryingMasterPdf"
                    @click="handleRetryMasterPdf"
                >
                    Reintentar actualización del PDF
                </Button>
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
                            <dt class="text-sm font-medium text-gray-500">Tercero</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ expediente.tercero || 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Demandado</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ expediente.demandado || 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Demandante</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ expediente.demandante || 'N/A' }}</dd>
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
                                                  : 'Pendiente de edición y finalización'
                                        }}
                                        •
                                        {{
                                            formatResolutionDate(
                                                resolucion.completada_at || resolucion.created_at,
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            resolucion.estado === 'pendiente' &&
                                            !resolucion.onlyoffice_saved_at
                                        "
                                        class="mt-1 text-sm font-medium text-gray-700"
                                    >
                                        Para finalizar en línea, cierre o guarde el editor y espere unos segundos.
                                    </p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <Button
                                    v-if="resolucion.estado === 'pendiente'"
                                    variant="primary"
                                    size="sm"
                                    @click="openResolutionEditor(resolucion)"
                                >
                                    <template #icon>
                                        <Edit class="h-4 w-4" />
                                    </template>
                                    Editar resolución
                                </Button>
                                <Button
                                    v-if="
                                        resolucion.estado === 'completada' &&
                                        hasEditableWordSource(resolucion)
                                    "
                                    variant="outline"
                                    size="sm"
                                    @click="openResolutionEditor(resolucion)"
                                >
                                    <template #icon>
                                        <Edit class="h-4 w-4" />
                                    </template>
                                    Editar
                                </Button>
                                <Button
                                    v-if="resolucion.estado !== 'pendiente' && resolucion.nombre_archivo"
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
                                    @click="openOnlineFinalization(resolucion)"
                                >
                                    <template #icon>
                                        <CheckCircle2 class="h-4 w-4" />
                                    </template>
                                    Finalizar resolución
                                </Button>
                                <Button
                                    v-if="resolucion.estado === 'pendiente'"
                                    variant="outline"
                                    size="sm"
                                    :loading="generatingResolution"
                                    @click="handleDownloadPendingTemplate"
                                >
                                    <template #icon>
                                        <Download class="h-4 w-4" />
                                    </template>
                                    Descargar plantilla
                                </Button>
                                <Button
                                    v-if="resolucion.estado === 'pendiente'"
                                    variant="outline"
                                    size="sm"
                                    @click="openPendingResolution(resolucion)"
                                >
                                    Subir Word terminado
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
                            :loading="generatingResolution"
                            :disabled="!resolutionHistoryReady"
                            class="w-full justify-start"
                            @click="handleGenerateNextResolution"
                        >
                            <template #icon>
                                <FilePlus2 class="h-4 w-4" />
                            </template>
                            <template v-if="resolucionesLoading">Cargando resoluciones...</template>
                            <template v-else-if="resolucionesError">Historial no disponible</template>
                            <template v-else-if="pendingResolution">
                                Editar resolución {{ pendingResolution.numero }}
                            </template>
                            <template v-else-if="expediente.ultima_resolucion == null">
                                Configurar resoluciones
                            </template>
                            <template v-else>
                                Crear resolución {{ nextResolutionNumber }}
                            </template>
                        </Button>

                        <Button
                            v-if="expediente.archivo"
                            variant="outline"
                            size="lg"
                            class="w-full justify-start"
                            @click="openSourceDocumentEditor"
                        >
                            <template #icon>
                                <Edit class="h-5 w-5" />
                            </template>
                            Editar documento en línea
                        </Button>

                        <Button
                            v-if="expediente.archivo"
                            variant="outline"
                            :loading="loading"
                            :disabled="!masterPdfReady"
                            :title="
                                masterPdfReady
                                    ? 'Descargar el documento consolidado'
                                    : 'Espere a que termine la actualización del PDF consolidado'
                            "
                            class="w-full justify-start"
                            @click="handleDownloadFile"
                        >
                            <template #icon>
                                <Download class="h-4 w-4" />
                            </template>
                            <template v-if="masterPdfRebuildStatus === 'pending'">
                                PDF actualizándose...
                            </template>
                            <template v-else-if="masterPdfRebuildStatus === 'failed'">
                                PDF no disponible
                            </template>
                            <template v-else>Descargar archivo</template>
                        </Button>

                        <Button
                            v-if="expediente.archivo"
                            variant="outline"
                            :loading="isGenerating"
                            :disabled="!masterPdfReady"
                            :title="
                                masterPdfReady
                                    ? 'Generar la vista PDF del expediente'
                                    : 'Espere a que termine la actualización del PDF consolidado'
                            "
                            class="w-full justify-start"
                            @click="handleGeneratePdf"
                        >
                            <template #icon>
                                <FileIcon class="h-4 w-4" />
                            </template>
                            Ver o generar PDF
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
            @close="showInitialResolutionModal = false"
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
                    <Button variant="outline" @click="showInitialResolutionModal = false">Ahora no</Button>
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
                    Si redactó la resolución fuera del sistema, seleccione o arrastre aquí el documento Word
                    terminado. El número del expediente se actualizará únicamente cuando la carga finalice
                    correctamente.
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
            :open="showFinalizeOnlineModal"
            :title="`Finalizar Resolución N.º ${onlineFinalizationResolutionNumber ?? ''}`"
            size="md"
            @close="closeOnlineFinalization"
        >
            <div class="space-y-5">
                <div class="rounded-lg border border-gray-300 bg-gray-50 p-4 text-base text-gray-800">
                    <template v-if="!onlineFinalizationResolution?.onlyoffice_saved_at">
                        Cierre o guarde el editor y espere unos segundos para que ONLYOFFICE confirme el
                        guardado. Si todavía está procesando, podrá reintentar sin perder el documento.
                    </template>
                    <template v-else>
                        ONLYOFFICE ya confirmó el guardado. Al finalizar, el documento se incorporará al
                        expediente y se actualizará el PDF consolidado.
                    </template>
                </div>

                <p class="font-semibold text-gray-950">
                    ¿Confirma que terminó de redactar y revisar esta resolución?
                </p>

                <div
                    v-if="onlineFinalizationError"
                    class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800"
                    role="alert"
                >
                    {{ onlineFinalizationError }}
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button
                        variant="outline"
                        size="lg"
                        :disabled="finalizingOnlineResolution"
                        @click="closeOnlineFinalization"
                    >
                        Cancelar
                    </Button>
                    <Button
                        size="lg"
                        :loading="finalizingOnlineResolution"
                        @click="handleFinalizeOnlineResolution"
                    >
                        Sí, finalizar resolución
                    </Button>
                </div>
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
