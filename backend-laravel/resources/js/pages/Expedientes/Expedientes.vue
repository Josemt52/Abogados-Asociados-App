<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { Eye, Plus, Search } from '@lucide/vue';
import { expedientesAPI, type Expediente } from '@/api';
import ExpedienteForm from '@/components/ExpedienteForm/ExpedienteForm.vue';
import Button from '@/components/UI/Button.vue';
import Modal from '@/components/UI/Modal.vue';
import Table from '@/components/UI/Table.vue';

const router = useRouter();
const searchTerm = ref('');
const currentPage = ref(1);
const showCreateModal = ref(false);
const expedientes = ref<Expediente[] | null>(null);
const loading = ref(true);
const pageSize = 10;

const showViewerModal = ref(false);
const viewerLoading = ref(false);
const viewerBlobUrl = ref<string | null>(null);
const viewerMessage = ref<string | null>(null);
const viewerMimeType = ref<string | null>(null);
const selectedViewerRow = ref<Expediente | null>(null);

const columns = [
    { key: 'numero', label: 'Número' },
    { key: 'materia', label: 'Materia' },
    { key: 'juzgado', label: 'Juzgado' },
    { key: 'especialista', label: 'Especialista' },
    { key: 'demandante', label: 'Demandante' },
    { key: 'demandado', label: 'Demandado' },
    { key: 'archivo', label: 'Archivo' },
    { key: 'acciones', label: 'Acciones' },
];

const expedienteRows = computed(() => (Array.isArray(expedientes.value) ? expedientes.value : []));
const filteredExpedientes = computed(() => {
    const term = searchTerm.value.trim().toLocaleLowerCase('es');

    if (!term) {
        return expedienteRows.value;
    }

    return expedienteRows.value.filter((expediente) =>
        [
            expediente.numero,
            expediente.materia,
            expediente.juzgado,
            expediente.especialista,
            expediente.demandante,
            expediente.demandado,
            expediente.estado,
        ].some((value) => String(value ?? '').toLocaleLowerCase('es').includes(term)),
    );
});
const totalPages = computed(() => Math.max(1, Math.ceil(filteredExpedientes.value.length / pageSize)));
const paginatedExpedientes = computed(() => {
    const start = (currentPage.value - 1) * pageSize;
    return filteredExpedientes.value.slice(start, start + pageSize);
});
const viewerTitle = computed(
    () => `Visor de Documento - ${selectedViewerRow.value?.numero ?? ''}`,
);

const refetch = async (): Promise<void> => {
    loading.value = true;

    try {
        expedientes.value = await expedientesAPI.getAll();
    } catch {
        expedientes.value = null;
    } finally {
        loading.value = false;
    }
};

const handleRowClick = (expediente: Expediente): void => {
    void router.push(`/expedientes/${expediente.id}`);
};

const handleSearch = (): void => {
    currentPage.value = 1;
};

const handleCreateSuccess = (): void => {
    showCreateModal.value = false;
    void refetch();
};

const handleViewClick = async (row: Expediente): Promise<void> => {
    if (viewerBlobUrl.value) {
        URL.revokeObjectURL(viewerBlobUrl.value);
    }

    selectedViewerRow.value = row;
    viewerBlobUrl.value = null;
    viewerMimeType.value = null;
    viewerMessage.value = null;
    showViewerModal.value = true;

    if (!row.archivo) {
        viewerMessage.value = 'Este expediente no tiene un documento asociado todavía.';
        return;
    }

    try {
        viewerLoading.value = true;
        viewerMessage.value = null;

        const originalBlob = await expedientesAPI.downloadFile(row.id);
        const originalType = originalBlob.type || '';

        if (originalType.includes('pdf')) {
            viewerBlobUrl.value = URL.createObjectURL(originalBlob);
            viewerMimeType.value = 'application/pdf';
            return;
        }

        try {
            const pdfBlob = await expedientesAPI.generatePdf(row.id);
            viewerBlobUrl.value = URL.createObjectURL(pdfBlob);
            viewerMimeType.value = 'application/pdf';
        } catch (conversionError) {
            console.warn('PDF conversion failed, falling back to download', conversionError);
            viewerBlobUrl.value = URL.createObjectURL(originalBlob);
            viewerMimeType.value = originalType || null;
            viewerMessage.value =
                'No se pudo convertir el documento a PDF. Puede descargar el archivo original.';
        }
    } catch (error) {
        console.error('Error fetching expediente PDF', error);
        viewerMessage.value = 'Error al descargar el documento. Intente nuevamente.';
        viewerBlobUrl.value = null;
    } finally {
        viewerLoading.value = false;
    }
};

const closeViewerModal = (): void => {
    showViewerModal.value = false;

    if (viewerBlobUrl.value) {
        URL.revokeObjectURL(viewerBlobUrl.value);
    }

    viewerBlobUrl.value = null;
    viewerMessage.value = null;
    viewerMimeType.value = null;
    selectedViewerRow.value = null;
};

onMounted(() => {
    void refetch();
});

watch(searchTerm, () => {
    currentPage.value = 1;
});

watch(totalPages, (pages) => {
    if (currentPage.value > pages) {
        currentPage.value = pages;
    }
});

