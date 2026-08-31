<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { ArrowLeft, CheckCircle2, FileText, RotateCcw, Upload, X } from '@lucide/vue';
import { cargasMasivasAPI, type CargaMasivaCreated, type CargaMasivaProgress } from '@/api';
import Button from '@/components/UI/Button.vue';
import { useToast } from '@/composables/useToast';

type Phase = 'idle' | 'uploading' | 'processing' | 'completed' | 'error';

const MAX_FILES = 50;
const MAX_FILE_SIZE = 10 * 1024 * 1024;
const ALLOWED_EXTENSIONS = new Set(['doc', 'docx', 'pdf']);
const toast = useToast();
const files = ref<File[]>([]);
const dragging = ref(false);
const phase = ref<Phase>('idle');
const batch = ref<CargaMasivaCreated | null>(null);
const serverProgress = ref<CargaMasivaProgress | null>(null);
const uploadedCount = ref(0);
const transferProgress = ref(0);
const currentFileName = ref('');
let pollTimer: ReturnType<typeof setTimeout> | null = null;
let disposed = false;

const isBusy = computed(() => phase.value === 'uploading' || phase.value === 'processing');
const canEditSelection = computed(() => phase.value === 'idle' && batch.value === null);
const processed = computed(() => serverProgress.value?.procesados ?? 0);
const total = computed(() => serverProgress.value?.total ?? files.value.length);
const overallProgress = computed(() => serverProgress.value?.progreso ?? 0);
const isPdf = (file: File): boolean => file.name.toLowerCase().endsWith('.pdf');

const formatSize = (bytes: number): string => {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const selectFiles = (incoming: FileList | File[]): void => {
    if (!canEditSelection.value) {
        return;
    }

    const next = [...files.value];
    let rejectedType = 0;
    let rejectedSize = 0;

    for (const file of Array.from(incoming)) {
        const extension = file.name.split('.').pop()?.toLowerCase();
        if (!extension || !ALLOWED_EXTENSIONS.has(extension)) {
            rejectedType += 1;
            continue;
        }
        if (file.size <= 0 || file.size > MAX_FILE_SIZE) {
            rejectedSize += 1;
            continue;
        }

        if (next.length < MAX_FILES) {
            next.push(file);
        }
    }

    if (Array.from(incoming).length + files.value.length > MAX_FILES) {
        toast.error('Puedes procesar como máximo 50 documentos por lote.');
    }
    if (rejectedType > 0) {
        toast.error('Solo se admiten documentos .doc, .docx o .pdf.');
    }
    if (rejectedSize > 0) {
        toast.error('Cada documento debe pesar entre 1 byte y 10 MB.');
    }

    files.value = next;
};

const handleInput = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    if (input.files) {
        selectFiles(input.files);
    }
    input.value = '';
};

const handleDrop = (event: DragEvent): void => {
    dragging.value = false;
    if (event.dataTransfer?.files) {
        selectFiles(event.dataTransfer.files);
    }
};

const removeFile = (index: number): void => {
    if (canEditSelection.value) {
        files.value.splice(index, 1);
    }
};

const clearPoll = (): void => {
    if (pollTimer !== null) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }
};

const poll = async (): Promise<void> => {
    if (disposed || !batch.value) {
        return;
    }

    const batchId = batch.value.id;

    try {
        const progress = await cargasMasivasAPI.getProgress(batchId);
        if (disposed || batch.value?.id !== batchId) {
            return;
        }

        serverProgress.value = progress;
        if (serverProgress.value.estado === 'completado') {
            phase.value = 'completed';
            currentFileName.value = '';
            toast.success('La carga masiva terminó correctamente.');
            return;
        }
    } catch {
        if (!disposed) {
            phase.value = 'error';
            toast.error('No se pudo actualizar el progreso. Puedes reintentar la consulta.');
        }

        return;
    }

    if (!disposed && batch.value?.id === batchId) {
        pollTimer = setTimeout(() => void poll(), 2000);
    }
};

