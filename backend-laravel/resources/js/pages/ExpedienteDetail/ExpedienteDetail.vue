<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
    ArrowLeft,
    Clock,
    Download,
    Edit,
    File as FileIcon,
    Scale,
    Trash2,
    Upload,
    User,
} from '@lucide/vue';
import { expedientesAPI, type Expediente } from '@/api';
import ExpedienteForm from '@/components/ExpedienteForm/ExpedienteForm.vue';
import FileUploader from '@/components/FileUploader/FileUploader.vue';
import Button from '@/components/UI/Button.vue';
import Modal from '@/components/UI/Modal.vue';
import { useToast } from '@/composables/useToast';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const showEditModal = ref(false);
const showUploadModal = ref(false);
const showDeleteConfirm = ref(false);
const showUpdateStatusModal = ref(false);
const statusText = ref('');
const statusLoading = ref(false);
const loading = ref(false);
const isGenerating = ref(false);
const expediente = ref<Expediente | null>(null);
const expedienteLoading = ref(true);

const rawId = computed(() => {
    const routeId = route.params.id;
    return Array.isArray(routeId) ? routeId[0] : routeId;
});
const expedienteId = computed(() => Number(rawId.value));
const formattedUpdatedAt = computed(() => {
    if (!expediente.value?.updated_at) {
        return 'Sin fecha';
    }

    const date = new Date(expediente.value.updated_at);
    return Number.isNaN(date.getTime()) ? 'Sin fecha' : date.toLocaleDateString('es-PE');
});

const refetch = async (): Promise<void> => {
    if (!rawId.value) {
        expediente.value = null;
        expedienteLoading.value = false;
        return;
    }

    expedienteLoading.value = true;

    try {
        expediente.value = await expedientesAPI.getById(expedienteId.value);
    } catch {
        expediente.value = null;
    } finally {
        expedienteLoading.value = false;
    }
};

const downloadBlob = (blob: Blob, filename: string): void => {
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
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

        statusText.value = expediente.value?.estado || '';
        showUpdateStatusModal.value = true;
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
        const filename = `expediente_${expediente.value?.numero || rawId.value}.pdf`;
        downloadBlob(blob, filename);
        toast.success('Documento PDF generado correctamente');
    } catch (error) {
        toast.error('Error al generar documento PDF');
        console.error(error);
    } finally {
        isGenerating.value = false;
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

watch(
    rawId,
    (id) => {
        if (!id) {
            void router.push('/expedientes');
            return;
        }

        void refetch();
    },
    { immediate: true },
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
            Volver a Expedientes
        </Button>
    </div>

    <div v-else class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <Button variant="outline" @click="router.push('/expedientes')">
                    <template #icon>
                        <ArrowLeft class="h-4 w-4" />
                    </template>
                    Volver
                </Button>
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
                <Button variant="outline" @click="showUploadModal = true">
                    <template #icon>
                        <Upload class="h-4 w-4" />
                    </template>
                    Subir Archivo
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
            </div>

            <div class="space-y-6">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Acciones Rápidas</h3>
                    <div class="space-y-3">
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
