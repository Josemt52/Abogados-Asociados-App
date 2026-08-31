<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, useRouter, useRoute } from 'vue-router';
import { ArrowLeft, Eye, FilePenLine, FolderOpen, Plus, Search } from '@lucide/vue';
import { expedientesAPI, type Expediente } from '@/api';
import ExpedienteForm from '@/components/ExpedienteForm/ExpedienteForm.vue';
import Button from '@/components/UI/Button.vue';
import Modal from '@/components/UI/Modal.vue';
import Table from '@/components/UI/Table.vue';
import { getApiErrorMessage } from '@/utils/apiError';
import { isValidPdfBlob, pdfFilename } from '@/utils/pdf';

type ViewerState = 'idle' | 'loading' | 'pdf' | 'download' | 'error';

const router = useRouter();
const route = useRoute();
const searchTerm = ref('');
const currentPage = ref(1);
const showCreateModal = ref(false);
const expedientes = ref<Expediente[] | null>(null);
const loading = ref(true);
const pageSize = 10;

const showViewerModal = ref(false);
const viewerState = ref<ViewerState>('idle');
const viewerBlobUrl = ref<string | null>(null);
const viewerMessage = ref<string | null>(null);
const viewerFilename = ref('documento');
const selectedViewerRow = ref<Expediente | null>(null);
let viewerRequestId = 0;

