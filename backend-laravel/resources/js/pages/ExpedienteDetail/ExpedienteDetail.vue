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
        <div class="flex items-center gap-3 rounded-xl border border-indigo-100 bg-indigo-50 px-5 py-4 text-indigo-800">
            <Clock class="h-5 w-5 animate-pulse" />
            <span class="font-medium">Estamos preparando el expediente...</span>
        </div>
    </div>

    <div v-else-if="!expediente" class="py-16 text-center">
        <div class="mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <Scale class="mx-auto mb-4 h-12 w-12 text-slate-400" />
            <h1 class="text-xl font-bold text-slate-900">No encontramos este expediente</h1>
            <p class="mt-2 text-sm text-slate-600">
                Puede volver a la lista para buscarlo nuevamente.
            </p>
            <Button variant="outline" size="lg" class="mt-6" @click="router.push('/expedientes')">
                <template #icon>
                    <ArrowLeft class="h-5 w-5" />
                </template>
                Volver a expedientes
            </Button>
        </div>
    </div>

    <div v-else class="mx-auto max-w-[1500px] space-y-6 pb-10">
        <header class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-5 px-6 py-6 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <RouterLink
                        to="/expedientes"
                        class="inline-flex shrink-0 items-center rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        <ArrowLeft class="mr-2 h-5 w-5" />
                        Volver a expedientes
                    </RouterLink>
                    <div class="min-w-0 pt-1">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-indigo-700">Vista guiada</p>
                        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950">
                            Expediente N.º {{ expediente.numero }}
                        </h1>
                        <p class="mt-1 text-base text-slate-600">{{ expediente.materia }}</p>
                    </div>
                </div>

                <Button variant="outline" size="lg" class="shrink-0" @click="showEditModal = true">
                    <template #icon>
                        <Edit class="h-5 w-5" />
                    </template>
                    Corregir datos del expediente
                </Button>
            </div>

            <div class="grid border-t border-slate-200 bg-slate-50 md:grid-cols-3">
                <div class="flex items-center gap-3 px-6 py-4 md:border-r md:border-slate-200">
                    <div class="rounded-full bg-indigo-100 p-2 text-indigo-700">
                        <Clock class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado actual</p>
                        <p class="font-bold text-slate-900">{{ expediente.estado || 'En proceso' }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-6 py-4 md:border-r md:border-slate-200">
                    <div class="rounded-full bg-emerald-100 p-2 text-emerald-700">
                        <CheckCircle2 class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Última resolución</p>
                        <p class="font-bold text-slate-900">{{ lastResolutionLabel }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-6 py-4">
                    <div class="rounded-full bg-amber-100 p-2 text-amber-700">
                        <FileIcon class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Última actualización</p>
                        <p class="font-bold text-slate-900">{{ formattedUpdatedAt }}</p>
                    </div>
                </div>
            </div>
        </header>

        <section aria-labelledby="workflow-heading" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex items-center gap-3">
                <div class="rounded-xl bg-violet-100 p-2 text-violet-700">
                    <Scale class="h-6 w-6" />
                </div>
                <div>
                    <h2 id="workflow-heading" class="text-xl font-bold text-slate-950">Guía rápida del expediente</h2>
                    <p class="text-sm text-slate-600">
                        Cada grupo reúne una tarea concreta. El sistema le indica cuál conviene hacer ahora.
                    </p>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <div class="flex items-center justify-between">
                        <span class="rounded-full bg-emerald-600 px-2.5 py-1 text-xs font-black text-white">01</span>
                        <CheckCircle2 class="h-5 w-5 text-emerald-700" />
                    </div>
                    <p class="mt-3 font-bold text-emerald-950">Datos del expediente</p>
                    <p class="mt-1 text-sm text-emerald-800">Registrados y disponibles para revisar.</p>
                </div>

                <div
                    class="rounded-xl border p-4"
                    :class="expediente.archivo ? 'border-cyan-200 bg-cyan-50' : 'border-amber-200 bg-amber-50'"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-black text-white"
                            :class="expediente.archivo ? 'bg-cyan-600' : 'bg-amber-500'"
                        >
                            02
                        </span>
                        <FileIcon :class="expediente.archivo ? 'text-cyan-700' : 'text-amber-700'" class="h-5 w-5" />
                    </div>
                    <p class="mt-3 font-bold text-slate-950">Documento inicial</p>
                    <p class="mt-1 text-sm text-slate-700">
                        {{ expediente.archivo ? 'Archivo listo para usar.' : 'Falta cargar el archivo base.' }}
                    </p>
                </div>

                <div
                    class="rounded-xl border p-4"
                    :class="pendingResolution ? 'border-amber-200 bg-amber-50' : 'border-violet-200 bg-violet-50'"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-black text-white"
                            :class="pendingResolution ? 'bg-amber-500' : 'bg-violet-600'"
                        >
                            03
                        </span>
                        <Clock v-if="pendingResolution" class="h-5 w-5 text-amber-700" />
                        <FilePlus2 v-else class="h-5 w-5 text-violet-700" />
                    </div>
                    <p class="mt-3 font-bold text-slate-950">Resoluciones</p>
                    <p class="mt-1 text-sm text-slate-700">
                        <template v-if="resolucionesLoading">Estamos revisando el historial.</template>
                        <template v-else-if="resolucionesError">El historial necesita reintentarse.</template>
                        <template v-else-if="pendingResolution">
                            La resolución {{ pendingResolution.numero }} está pendiente.
                        </template>
                        <template v-else-if="expediente.ultima_resolucion == null">
                            Falta confirmar la numeración inicial.
                        </template>
                        <template v-else>Lista para la próxima resolución.</template>
                    </p>
                </div>

                <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                    <div class="flex items-center justify-between">
                        <span class="rounded-full bg-indigo-600 px-2.5 py-1 text-xs font-black text-white">04</span>
                        <Download class="h-5 w-5 text-indigo-700" />
                    </div>
                    <p class="mt-3 font-bold text-indigo-950">Descargas y cierre</p>
                    <p class="mt-1 text-sm text-indigo-800">
                        Genere el PDF o descargue los documentos cuando los necesite.
                    </p>
                </div>
            </div>
        </section>

        <section aria-live="polite" class="overflow-hidden rounded-2xl border border-indigo-300 bg-indigo-50 shadow-sm">
            <div class="flex flex-col gap-5 p-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="rounded-xl bg-indigo-600 p-3 text-white shadow-sm">
                        <FilePlus2 class="h-7 w-7" />
                    </div>
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-indigo-700">Siguiente paso recomendado</p>
                        <h2 class="mt-1 text-2xl font-bold text-indigo-950">
                            <template v-if="!resolutionHistoryReady">Espere mientras comprobamos las resoluciones</template>
                            <template v-else-if="expediente.ultima_resolucion == null">
                                Confirme cuál fue la última resolución
                            </template>
                            <template v-else-if="!expediente.archivo && sortedResoluciones.length === 0">
                                Suba el documento inicial del expediente
                            </template>
                            <template v-else-if="pendingResolution">
                                Termine la Resolución N.º {{ pendingResolution.numero }}
                            </template>
                            <template v-else>Prepare la Resolución N.º {{ nextResolutionNumber }}</template>
                        </h2>
                        <p class="mt-1 max-w-3xl text-sm text-indigo-800">
                            <template v-if="!resolutionHistoryReady">
                                No cierre esta pantalla: habilitaremos las acciones apenas el historial esté listo.
                            </template>
                            <template v-else-if="expediente.ultima_resolucion == null">
                                Verifique el número para que las próximas resoluciones mantengan el orden correcto.
                            </template>
                            <template v-else-if="!expediente.archivo && sortedResoluciones.length === 0">
                                Cargue el documento Word, PDF o DOC que servirá como base para este expediente.
                            </template>
                            <template v-else-if="pendingResolution">
                                Puede continuar editándola aquí o subir el Word que ya terminó fuera del sistema.
                            </template>
                            <template v-else>
                                Abra el editor para redactarla dentro del sistema, o descargue una plantilla Word.
                            </template>
                        </p>
                    </div>
                </div>

                <div class="shrink-0">
                    <Button
                        v-if="resolutionHistoryReady && expediente.ultima_resolucion == null"
                        variant="primary"
                        size="lg"
                        class="w-full min-w-[280px] shadow-md lg:w-auto"
                        @click="showInitialResolutionModal = true"
                    >
                        <template #icon>
                            <CheckCircle2 class="h-5 w-5" />
                        </template>
                        Confirmar última resolución
                    </Button>
                    <Button
                        v-else-if="resolutionHistoryReady && !expediente.archivo && sortedResoluciones.length === 0"
                        variant="primary"
                        size="lg"
                        class="w-full min-w-[280px] shadow-md lg:w-auto"
                        @click="showUploadModal = true"
                    >
                        <template #icon>
                            <Upload class="h-5 w-5" />
                        </template>
                        Subir documento inicial
                    </Button>
                    <Button
                        v-else-if="resolutionHistoryReady"
                        variant="primary"
                        size="lg"
                        :loading="openingResolutionEditor"
                        :disabled="generatingResolution"
                        class="w-full min-w-[280px] shadow-md lg:w-auto"
                        @click="handleOpenResolutionEditor"
                    >
                        <template #icon>
                            <Edit class="h-5 w-5" />
                        </template>
                        {{ pendingResolution ? `Continuar Resolución ${pendingResolution.numero}` : `Abrir Resolución ${nextResolutionNumber}` }}
                    </Button>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <main class="space-y-6 xl:col-span-2">
                <section aria-labelledby="step-data-heading" class="overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-4 border-b border-emerald-200 bg-emerald-50 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-600 text-sm font-black text-white">1</span>
                            <div>
                                <h2 id="step-data-heading" class="text-xl font-bold text-emerald-950">Paso 1. Revise los datos</h2>
                                <p class="text-sm text-emerald-800">Compruebe que esta información identifica correctamente el expediente.</p>
                            </div>
                        </div>
                        <Button variant="outline" size="lg" class="shrink-0" @click="showEditModal = true">
                            <template #icon>
                                <Edit class="h-5 w-5" />
                            </template>
                            Editar datos
                        </Button>
                    </div>

                    <div class="p-6">
                        <dl class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Número de expediente</dt>
                                <dd class="mt-2 text-lg font-bold text-slate-950">{{ expediente.numero }}</dd>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Materia</dt>
                                <dd class="mt-2 text-base font-semibold text-slate-900">{{ expediente.materia }}</dd>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Juzgado</dt>
                                <dd class="mt-2 text-base font-semibold text-slate-900">{{ expediente.juzgado }}</dd>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <dt class="flex items-center text-xs font-bold uppercase tracking-wide text-slate-500">
                                    <User class="mr-1.5 h-4 w-4" />
                                    Especialista
                                </dt>
                                <dd class="mt-2 text-base font-semibold text-slate-900">{{ expediente.especialista }}</dd>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Terceros</dt>
                                <dd class="mt-2 whitespace-pre-line text-base text-slate-900">{{ expediente.tercero || 'No registrado' }}</dd>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Demandados</dt>
                                <dd class="mt-2 whitespace-pre-line text-base text-slate-900">{{ expediente.demandado || 'No registrado' }}</dd>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 md:col-span-2">
                                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Demandantes</dt>
                                <dd class="mt-2 whitespace-pre-line text-base text-slate-900">{{ expediente.demandante || 'No registrado' }}</dd>
                            </div>
                        </dl>

                        <div class="mt-5 flex flex-col gap-4 rounded-xl border border-indigo-200 bg-indigo-50 p-5 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="text-sm font-bold text-indigo-950">Estado o nota actual</p>
                                <p class="mt-1 whitespace-pre-line text-sm text-indigo-900">
                                    {{ expediente.estado || 'Aún no se ha registrado un estado para este expediente.' }}
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                class="shrink-0"
                                @click="statusText = expediente.estado || ''; showUpdateStatusModal = true"
                            >
                                <template #icon>
                                    <Edit class="h-4 w-4" />
                                </template>
                                Actualizar estado
                            </Button>
                        </div>
                    </div>
                </section>

                <section aria-labelledby="step-document-heading" class="overflow-hidden rounded-2xl border border-cyan-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-4 border-b border-cyan-200 bg-cyan-50 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-cyan-600 text-sm font-black text-white">2</span>
                            <div>
                                <h2 id="step-document-heading" class="text-xl font-bold text-cyan-950">Paso 2. Documento inicial</h2>
                                <p class="text-sm text-cyan-800">Es el archivo base que acompaña este expediente.</p>
                            </div>
                        </div>
                        <span
                            class="inline-flex w-fit items-center rounded-full px-3 py-1.5 text-sm font-bold"
                            :class="expediente.archivo ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                        >
                            <CheckCircle2 v-if="expediente.archivo" class="mr-1.5 h-4 w-4" />
                            <Clock v-else class="mr-1.5 h-4 w-4" />
                            {{ expediente.archivo ? 'Archivo disponible' : 'Archivo pendiente' }}
                        </span>
                    </div>

                    <div class="p-6">
                        <div
                            class="flex flex-col gap-5 rounded-xl border p-5 lg:flex-row lg:items-center lg:justify-between"
                            :class="expediente.archivo ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="rounded-xl p-3"
                                    :class="expediente.archivo ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white'"
                                >
                                    <FileIcon class="h-6 w-6" />
                                </div>
                                <div>
                                    <p class="text-base font-bold text-slate-950">
                                        {{ expediente.archivo ? 'Documento inicial cargado' : 'Todavía no hay documento inicial' }}
                                    </p>
                                    <p class="mt-1 text-sm text-slate-700">
                                        <template v-if="expediente.archivo">
                                            {{ expediente.nombre_archivo || 'Archivo disponible para el expediente.' }}
                                        </template>
                                        <template v-else>
                                            Cargue el archivo para contar con una base documental clara.
                                        </template>
                                    </p>
                                </div>
                            </div>

                            <Button
                                v-if="resolutionHistoryReady && sortedResoluciones.length === 0"
                                variant="primary"
                                size="lg"
                                class="shrink-0"
                                @click="showUploadModal = true"
                            >
                                <template #icon>
                                    <Upload class="h-5 w-5" />
                                </template>
                                {{ expediente.archivo ? 'Reemplazar documento inicial' : 'Subir documento inicial' }}
                            </Button>
                        </div>

                        <p
                            v-if="resolutionHistoryReady && sortedResoluciones.length === 0"
                            class="mt-3 text-sm text-slate-600"
                        >
                            Puede cambiar este documento antes de registrar resoluciones individuales.
                        </p>
                        <p v-else-if="resolucionesLoading" class="mt-3 text-sm text-slate-600">
                            Estamos comprobando si el documento puede actualizarse.
                        </p>
                        <p v-else-if="resolucionesError" class="mt-3 text-sm text-red-700">
                            Necesitamos recuperar el historial antes de modificar el documento inicial.
                        </p>
                    </div>
                </section>

                <section aria-labelledby="step-resolution-heading" class="overflow-hidden rounded-2xl border border-violet-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-4 border-b border-violet-200 bg-violet-50 px-6 py-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-violet-600 text-sm font-black text-white">3</span>
                                <div>
                                    <h2 id="step-resolution-heading" class="text-xl font-bold text-violet-950">Paso 3. Trabaje las resoluciones</h2>
                                    <p class="text-sm text-violet-800">Cree, continúe o incorpore una resolución sin perder el orden.</p>
                                </div>
                            </div>
                            <span class="inline-flex w-fit rounded-full bg-white px-3 py-1.5 text-sm font-bold text-violet-800 shadow-sm ring-1 ring-violet-200">
                                Última resolución: {{ lastResolutionLabel }}
                            </span>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <Button
                                variant="primary"
                                size="lg"
                                :loading="openingResolutionEditor"
                                :disabled="!resolutionHistoryReady || generatingResolution"
                                @click="handleOpenResolutionEditor"
                            >
                                <template #icon>
                                    <Edit class="h-5 w-5" />
                                </template>
                                <template v-if="resolucionesLoading">Cargando resoluciones...</template>
                                <template v-else-if="resolucionesError">Historial no disponible</template>
                                <template v-else-if="pendingResolution">
                                    Continuar Resolución N.º {{ pendingResolution.numero }}
                                </template>
                                <template v-else-if="expediente.ultima_resolucion == null">
                                    Confirmar numeración inicial
                                </template>
                                <template v-else>
                                    Redactar Resolución N.º {{ nextResolutionNumber }}
                                </template>
                            </Button>

                            <Button
                                v-if="resolutionHistoryReady && expediente.ultima_resolucion != null"
                                variant="outline"
                                size="lg"
                                :loading="generatingResolution"
                                :disabled="openingResolutionEditor"
                                @click="handleDownloadResolutionTemplate"
                            >
                                <template #icon>
                                    <Download class="h-5 w-5" />
                                </template>
                                Descargar Word de la Resolución N.º {{ pendingResolution?.numero ?? nextResolutionNumber }}
                            </Button>

                            <Button
                                v-if="pendingResolution"
                                variant="outline"
                                size="lg"
                                :disabled="openingResolutionEditor || generatingResolution"
                                @click="openPendingResolution(pendingResolution)"
                            >
                                <template #icon>
                                    <Upload class="h-5 w-5" />
                                </template>
                                Subir Word terminado
                            </Button>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="mb-4 flex flex-col gap-2 rounded-xl bg-slate-50 p-4 text-sm text-slate-700 lg:flex-row lg:items-center lg:justify-between">
                            <p>
                                <strong class="text-slate-950">Cómo continuar:</strong>
                                use “Redactar” para trabajar aquí, o descargue el Word para editarlo fuera y súbalo al terminar.
                            </p>
                            <span class="shrink-0 font-bold text-slate-900">Historial de resoluciones</span>
                        </div>

                        <div
                            v-if="resolucionesLoading"
                            class="rounded-xl border border-slate-200 bg-slate-50 px-5 py-10 text-center text-sm text-slate-600"
                        >
                            <Clock class="mx-auto mb-3 h-6 w-6 animate-pulse text-violet-600" />
                            Cargando el historial de resoluciones...
                        </div>

                        <div
                            v-else-if="resolucionesError"
                            class="rounded-xl border border-red-200 bg-red-50 px-5 py-7 text-center"
                        >
                            <p class="font-bold text-red-900">No pudimos cargar el historial de resoluciones.</p>
                            <p class="mt-1 text-sm text-red-800">{{ resolucionesError }}</p>
                            <Button variant="outline" size="lg" class="mt-5" @click="refetch">
                                <template #icon>
                                    <Clock class="h-5 w-5" />
                                </template>
                                Intentar nuevamente
                            </Button>
                        </div>

                        <div v-else-if="sortedResoluciones.length" class="space-y-3">
                            <article
                                v-for="resolucion in sortedResoluciones"
                                :key="resolucion.id"
                                class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 lg:flex-row lg:items-center lg:justify-between"
                            >
                                <div class="flex items-start gap-4">
                                    <div
                                        class="rounded-xl p-3"
                                        :class="
                                            resolucion.estado === 'completada'
                                                ? 'bg-emerald-100 text-emerald-700'
                                                : resolucion.estado === 'base'
                                                  ? 'bg-cyan-100 text-cyan-700'
                                                  : 'bg-amber-100 text-amber-700'
                                        "
                                    >
                                        <CheckCircle2 v-if="resolucion.estado === 'completada'" class="h-6 w-6" />
                                        <FileIcon v-else-if="resolucion.estado === 'base'" class="h-6 w-6" />
                                        <Clock v-else class="h-6 w-6" />
                                    </div>
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-lg font-bold text-slate-950">
                                                {{
                                                    resolucion.estado === 'base' && resolucion.numero === 0
                                                        ? 'Documento base (sin resoluciones)'
                                                        : `Resolución N.º ${resolucion.numero}`
                                                }}
                                            </h3>
                                            <span
                                                class="rounded-full px-2.5 py-1 text-xs font-bold"
                                                :class="
                                                    resolucion.estado === 'completada'
                                                        ? 'bg-emerald-100 text-emerald-800'
                                                        : resolucion.estado === 'base'
                                                          ? 'bg-cyan-100 text-cyan-800'
                                                          : 'bg-amber-100 text-amber-800'
                                                "
                                            >
                                                {{
                                                    resolucion.estado === 'completada'
                                                        ? 'Completada'
                                                        : resolucion.estado === 'base'
                                                          ? 'Documento base'
                                                          : 'Pendiente'
                                                }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-sm text-slate-600">
                                            {{
                                                resolucion.estado === 'completada'
                                                    ? resolucion.nombre_archivo || 'Documento incorporado'
                                                    : resolucion.estado === 'base'
                                                      ? 'Documento original usado como base del expediente'
                                                      : 'Falta editar o subir el Word terminado'
                                            }}
                                        </p>
                                        <p class="mt-1 text-xs font-medium text-slate-500">
                                            Fecha: {{ formatResolutionDate(resolucion.completada_at || resolucion.created_at) }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                    <Button
                                        v-if="resolucion.estado === 'pendiente'"
                                        variant="primary"
                                        :loading="openingResolutionEditor"
                                        :disabled="generatingResolution"
                                        @click="handleOpenResolutionEditor"
                                    >
                                        <template #icon>
                                            <Edit class="h-4 w-4" />
                                        </template>
                                        Abrir editor
                                    </Button>
                                    <Button
                                        v-if="resolucion.estado === 'pendiente'"
                                        variant="outline"
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
                                        :loading="downloadingResolutionId === resolucion.id"
                                        @click="handleDownloadResolution(resolucion)"
                                    >
                                        <template #icon>
                                            <Download class="h-4 w-4" />
                                        </template>
                                        Descargar resolución
                                    </Button>
                                    <Button
                                        v-if="resolucion.estado === 'pendiente'"
                                        variant="outline"
                                        @click="openPendingResolution(resolucion)"
                                    >
                                        <template #icon>
                                            <Upload class="h-4 w-4" />
                                        </template>
                                        Subir Word terminado
                                    </Button>
                                </div>
                            </article>
                        </div>

                        <div v-else class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                            <FilePlus2 class="mx-auto mb-3 h-8 w-8 text-violet-600" />
                            <p class="font-bold text-slate-900">Aún no hay resoluciones individuales</p>
                            <p class="mt-1 text-sm text-slate-600">Use el botón de arriba para preparar la primera.</p>
                        </div>
                    </div>
                </section>
            </main>

            <aside class="space-y-6 xl:sticky xl:top-6 xl:self-start">
                <section aria-labelledby="step-download-heading" class="overflow-hidden rounded-2xl border border-indigo-200 bg-white shadow-sm">
                    <div class="border-b border-indigo-200 bg-indigo-50 px-6 py-5">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-600 text-sm font-black text-white">4</span>
                            <div>
                                <h2 id="step-download-heading" class="text-xl font-bold text-indigo-950">Descargas y cierre</h2>
                                <p class="text-sm text-indigo-800">Prepare los documentos para revisarlos o entregarlos.</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 p-6">
                        <div v-if="expediente.archivo" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                            <div class="flex items-start gap-3">
                                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-700" />
                                <div>
                                    <p class="font-bold text-emerald-950">Documento inicial disponible</p>
                                    <p class="mt-1 break-words text-sm text-emerald-800">
                                        {{ expediente.nombre_archivo || 'Documento del expediente' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div v-else class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <div class="flex items-start gap-3">
                                <Clock class="mt-0.5 h-5 w-5 shrink-0 text-amber-700" />
                                <p class="text-sm text-amber-900">Cuando cargue el documento inicial, las descargas estarán disponibles aquí.</p>
                            </div>
                        </div>

                        <Button
                            v-if="expediente.archivo"
                            variant="outline"
                            size="lg"
                            :loading="loading"
                            class="w-full justify-start"
                            @click="handleDownloadFile"
                        >
                            <template #icon>
                                <Download class="h-5 w-5" />
                            </template>
                            Descargar documento inicial
                        </Button>

                        <Button
                            v-if="expediente.archivo"
                            variant="primary"
                            size="lg"
                            :loading="isGenerating"
                            class="w-full justify-start"
                            @click="handleGeneratePdf"
                        >
                            <template #icon>
                                <FileIcon class="h-5 w-5" />
                            </template>
                            Generar PDF del expediente
                        </Button>

                        <p class="text-xs leading-5 text-slate-500">
                            Las resoluciones individuales se descargan directamente desde su historial, en el paso 3.
                        </p>
                    </div>
                </section>

                <section aria-labelledby="delete-heading" class="rounded-2xl border-2 border-red-300 bg-red-50 p-6 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="rounded-xl bg-red-600 p-2.5 text-white">
                            <Trash2 class="h-6 w-6" />
                        </div>
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.14em] text-red-700">Acción irreversible</p>
                            <h2 id="delete-heading" class="mt-1 text-xl font-bold text-red-950">Eliminar expediente</h2>
                            <p class="mt-2 text-sm leading-5 text-red-900">
                                Use esta opción solo si está completamente seguro. No podrá recuperar los datos ni documentos.
                            </p>
                        </div>
                    </div>
                    <Button variant="danger" size="lg" class="mt-5 w-full" @click="showDeleteConfirm = true">
                        <template #icon>
                            <Trash2 class="h-5 w-5" />
                        </template>
                        Eliminar este expediente
                    </Button>
                </section>
            </aside>
        </div>

        <Modal
            :open="showEditModal"
            title="Paso 1: corregir los datos del expediente"
            size="xl"
            @close="showEditModal = false"
        >
            <ExpedienteForm
                :expediente="expediente"
                @success="handleEditSuccess"
                @cancel="showEditModal = false"
            />
        </Modal>

        <Modal
            :open="showUploadModal"
            :title="expediente.archivo ? 'Paso 2: reemplazar documento inicial' : 'Paso 2: subir documento inicial'"
            size="md"
            @close="showUploadModal = false"
        >
            <div class="space-y-4">
                <div class="rounded-xl border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-950">
                    Seleccione el archivo que servirá como documento base del expediente. Se aceptan archivos PDF, DOC y DOCX.
                </div>
                <FileUploader
                    :on-upload="handleFileUpload"
                    accept=".pdf,.doc,.docx"
                    :loading="loading"
                />
            </div>
        </Modal>

        <Modal
            :open="showInitialResolutionModal"
            title="Antes de continuar: confirme la última resolución"
            size="md"
            @close="closeInitialResolutionModal"
        >
            <div class="space-y-5">
                <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-950">
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
                        class="w-full rounded-lg border border-slate-300 px-4 py-3 text-lg font-semibold shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p class="mt-2 text-sm text-slate-600">Use 0 si todavía no existe ninguna resolución.</p>
                </div>

                <div class="flex justify-end space-x-3">
                    <Button variant="outline" size="lg" @click="closeInitialResolutionModal">Ahora no</Button>
                    <Button
                        variant="primary"
                        size="lg"
                        :loading="initialResolutionLoading"
                        @click="handleConfirmInitialResolution"
                    >
                        <template #icon>
                            <CheckCircle2 class="h-5 w-5" />
                        </template>
                        Confirmar número
                    </Button>
                </div>
            </div>
        </Modal>

        <Modal
            :open="showCompleteResolutionModal"
            :title="`Paso 3: completar la Resolución N.º ${completionResolutionNumber ?? ''}`"
            size="md"
            @close="showCompleteResolutionModal = false"
        >
            <div class="space-y-5">
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    La plantilla ya fue descargada. Termine de redactarla y arrastre aquí el documento Word. El
                    número del expediente se actualizará únicamente cuando la carga finalice correctamente.
                </div>

                <FileUploader
                    :on-upload="handleCompleteResolution"
                    accept=".doc,.docx"
                    :loading="completingResolution || !resolutionHistoryReady"
                />

                <p class="text-xs text-gray-500">
                    Al finalizar, guardaremos esta resolución en el historial y actualizaremos el expediente consolidado.
                </p>
            </div>
        </Modal>

        <Modal
            :open="showUpdateStatusModal"
            title="Actualizar estado o nota del expediente"
            size="md"
            @close="showUpdateStatusModal = false"
        >
            <div class="space-y-4">
                <p class="text-sm text-slate-700">
                    Escriba una nota breve sobre la situación actual. Esta información se verá en la parte superior del expediente.
                </p>
                <textarea
                    v-model="statusText"
                    rows="6"
                    class="w-full rounded-lg border border-slate-300 p-3 text-base focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                ></textarea>
                <div class="flex justify-end space-x-3">
                    <Button variant="outline" size="lg" @click="showUpdateStatusModal = false">Cancelar</Button>
                    <Button variant="primary" size="lg" :loading="statusLoading" @click="handleUpdateStatus">
                        <template #icon>
                            <CheckCircle2 class="h-5 w-5" />
                        </template>
                        Guardar estado
                    </Button>
                </div>
            </div>
        </Modal>

        <Modal
            :open="showDeleteConfirm"
            title="Eliminar expediente de forma permanente"
            size="md"
            @close="showDeleteConfirm = false"
        >
            <div class="space-y-5">
                <div class="flex items-center space-x-3 rounded-xl border border-red-200 bg-red-50 p-4">
                    <div class="flex-shrink-0">
                        <Trash2 class="h-6 w-6 text-red-600" />
                    </div>
                    <div>
                        <p class="font-bold text-red-950">¿Está seguro que desea eliminar este expediente?</p>
                        <p class="mt-1 text-sm text-red-800">Esta acción no se puede deshacer.</p>
                    </div>
                </div>
                <div class="rounded-xl border border-red-200 bg-white p-4">
                    <p class="text-sm text-red-800">
                        <strong>Expediente:</strong> #{{ expediente.numero }} - {{ expediente.materia }}
                    </p>
                </div>
                <div class="flex justify-end space-x-3">
                    <Button variant="outline" size="lg" @click="showDeleteConfirm = false">Cancelar</Button>
                    <Button variant="danger" size="lg" :loading="loading" @click="handleDelete">
                        <template #icon>
                            <Trash2 class="h-5 w-5" />
                        </template>
                        Eliminar Expediente
                    </Button>
                </div>
            </div>
        </Modal>
    </div>
</template>