const start = async (): Promise<void> => {
    if (files.value.length === 0 || isBusy.value) {
        return;
    }

    phase.value = 'uploading';
    const selectedFiles = [...files.value];

    try {
        if (!batch.value) {
            const created = await cargasMasivasAPI.create(selectedFiles);
            if (disposed) {
                return;
            }

            batch.value = created;
            serverProgress.value = created;
        }

        for (let index = uploadedCount.value; index < selectedFiles.length; index += 1) {
            if (disposed) {
                return;
            }

            const file = selectedFiles[index];
            const slot = batch.value.cargas[index];

            if (!slot) {
                throw new Error('El servidor no reservó todos los documentos del lote.');
            }

            currentFileName.value = file.name;
            transferProgress.value = 0;
            const progress = await cargasMasivasAPI.upload(
                batch.value.id,
                slot.id,
                file,
                (progress) => {
                    transferProgress.value = progress;
                },
            );
            if (disposed) {
                return;
            }

            serverProgress.value = progress;
            uploadedCount.value = index + 1;
        }

        if (disposed) {
            return;
        }

        transferProgress.value = 100;
        currentFileName.value = '';
        phase.value = 'processing';
        clearPoll();
        await poll();
    } catch {
        if (!disposed) {
            phase.value = 'error';
            toast.error('La carga se interrumpió. Puedes reintentar desde el último documento enviado.');
        }
    }
};

const reset = (): void => {
    clearPoll();
    files.value = [];
    phase.value = 'idle';
    batch.value = null;
    serverProgress.value = null;
    uploadedCount.value = 0;
    transferProgress.value = 0;
    currentFileName.value = '';
};

onBeforeUnmount(() => {
    disposed = true;
    clearPoll();
});
</script>