const columns = [
    { key: 'numero', label: 'Número de expediente', headerClass: 'xl:w-[25%]' },
    { key: 'materia', label: 'Materia', headerClass: 'xl:w-[18%]' },
    { key: 'estado', label: 'Estado', headerClass: 'xl:w-[15%]' },
    {
        key: 'acciones',
        label: 'Acciones',
        headerClass: 'xl:w-[42%]',
        cellClass: 'max-w-none',
    },
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

const handleCreateSuccess = (expediente: Expediente): void => {
    showCreateModal.value = false;
    void router.push(`/expedientes/${expediente.id}`);
};

const handleUpdateExpediente = (expediente: Expediente): void => {
    void router.push({
        path: `/expedientes/${expediente.id}`,
        query: { editor: 'true' },
    });
};

const revokeViewerUrl = (): void => {
    if (viewerBlobUrl.value) {
        URL.revokeObjectURL(viewerBlobUrl.value);
        viewerBlobUrl.value = null;
    }
};

const setViewerBlob = (blob: Blob, filename: string, state: 'pdf' | 'download'): void => {
    revokeViewerUrl();
    viewerBlobUrl.value = URL.createObjectURL(blob);
    viewerFilename.value = filename;
    viewerState.value = state;
};

const isActiveViewerRequest = (requestId: number): boolean =>
    requestId === viewerRequestId && showViewerModal.value;

const handleViewClick = async (row: Expediente): Promise<void> => {
    const requestId = ++viewerRequestId;
    revokeViewerUrl();

    selectedViewerRow.value = row;
    viewerMessage.value = null;
    viewerFilename.value = row.nombre_archivo || 'documento';
    viewerState.value = 'idle';
    showViewerModal.value = true;

    if (!row.archivo) {
        viewerMessage.value = 'Este expediente no tiene un documento asociado todavía.';
        viewerState.value = 'error';
        return;
    }

    try {
        viewerState.value = 'loading';
        viewerMessage.value = null;

        const originalBlob = await expedientesAPI.downloadFile(row.id);
        if (!isActiveViewerRequest(requestId)) {
            return;
        }

        if (await isValidPdfBlob(originalBlob)) {
            if (!isActiveViewerRequest(requestId)) {
                return;
            }

            setViewerBlob(
                originalBlob,
                pdfFilename(row.nombre_archivo, `expediente_${row.numero}`),
                'pdf',
            );
            return;
        }

        try {
            const pdfBlob = await expedientesAPI.generatePdf(row.id);
            if (!isActiveViewerRequest(requestId)) {
                return;
            }

            if (!(await isValidPdfBlob(pdfBlob))) {
                throw new Error('La conversión no devolvió un archivo PDF válido.');
            }

            if (!isActiveViewerRequest(requestId)) {
                return;
            }

            setViewerBlob(
                pdfBlob,
                pdfFilename(row.nombre_archivo, `expediente_${row.numero}`),
                'pdf',
            );
        } catch (conversionError) {
            console.warn('PDF conversion failed, falling back to download', conversionError);
            if (!isActiveViewerRequest(requestId)) {
                return;
            }

            const message = await getApiErrorMessage(
                conversionError,
                'No se pudo convertir el documento a PDF.',
            );
            if (!isActiveViewerRequest(requestId)) {
                return;
            }

            viewerMessage.value = `${message} Puede descargar el archivo original.`;
            setViewerBlob(originalBlob, row.nombre_archivo || 'documento', 'download');
        }
    } catch (error) {
        console.error('Error fetching expediente PDF', error);
        if (!isActiveViewerRequest(requestId)) {
            return;
        }

        const message = await getApiErrorMessage(
            error,
            'Error al descargar el documento. Intente nuevamente.',
        );
        if (!isActiveViewerRequest(requestId)) {
            return;
        }

        viewerMessage.value = message;
        viewerState.value = 'error';
    }
};

const closeViewerModal = (): void => {
    viewerRequestId += 1;
    showViewerModal.value = false;
    revokeViewerUrl();
    viewerMessage.value = null;
    viewerState.value = 'idle';
    selectedViewerRow.value = null;
};

onMounted(() => {
    void refetch();
    
    // Handle query parameters for create/filter modes
    if (route.query.create === 'true') {
        showCreateModal.value = true;
        router.replace({ query: {} });
    } else if (route.query.filter === 'finished') {
        // Filter for finished expedientes
        searchTerm.value = 'finalizado';
    }
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
    viewerRequestId += 1;
    revokeViewerUrl();
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start space-x-4 sm:items-center">
                <RouterLink
                    to="/main"
                    class="inline-flex min-h-12 items-center rounded-lg bg-blue-700 px-5 py-3 text-base font-semibold text-white shadow-md transition-colors hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    <ArrowLeft class="mr-2 h-5 w-5" />
                    Volver
                </RouterLink>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Expedientes</h1>
                    <p class="mt-1 text-base text-gray-600">Consulta y administra los expedientes del sistema</p>
                </div>
            </div>
            <Button class="min-h-12 text-base" variant="primary" size="lg" @click="showCreateModal = true">
                <template #icon>
                    <Plus class="h-4 w-4" />
                </template>
                Nuevo Expediente
            </Button>
        </div>

        <div class="rounded-2xl border-2 border-gray-300 bg-white p-6 shadow-md sm:p-8">
            <div class="mb-4">
                <label for="expediente-search" class="block text-xl font-bold text-gray-900">
                    Buscar expedientes
                </label>
                <p id="expediente-search-help" class="mt-1 text-base text-gray-600">
                    Escriba un número, materia, juzgado o nombre relacionado con el expediente.
                </p>
            </div>
            <form class="flex flex-col gap-3 sm:flex-row" role="search" @submit.prevent="handleSearch">
                <div class="flex-1">
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                            <Search class="h-7 w-7 text-gray-500" />
                        </div>
                        <input
                            id="expediente-search"
                            v-model="searchTerm"
                            type="text"
                            aria-describedby="expediente-search-help"
                            placeholder="Ejemplo: 12345-2024 o materia civil"
                            class="block min-h-14 w-full rounded-xl border-2 border-gray-400 bg-white py-3 pl-14 pr-4 text-lg leading-6 text-gray-900 shadow-sm placeholder-gray-500 focus:border-gray-700 focus:outline-none focus:ring-4 focus:ring-gray-200"
                        />
                    </div>
                </div>
                <Button class="min-h-14 text-base" type="submit" variant="outline" size="lg">Buscar</Button>
                <Button
                    v-if="searchTerm"
                    class="min-h-14 text-base"
                    type="button"
                    variant="outline"
                    size="lg"
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
            fixed-layout
            stack-on-mobile
            empty-message="No se encontraron expedientes"
            @row-click="handleRowClick"
        >
            <template #cell-numero="{ value }">
                <span class="text-base font-semibold text-gray-900">{{ value }}</span>
            </template>

            <template #cell-materia="{ value }">
                <span
                    class="block max-w-[12rem] truncate text-base text-gray-800"
                    :title="String(value || 'Sin registrar')"
                >
                    {{ value || 'Sin registrar' }}
                </span>
            </template>

            <template #cell-estado="{ value }">
                <span
                    class="block max-w-[12rem] truncate text-base font-medium capitalize text-gray-800"
                    :title="String(value || 'Sin estado')"
                >
                    {{ value || 'Sin estado' }}
                </span>
            </template>

            <template #cell-acciones="{ row }">
                <div class="grid min-w-0 grid-cols-1 gap-2 xl:grid-cols-3">
                    <button
                        type="button"
                        class="inline-flex min-h-11 min-w-0 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold leading-tight text-gray-800 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2"
                        @click.stop="handleRowClick(row)"
                    >
                        <FolderOpen class="mr-2 h-5 w-5" aria-hidden="true" />
                        Ver expediente
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-h-11 min-w-0 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold leading-tight text-gray-800 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!row.archivo"
                        :title="row.archivo ? 'Ver documento' : 'Este expediente no tiene un documento asociado'"
                        @click.stop="handleViewClick(row)"
                    >
                        <Eye class="mr-2 h-5 w-5" aria-hidden="true" />
                        Ver documento
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-h-11 min-w-0 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold leading-tight text-gray-800 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2"
                        title="Redactar la siguiente resolución"
                        @click.stop="handleUpdateExpediente(row)"
                    >
                        <FilePenLine class="mr-2 h-5 w-5" aria-hidden="true" />
                        Actualizar expediente
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
                    v-if="viewerState !== 'loading' && viewerBlobUrl"
                    class="flex items-center justify-between border-b bg-gray-50 px-4 py-3"
                >
                    <div class="text-sm text-gray-600">{{ viewerFilename }}</div>
                    <a
                        :href="viewerBlobUrl"
                        :download="viewerFilename"
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
                    <div v-if="viewerState === 'loading'" class="flex h-full items-center justify-center">
                        <div class="text-center">
                            <div
                                class="mx-auto mb-4 h-12 w-12 animate-spin rounded-full border-b-2 border-blue-600"
                            ></div>
                            <p class="text-gray-600">Cargando documento...</p>
                        </div>
                    </div>

                    <iframe
                        v-else-if="viewerState === 'pdf' && viewerBlobUrl"
                        :src="viewerBlobUrl"
                        title="Documento PDF"
                        class="h-full w-full border-0"
                    ></iframe>

                    <div
                        v-else-if="viewerState === 'download' && viewerBlobUrl"
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
                            <p class="mb-4 font-medium text-gray-700">No se pudo mostrar la vista previa</p>
                            <p class="mb-4 text-sm text-gray-500">{{ viewerMessage }}</p>
                            <a
                                :href="viewerBlobUrl"
                                :download="viewerFilename"
                                class="inline-flex items-center rounded-md bg-blue-600 px-6 py-3 text-white transition-colors hover:bg-blue-700"
                            >
                                Descargar archivo original
                            </a>
                        </div>
                    </div>

                    <div v-else class="flex h-full items-center justify-center">
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
                </div>
            </div>
        </Modal>
    </div>
</template>
