<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';

import { expedientesAPI, type Expediente, type Resolucion } from '@/api';
import ExpedienteForm from '@/components/ExpedienteForm/ExpedienteForm.vue';
import FileUploader from '@/components/FileUploader/FileUploader.vue';
import Button from '@/components/UI/Button.vue';
import Modal from '@/components/UI/Modal.vue';
import { useToast } from '@/composables/useToast';
import { getApiErrorMessage } from '@/utils/apiError';
import { downloadBlob } from '@/utils/fileDownload';
import { isValidPdfBlob } from '@/utils/pdf';
import ExpedienteDocument from '@/components/ExpedienteDocument.vue';
import DeleteExpedienteModal from '@/components/DeleteExpedienteModal.vue';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const showEditModal = ref(false);
const showUploadModal = ref(false);
const showDeleteConfirm = ref(false);
const documentMode = ref<'view' | 'print' | null>(null);
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

        const detected = Number(expedienteResponse.resolucion_detectada ?? 0);
        initialResolutionNumber.value = Number.isInteger(detected) && detected >= 0 ? detected : 0;
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
  <section v-if="expedienteLoading" class="simple-page" role="status">Buscando expediente…</section>
  <section v-else-if="!expediente" class="simple-page">
    <h1>No se pudo abrir el expediente</h1>
    <p>Vuelva a buscar por el número completo.</p>
    <RouterLink class="task-button" to="/expedientes">Buscar expediente</RouterLink>
  </section>
  <section v-else class="simple-page record-page">
    <RouterLink class="plain-button" to="/expedientes">Buscar otro expediente</RouterLink>
    <h1>Expediente N.º {{ expediente.numero }}</h1>
    <p v-if="expediente.materia">{{ expediente.materia }}</p>

    <div class="record-actions">
      <button type="button" class="task-button" @click="documentMode = 'view'">Ver documento del expediente</button>
      <button
        type="button" class="task-button"
        :disabled="!resolutionHistoryReady || openingResolutionEditor || generatingResolution"
        @click="handleOpenResolutionEditor"
      >{{ openingResolutionEditor ? 'Abriendo editor…' : 'Actualizar expediente' }}</button>
      <button type="button" class="task-button" @click="showDeleteConfirm = true">Eliminar expediente</button>
      <button type="button" class="task-button" @click="documentMode = 'print'">Imprimir expediente</button>
    </div>
    <p v-if="pendingResolution">Resolución N.º {{ pendingResolution.numero }} pendiente. Pulse “Actualizar expediente” para continuar.</p>
    <p v-if="resolucionesError" role="alert" class="plain-notice">
      {{ resolucionesError }}
      <button type="button" class="plain-button" @click="refetch">Intentar nuevamente</button>
    </p>

    <section class="plain-section" aria-labelledby="record-data">
      <h2 id="record-data">Datos del expediente</h2>
      <dl class="record-data">
        <dt>Número</dt><dd>{{ expediente.numero }}</dd>
        <dt>Materia</dt><dd>{{ expediente.materia || 'Sin registrar' }}</dd>
        <dt>Juzgado</dt><dd>{{ expediente.juzgado || 'Sin registrar' }}</dd>
        <dt>Especialista</dt><dd>{{ expediente.especialista || 'Sin registrar' }}</dd>
        <dt>Demandantes</dt><dd>{{ expediente.demandante || 'Sin registrar' }}</dd>
        <dt>Demandados</dt><dd>{{ expediente.demandado || 'Sin registrar' }}</dd>
        <dt>Terceros</dt><dd>{{ expediente.tercero || 'Sin registrar' }}</dd>
        <dt>Estado</dt><dd>{{ expediente.estado || 'Sin registrar' }}</dd>
        <dt>Última resolución</dt><dd>{{ lastResolutionLabel }}</dd>
        <dt>Actualizado el</dt><dd>{{ formattedUpdatedAt }}</dd>
      </dl>
    </section>

    <section v-if="sortedResoluciones.length" class="plain-section" aria-labelledby="resolution-history">
      <h2 id="resolution-history">Resoluciones del expediente</h2>
      <div v-for="resolucion in sortedResoluciones" :key="resolucion.id" class="history-row">
        <div>
          <strong>{{ resolucion.estado === 'base' ? 'Documento base' : `Resolución N.º ${resolucion.numero}` }}</strong>
          <p>{{ resolucion.estado === 'pendiente' ? 'Pendiente' : 'Completada' }} · {{ formatResolutionDate(resolucion.completada_at || resolucion.created_at) }}</p>
        </div>
        <Button v-if="resolucion.estado === 'pendiente'" :loading="openingResolutionEditor" :disabled="generatingResolution" @click="handleOpenResolutionEditor">
          Continuar resolución
        </Button>
        <Button v-else-if="resolucion.nombre_archivo" variant="outline" :loading="downloadingResolutionId === resolucion.id" @click="handleDownloadResolution(resolucion)">
          Descargar documento
        </Button>
      </div>
    </section>

    <details class="plain-section extra-options">
      <summary>Otras opciones del expediente</summary>
      <div class="record-actions">
        <Button variant="outline" @click="showEditModal = true">Corregir datos del expediente</Button>
        <Button variant="outline" @click="statusText = expediente.estado || ''; showUpdateStatusModal = true">Cambiar estado</Button>
        <Button v-if="resolutionHistoryReady && !sortedResoluciones.length" variant="outline" @click="showUploadModal = true">
          {{ expediente.archivo ? 'Reemplazar documento' : 'Subir documento inicial' }}
        </Button>
        <Button v-if="expediente.archivo" variant="outline" :loading="loading" @click="handleDownloadFile">Descargar archivo</Button>
        <Button variant="outline" :loading="isGenerating" @click="handleGeneratePdf">Descargar PDF</Button>
        <Button
          v-if="resolutionHistoryReady" variant="outline" :loading="generatingResolution"
          :disabled="openingResolutionEditor" @click="handleDownloadResolutionTemplate"
        >Descargar plantilla Word de resolución {{ pendingResolution?.numero ?? nextResolutionNumber }}</Button>
        <Button v-if="pendingResolution" variant="outline" @click="openPendingResolution(pendingResolution)">Subir resolución terminada en Word</Button>
      </div>
    </details>

    <ExpedienteDocument v-if="documentMode" :expediente="expediente" :mode="documentMode" @close="documentMode = null" />
    <DeleteExpedienteModal
      v-if="showDeleteConfirm" :expediente="expediente" @close="showDeleteConfirm = false"
      @deleted="router.push('/expedientes?accion=eliminar')"
    />
    <Modal :open="showEditModal" title="Corregir datos del expediente" size="xl" @close="showEditModal = false">
      <ExpedienteForm :expediente="expediente" @success="handleEditSuccess" @cancel="showEditModal = false" />
    </Modal>
    <Modal :open="showUploadModal" :title="expediente.archivo ? 'Reemplazar documento' : 'Subir documento inicial'" size="lg" @close="showUploadModal = false">
      <FileUploader :on-upload="handleFileUpload" accept=".pdf,.doc,.docx" :loading="loading" />
    </Modal>
    <Modal :open="showInitialResolutionModal" title="Última resolución del expediente" size="lg" @close="closeInitialResolutionModal">
      <form class="simple-dialog" @submit.prevent="handleConfirmInitialResolution">
        <p v-if="expediente.resolucion_detectada != null">
          El documento indica la resolución {{ expediente.resolucion_detectada }}. Confirme o corrija el número para continuar.
        </p>
        <p v-else>Indique el número de la última resolución completada. Si aún no existe ninguna, escriba 0.</p>
        <label for="initial-resolution">Última resolución completada</label>
        <input id="initial-resolution" v-model.number="initialResolutionNumber" class="plain-input" type="number" min="0" step="1" required />
        <div class="record-actions">
          <Button variant="outline" :disabled="initialResolutionLoading" @click="closeInitialResolutionModal">Cancelar</Button>
          <Button type="submit" :loading="initialResolutionLoading">Confirmar y continuar</Button>
        </div>
      </form>
    </Modal>
    <Modal :open="showCompleteResolutionModal" :title="`Subir resolución ${completionResolutionNumber ?? ''} terminada`" size="lg" @close="showCompleteResolutionModal = false">
      <p class="mb-5 text-lg">Seleccione el Word terminado para incorporarlo al expediente.</p>
      <FileUploader :on-upload="handleCompleteResolution" accept=".doc,.docx" :loading="completingResolution || !resolutionHistoryReady" />
    </Modal>
    <Modal :open="showUpdateStatusModal" title="Cambiar estado del expediente" size="lg" @close="showUpdateStatusModal = false">
      <form class="simple-dialog" @submit.prevent="handleUpdateStatus">
        <label for="record-status">Situación actual del expediente</label>
        <textarea id="record-status" v-model="statusText" rows="4" class="plain-input" />
        <div class="record-actions">
          <Button variant="outline" @click="showUpdateStatusModal = false">Cancelar</Button>
          <Button type="submit" :loading="statusLoading">Guardar estado</Button>
        </div>
      </form>
    </Modal>
  </section>
</template>
