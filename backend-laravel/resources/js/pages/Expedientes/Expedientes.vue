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
        label: 'Acciones disponibles',
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
    () => `Documento del expediente ${selectedViewerRow.value?.numero ?? ''}`,
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

const handleManageResolution = (expediente: Expediente): void => {
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

    const querySearch = Array.isArray(route.query.search)
        ? route.query.search[0]
        : route.query.search;

    if (typeof querySearch === 'string') {
        searchTerm.value = querySearch;
    }

    // Handle query parameters for create/filter modes
    if (route.query.create === 'true') {
        showCreateModal.value = true;
        router.replace({ query: {} });
    } else if (route.query.filter === 'finished' && !searchTerm.value) {
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
    <div class="space-y-7 pb-2">
        <section
            aria-labelledby="expedientes-page-title"
            class="overflow-hidden rounded-3xl border border-blue-800 bg-gradient-to-br from-blue-800 via-blue-700 to-cyan-700 shadow-xl"
        >
            <div class="p-6 lg:p-8">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex items-start gap-4">
                        <RouterLink
                            to="/main"
                            class="inline-flex min-h-12 shrink-0 items-center rounded-xl border border-white/30 bg-white/10 px-4 py-3 text-base font-bold text-white shadow-sm transition-colors hover:bg-white/20 focus:outline-none focus:ring-4 focus:ring-white/50 focus:ring-offset-2 focus:ring-offset-blue-700"
                        >
                            <ArrowLeft class="mr-2 h-5 w-5" aria-hidden="true" />
                            Inicio
                        </RouterLink>
                        <div class="pt-0.5">
                            <p class="text-sm font-bold uppercase tracking-[0.16em] text-cyan-100">
                                Panel de trabajo
                            </p>
                            <h1 id="expedientes-page-title" class="mt-1 text-3xl font-extrabold text-white lg:text-4xl">
                                Expedientes
                            </h1>
                            <p class="mt-2 max-w-2xl text-lg text-blue-50">
                                Busque un caso, abra sus datos o continúe con sus resoluciones desde un solo lugar.
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="inline-flex min-h-14 items-center justify-center rounded-xl bg-amber-400 px-6 py-3 text-lg font-extrabold text-slate-950 shadow-lg transition-colors hover:bg-amber-300 focus:outline-none focus:ring-4 focus:ring-amber-200 focus:ring-offset-2 focus:ring-offset-blue-700"
                        @click="showCreateModal = true"
                    >
                        <Plus class="mr-2 h-6 w-6" aria-hidden="true" />
                        Crear nuevo expediente
                    </button>
                </div>

                <div class="mt-7 grid gap-3 border-t border-white/20 pt-6 md:grid-cols-3">
                    <div class="flex items-center gap-3 rounded-2xl bg-white/10 px-4 py-3 text-white">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-cyan-300 text-base font-extrabold text-slate-900">
                            1
                        </span>
                        <div>
                            <p class="font-bold">Busque</p>
                            <p class="text-sm text-blue-100">Escriba un dato que recuerde.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-2xl bg-white/10 px-4 py-3 text-white">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-cyan-300 text-base font-extrabold text-slate-900">
                            2
                        </span>
                        <div>
                            <p class="font-bold">Abra el expediente</p>
                            <p class="text-sm text-blue-100">Revise los datos del caso.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-2xl bg-white/10 px-4 py-3 text-white">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-300 text-base font-extrabold text-slate-900">
                            3
                        </span>
                        <div>
                            <p class="font-bold">Gestione resoluciones</p>
                            <p class="text-sm text-blue-100">Cree o continúe el siguiente documento.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section
            aria-labelledby="expediente-search-title"
            class="rounded-3xl border-2 border-cyan-200 bg-white p-6 shadow-lg lg:p-8"
        >
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-cyan-100 text-cyan-800">
                        <Search class="h-7 w-7" aria-hidden="true" />
                    </div>
                    <div>
                        <h2 id="expediente-search-title" class="text-2xl font-extrabold text-slate-900">
                            Encuentre un expediente
                        </h2>
                        <p id="expediente-search-help" class="mt-1 text-base text-slate-600">
                            Puede escribir el número, materia, juzgado, especialista o el nombre de una persona.
                        </p>
                    </div>
                </div>
                <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">
                    No necesita escribir el dato completo.
                </p>
            </div>

            <form class="mt-6 flex flex-col gap-3 xl:flex-row" role="search" @submit.prevent="handleSearch">
                <div class="flex-1">
                    <label for="expediente-search" class="sr-only">Dato del expediente a buscar</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-5">
                            <Search class="h-7 w-7 text-blue-700" aria-hidden="true" />
                        </div>
                        <input
                            id="expediente-search"
                            v-model="searchTerm"
                            type="text"
                            aria-describedby="expediente-search-help"
                            autocomplete="off"
                            placeholder="Ejemplo: 12345-2024, civil o Pérez"
                            class="block min-h-16 w-full rounded-2xl border-2 border-slate-300 bg-white py-4 pl-16 pr-5 text-lg font-medium text-slate-900 shadow-sm placeholder:text-slate-500 focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-100"
                        />
                    </div>
                </div>
                <button
                    type="submit"
                    class="inline-flex min-h-16 items-center justify-center rounded-2xl bg-blue-700 px-7 py-3 text-lg font-extrabold text-white shadow-md transition-colors hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200 focus:ring-offset-2"
                >
                    <Search class="mr-2 h-5 w-5" aria-hidden="true" />
                    Buscar ahora
                </button>
                <button
                    v-if="searchTerm"
                    type="button"
                    class="inline-flex min-h-16 items-center justify-center rounded-2xl border-2 border-slate-300 bg-white px-6 py-3 text-base font-bold text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-200 focus:ring-offset-2"
                    @click="searchTerm = ''"
                >
                    Limpiar búsqueda
                </button>
            </form>
        </section>

        <section aria-labelledby="expedientes-list-title" class="space-y-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 id="expedientes-list-title" class="text-2xl font-extrabold text-slate-900">
                        Resultados de expedientes
                    </h2>
                    <p class="mt-1 text-base text-slate-600">
                        Use los botones de la derecha para realizar la acción que necesita.
                    </p>
                </div>
                <div
                    v-if="!loading && expedientes"
                    role="status"
                    aria-live="polite"
                    class="inline-flex items-center self-start rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-base font-bold text-emerald-900 lg:self-auto"
                >
                    {{
                        filteredExpedientes.length === 1
                            ? '1 expediente encontrado'
                            : `${filteredExpedientes.length} expedientes encontrados`
                    }}
                </div>
            </div>

            <div class="rounded-2xl border border-blue-100 bg-blue-50 px-5 py-4 text-sm text-blue-950">
                <div class="flex items-center gap-3">
                    <FolderOpen class="h-6 w-6 shrink-0 text-blue-700" aria-hidden="true" />
                    <p>
                        <span class="font-extrabold">Guía rápida:</span>
                        <span class="ml-1">“Abrir expediente” muestra todos los datos; “Gestionar resolución” inicia o continúa el documento pendiente.</span>
                    </p>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border-2 border-slate-200 bg-white shadow-lg">
                <Table
                    :columns="columns"
                    :rows="paginatedExpedientes"
                    :loading="loading"
                    fixed-layout
                    stack-on-mobile
                    empty-message="No se encontraron expedientes con esos datos. Pruebe con otra palabra o número."
                    @row-click="handleRowClick"
                >
                    <template #cell-numero="{ value }">
                        <span class="inline-flex rounded-lg bg-blue-50 px-3 py-2 text-base font-extrabold text-blue-900">
                            {{ value }}
                        </span>
                    </template>

                    <template #cell-materia="{ value }">
                        <span
                            class="block max-w-[12rem] truncate text-base font-medium text-slate-800"
                            :title="String(value || 'Sin registrar')"
                        >
                            {{ value || 'Sin registrar' }}
                        </span>
                    </template>

                    <template #cell-estado="{ value }">
                        <span
                            class="inline-flex max-w-[12rem] truncate rounded-full bg-violet-100 px-3 py-1.5 text-base font-bold capitalize text-violet-900"
                            :title="String(value || 'Sin estado')"
                        >
                            {{ value || 'Sin estado' }}
                        </span>
                    </template>

                    <template #cell-acciones="{ row }">
                        <div class="grid min-w-0 grid-cols-1 gap-2 2xl:grid-cols-3">
                            <button
                                type="button"
                                class="inline-flex min-h-12 min-w-0 items-center justify-center rounded-xl bg-blue-700 px-3 py-3 text-sm font-extrabold leading-tight text-white shadow-sm transition-colors hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200 focus:ring-offset-2"
                                title="Abrir los datos y acciones de este expediente"
                                @click.stop="handleRowClick(row)"
                            >
                                <FolderOpen class="mr-2 h-5 w-5 shrink-0" aria-hidden="true" />
                                Abrir expediente
                            </button>
                            <button
                                type="button"
                                class="inline-flex min-h-12 min-w-0 items-center justify-center rounded-xl border-2 border-cyan-300 bg-cyan-50 px-3 py-3 text-sm font-extrabold leading-tight text-cyan-950 shadow-sm transition-colors hover:bg-cyan-100 focus:outline-none focus:ring-4 focus:ring-cyan-200 focus:ring-offset-2 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-500 disabled:opacity-100"
                                :disabled="!row.archivo"
                                :title="row.archivo ? 'Abrir el documento asociado' : 'Este expediente todavía no tiene un documento asociado'"
                                @click.stop="handleViewClick(row)"
                            >
                                <Eye class="mr-2 h-5 w-5 shrink-0" aria-hidden="true" />
                                {{ row.archivo ? 'Ver documento' : 'Sin documento' }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex min-h-12 min-w-0 items-center justify-center rounded-xl bg-amber-400 px-3 py-3 text-sm font-extrabold leading-tight text-slate-950 shadow-sm transition-colors hover:bg-amber-300 focus:outline-none focus:ring-4 focus:ring-amber-200 focus:ring-offset-2"
                                title="Crear o continuar la resolución de este expediente"
                                @click.stop="handleManageResolution(row)"
                            >
                                <FilePenLine class="mr-2 h-5 w-5 shrink-0" aria-hidden="true" />
                                Gestionar resolución
                            </button>
                        </div>
                    </template>
                </Table>
            </div>

            <div
                v-if="!loading && filteredExpedientes.length > 0"
                class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="text-base font-medium text-slate-700">
                    Página <span class="font-extrabold text-slate-950">{{ currentPage }}</span> de
                    <span class="font-extrabold text-slate-950">{{ totalPages }}</span>
                </div>
                <div class="flex gap-3">
                    <Button
                        class="min-h-12 px-5 text-base font-bold"
                        variant="outline"
                        size="md"
                        :disabled="currentPage === 1"
                        @click="currentPage = Math.max(1, currentPage - 1)"
                    >
                        <template #icon>
                            <ArrowLeft class="h-5 w-5" />
                        </template>
                        Anterior
                    </Button>
                    <Button
                        class="min-h-12 px-5 text-base font-bold"
                        variant="outline"
                        size="md"
                        :disabled="currentPage >= totalPages"
                        @click="currentPage = Math.min(totalPages, currentPage + 1)"
                    >
                        <template #icon>
                            <ArrowLeft class="h-5 w-5 rotate-180" />
                        </template>
                        Siguiente
                    </Button>
                </div>
            </div>
        </section>

        <Modal
            :open="showCreateModal"
            title="Crear un nuevo expediente"
            size="xl"
            @close="showCreateModal = false"
        >
            <ExpedienteForm @success="handleCreateSuccess" @cancel="showCreateModal = false" />
        </Modal>

        <Modal :open="showViewerModal" :title="viewerTitle" size="full" @close="closeViewerModal">
            <div class="flex h-[85vh] flex-col">
                <div
                    v-if="viewerState !== 'loading' && viewerBlobUrl"
                    class="flex items-center justify-between gap-4 border-b border-slate-200 bg-slate-50 px-5 py-4"
                >
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Documento asociado</p>
                        <p class="truncate text-base font-semibold text-slate-800">{{ viewerFilename }}</p>
                    </div>
                    <a
                        :href="viewerBlobUrl"
                        :download="viewerFilename"
                        class="inline-flex min-h-12 shrink-0 items-center rounded-xl bg-blue-700 px-5 py-3 text-base font-bold text-white shadow-sm transition-colors hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200 focus:ring-offset-2"
                    >
                        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                            />
                        </svg>
                        Descargar documento
                    </a>
                </div>

                <div class="flex-1 overflow-hidden">
                    <div v-if="viewerState === 'loading'" class="flex h-full items-center justify-center">
                        <div class="text-center">
                            <div
                                class="mx-auto mb-4 h-12 w-12 animate-spin rounded-full border-b-2 border-blue-600"
                            ></div>
                            <p class="font-semibold text-slate-700">Preparando documento...</p>
                            <p class="mt-1 text-sm text-slate-500">Esto puede tomar unos segundos.</p>
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
                            <p class="mb-2 text-lg font-bold text-slate-800">No se pudo mostrar la vista previa</p>
                            <p class="mb-5 text-sm text-slate-600">{{ viewerMessage }}</p>
                            <a
                                :href="viewerBlobUrl"
                                :download="viewerFilename"
                                class="inline-flex min-h-12 items-center rounded-xl bg-blue-700 px-6 py-3 font-bold text-white transition-colors hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200 focus:ring-offset-2"
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
                            <p class="text-base font-semibold text-slate-700">{{ viewerMessage }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>
    </div>
</template>