onBeforeUnmount(() => {
    if (viewerBlobUrl.value) {
        URL.revokeObjectURL(viewerBlobUrl.value);
    }
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Expedientes</h1>
                <p class="text-gray-600">Gestiona todos los expedientes del sistema</p>
            </div>
            <Button variant="primary" @click="showCreateModal = true">
                <template #icon>
                    <Plus class="h-4 w-4" />
                </template>
                Nuevo Expediente
            </Button>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <form class="flex space-x-4" @submit.prevent="handleSearch">
                <div class="flex-1">
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <Search class="h-5 w-5 text-gray-400" />
                        </div>
                        <input
                            v-model="searchTerm"
                            type="text"
                            placeholder="Buscar por número de expediente..."
                            class="block w-full rounded-md border border-gray-300 bg-white py-2 pl-10 pr-3 leading-5 placeholder-gray-500 focus:border-blue-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500"
                        />
                    </div>
                </div>
                <Button type="submit" variant="outline">Buscar</Button>
                <Button
                    v-if="searchTerm"
                    type="button"
                    variant="outline"
                    @click="searchTerm = ''"
                >
                    Limpiar
                </Button>
            </form>
        </div>

        <div v-if="!loading && expedientes" class="text-sm text-gray-600">
            {{ filteredExpedientes.length }} expedientes encontrados
        </div>

        <Table
            :columns="columns"
            :rows="paginatedExpedientes"
            :loading="loading"
            empty-message="No se encontraron expedientes"
            @row-click="handleRowClick"
        >
            <template #cell-archivo="{ value }">
                <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="value ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                >
                    {{ value ? 'Sí' : 'No' }}
                </span>
            </template>

            <template #cell-acciones="{ row }">
                <div class="flex items-center justify-end space-x-2">
                    <button
                        type="button"
                        class="rounded p-1.5 text-gray-700 transition-colors hover:bg-gray-100"
                        title="Visualizar documento"
                        @click.stop="handleViewClick(row)"
                    >
                        <Eye class="h-4 w-4" />
                    </button>
                </div>
            </template>
        </Table>

        <div
            v-if="!loading && filteredExpedientes.length > 0"
            class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3"
        >
            <div class="text-sm text-gray-700">Página {{ currentPage }} de {{ totalPages }}</div>
            <div class="flex space-x-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="currentPage === 1"
                    @click="currentPage = Math.max(1, currentPage - 1)"
                >
                    Anterior
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="currentPage >= totalPages"
                    @click="currentPage = Math.min(totalPages, currentPage + 1)"
                >
                    Siguiente
                </Button>
            </div>
        </div>

        <Modal
            :open="showCreateModal"
            title="Crear Nuevo Expediente"
            size="xl"
            @close="showCreateModal = false"
        >
            <ExpedienteForm @success="handleCreateSuccess" @cancel="showCreateModal = false" />
        </Modal>

        <Modal :open="showViewerModal" :title="viewerTitle" size="full" @close="closeViewerModal">
            <div class="flex h-[85vh] flex-col">
                <div
                    v-if="!viewerLoading && viewerBlobUrl"
                    class="flex items-center justify-between border-b bg-gray-50 px-4 py-3"
                >
                    <div class="text-sm text-gray-600">
                        {{ selectedViewerRow?.nombre_archivo || 'documento.pdf' }}
                    </div>
                    <a
                        :href="viewerBlobUrl"
                        :download="selectedViewerRow?.nombre_archivo || 'documento.pdf'"
                        class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm text-white transition-colors hover:bg-blue-700"
                    >
                        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                            />
                        </svg>
                        Descargar
                    </a>
                </div>

                <div class="flex-1 overflow-hidden">
                    <div v-if="viewerLoading" class="flex h-full items-center justify-center">
                        <div class="text-center">
                            <div
                                class="mx-auto mb-4 h-12 w-12 animate-spin rounded-full border-b-2 border-blue-600"
                            ></div>
                            <p class="text-gray-600">Cargando documento...</p>
                        </div>
                    </div>

                    <div
                        v-if="!viewerLoading && viewerMessage"
                        class="flex h-full items-center justify-center"
                    >
                        <div class="p-6 text-center">
                            <svg
                                class="mx-auto mb-4 h-16 w-16 text-gray-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>
                            <p class="text-gray-700">{{ viewerMessage }}</p>
                        </div>
                    </div>

                    <iframe
                        v-if="
                            !viewerLoading &&
                            viewerBlobUrl &&
                            viewerMimeType &&
                            viewerMimeType.includes('pdf')
                        "
                        :src="viewerBlobUrl"
                        title="Documento PDF"
                        class="h-full w-full border-0"
                    ></iframe>

                    <div
                        v-if="
                            !viewerLoading &&
                            viewerBlobUrl &&
                            viewerMimeType &&
                            !viewerMimeType.includes('pdf')
                        "
                        class="flex h-full items-center justify-center"
                    >
                        <div class="p-6 text-center">
                            <svg
                                class="mx-auto mb-4 h-16 w-16 text-yellow-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                />
                            </svg>
                            <p class="mb-4 font-medium text-gray-700">El archivo asociado no es un PDF</p>
                            <p class="mb-4 text-sm text-gray-500">
                                Este tipo de archivo no puede previsualizarse en el navegador
                            </p>
                            <a
                                :href="viewerBlobUrl"
                                :download="selectedViewerRow?.nombre_archivo || 'documento'"
                                class="inline-flex items-center rounded-md bg-blue-600 px-6 py-3 text-white transition-colors hover:bg-blue-700"
                            >
                                <svg
                                    class="mr-2 h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                                    />
                                </svg>
                                Descargar archivo
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>
    </div>
</template>