<template>
    <div class="min-h-[calc(100vh-4rem)] bg-slate-50 px-4 py-8 sm:px-6">
        <div class="mx-auto max-w-5xl space-y-6">
            <div>
                <RouterLink
                    to="/main"
                    class="mb-4 inline-flex items-center text-sm font-medium text-blue-700 hover:text-blue-900"
                >
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Volver al inicio
                </RouterLink>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">
                    Carga masiva de expedientes
                </h1>
                <p class="mt-2 max-w-3xl text-slate-600">
                    Selecciona hasta 50 documentos Word o PDF. El sistema leerá cada cabecera y registrará
                    los expedientes automáticamente; cada PDF se convertirá y guardará como DOCX.
                </p>
            </div>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-900 px-6 py-5 text-white">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-slate-300">Paso único</p>
                            <h2 class="text-xl font-semibold">Selecciona tus documentos</h2>
                        </div>
                        <div class="rounded-full bg-white/10 px-4 py-2 text-sm font-semibold">
                            {{ files.length }} / {{ MAX_FILES }}
                        </div>
                    </div>
                </div>

                <div class="space-y-6 p-6">
                    <div
                        v-if="canEditSelection"
                        class="rounded-xl border-2 border-dashed p-8 text-center transition-colors"
                        :class="dragging ? 'border-blue-500 bg-blue-50' : 'border-slate-300 bg-slate-50'"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="handleDrop"
                    >
                        <Upload class="mx-auto h-12 w-12 text-blue-700" />
                        <p class="mt-4 font-semibold text-slate-900">Arrastra aquí tus documentos Word o PDF</p>
                        <p class="mt-1 text-sm text-slate-500">o selecciónalos desde tu equipo</p>
                        <label class="mt-5 inline-block cursor-pointer">
                            <input
                                type="file"
                                class="sr-only"
                                accept=".doc,.docx,.pdf"
                                multiple
                                @change="handleInput"
                            />
                            <span
                                class="inline-flex rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800"
                            >
                                Seleccionar documentos
                            </span>
                        </label>
                        <p class="mt-3 text-xs text-slate-500">.doc, .docx y .pdf · máximo 10 MB por archivo</p>
                    </div>

                    <div v-if="files.length > 0" class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-slate-900">Documentos del lote</h3>
                            <button
                                v-if="canEditSelection"
                                type="button"
                                class="text-sm font-medium text-slate-500 hover:text-red-700"
                                @click="files = []"
                            >
                                Quitar todos
                            </button>
                        </div>
                        <div class="max-h-72 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200">
                            <div
                                v-for="(file, index) in files"
                                :key="`${index}-${file.name}-${file.size}-${file.lastModified}`"
                                class="flex items-center gap-3 px-4 py-3"
                            >
                                <FileText class="h-5 w-5 shrink-0 text-blue-700" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-900">{{ file.name }}</p>
                                    <p class="text-xs text-slate-500">{{ formatSize(file.size) }}</p>
                                    <p v-if="isPdf(file)" class="mt-0.5 text-xs font-medium text-blue-700">
                                        Se convertirá y guardará como DOCX
                                    </p>
                                </div>
                                <span
                                    v-if="index < uploadedCount"
                                    class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800"
                                >
                                    Enviado
                                </span>
                                <button
                                    v-else-if="canEditSelection"
                                    type="button"
                                    class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-700"
                                    :aria-label="`Quitar ${file.name}`"
                                    @click="removeFile(index)"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="phase !== 'idle'"
                        class="space-y-5 rounded-xl border border-blue-100 bg-blue-50/60 p-5"
                        aria-live="polite"
                    >
                        <div v-if="phase === 'uploading'">
                            <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                                <span class="truncate font-medium text-slate-700">
                                    Enviando {{ currentFileName }}
                                </span>
                                <span class="shrink-0 text-slate-600">
                                    {{ uploadedCount }} de {{ files.length }} enviados
                                </span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-white">
                                <div
                                    class="h-full rounded-full bg-blue-500 transition-all duration-200"
                                    :style="{ width: `${transferProgress}%` }"
                                />
                            </div>
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="font-semibold text-slate-900">Procesamiento automático</span>
                                <span class="font-semibold text-blue-800">
                                    {{ processed }} de {{ total }} procesados
                                </span>
                            </div>
                            <div
                                class="h-3 overflow-hidden rounded-full bg-white"
                                role="progressbar"
                                aria-label="Expedientes procesados"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                :aria-valuenow="overallProgress"
                            >
                                <div
                                    class="h-full rounded-full bg-blue-700 transition-all duration-500"
                                    :style="{ width: `${overallProgress}%` }"
                                />
                            </div>
                        </div>

                        <div v-if="phase === 'completed'" class="flex items-start gap-3 rounded-lg bg-emerald-50 p-4">
                            <CheckCircle2 class="mt-0.5 h-6 w-6 shrink-0 text-emerald-700" />
                            <div>
                                <p class="font-semibold text-emerald-900">Lote procesado</p>
                                <p class="mt-1 text-sm text-emerald-800">
                                    Ya puedes consultar los expedientes registrados. Cualquier validación necesaria
                                    será atendida desde administración.
                                </p>
                            </div>
                        </div>

                        <div v-if="phase === 'error'" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <p class="font-semibold text-amber-950">La operación necesita atención</p>
                            <p class="mt-1 text-sm text-amber-900">
                                Puedes reintentar. Si el mismo documento vuelve a fallar, inicia un lote nuevo y
                                exclúyelo; los archivos ya enviados continuarán procesándose.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                        <Button v-if="phase === 'error'" variant="outline" @click="reset">
                            Empezar un lote nuevo
                        </Button>
                        <Button v-if="phase === 'completed'" variant="outline" @click="reset">
                            Cargar otro lote
                        </Button>
                        <RouterLink
                            v-if="phase === 'completed'"
                            to="/expedientes"
                            class="inline-flex items-center justify-center rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800"
                        >
                            Ver expedientes
                        </RouterLink>
                        <Button
                            v-else
                            size="lg"
                            :loading="isBusy"
                            :disabled="files.length === 0"
                            @click="start"
                        >
                            <template v-if="phase === 'error'" #icon>
                                <RotateCcw class="h-4 w-4" />
                            </template>
                            {{ phase === 'error' ? 'Reintentar carga' : 'Procesar expedientes' }}
                        </Button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
