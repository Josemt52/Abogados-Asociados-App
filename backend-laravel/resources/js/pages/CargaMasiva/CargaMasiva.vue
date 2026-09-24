<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
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
  <div class="simple-page">
    <h1>Carga masiva de expedientes</h1>
    <p class="page-instruction">Elija los documentos y después pulse «Procesar expedientes». Máximo 50 archivos Word o PDF de 10 MB cada uno.</p>
    <section class="simple-panel">
      <p v-if="!files.length">Documentos seleccionados: 0 / {{ MAX_FILES }}</p>
      <div v-if="canEditSelection" class="space-y-4" :class="{ 'bg-gray-100': dragging }"
        @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="handleDrop">
        <label class="task-button cursor-pointer focus-within:outline focus-within:outline-4 focus-within:outline-gray-800">
          Elegir documentos
          <input type="file" class="sr-only" accept=".doc,.docx,.pdf" multiple @change="handleInput" />
        </label>
        <p>También puede arrastrar los documentos aquí.</p>
      </div>
      <div v-if="files.length" class="mt-6 space-y-4">
        <h2>Documentos seleccionados: {{ files.length }} / {{ MAX_FILES }}</h2>
        <button v-if="canEditSelection" type="button" class="plain-button" @click="files = []">Quitar todos</button>
        <ul class="max-h-80 overflow-y-auto divide-y divide-gray-300 border border-gray-400">
          <li v-for="(file, index) in files" :key="`${index}-${file.name}-${file.size}-${file.lastModified}`" class="flex items-center gap-4 p-4">
            <div class="min-w-0 flex-1">
              <p class="break-words font-bold">{{ file.name }}</p>
              <p>{{ formatSize(file.size) }}</p>
              <p v-if="isPdf(file)">Se convertirá y guardará como DOCX</p>
            </div>
            <span v-if="index < uploadedCount">Enviado</span>
            <button v-else-if="canEditSelection" type="button" class="plain-button" :aria-label="`Quitar ${file.name}`" @click="removeFile(index)">Quitar</button>
          </li>
        </ul>
      </div>
      <div v-if="phase !== 'idle'" class="plain-notice mt-6 space-y-4" aria-live="polite">
        <div v-if="phase === 'uploading'">
          <p>Enviando {{ currentFileName }} · {{ uploadedCount }} de {{ files.length }} enviados</p>
          <progress :value="transferProgress" max="100" aria-label="Envío del documento" class="w-full accent-gray-800" />
        </div>
        <p>{{ processed }} de {{ total }} procesados</p>
        <progress :value="overallProgress" max="100" aria-label="Expedientes procesados" class="w-full accent-gray-800" />
        <div v-if="phase === 'completed'">
          <h2>Lote procesado correctamente</h2>
          <p>Ya puede consultar los expedientes registrados. Las validaciones pendientes se atienden desde administración.</p>
        </div>
        <div v-if="phase === 'error'" role="alert">
          <h2>La carga se interrumpió</h2>
          <p>Pulse «Reintentar carga» para continuar desde el último documento enviado.</p>
        </div>
        <p v-if="isBusy">Espere aquí hasta que termine la carga.</p>
      </div>
      <div class="mt-6 flex flex-wrap gap-4">
        <Button v-if="phase === 'error'" variant="outline" @click="reset">Empezar un lote nuevo</Button>
        <Button v-if="phase === 'completed'" variant="outline" @click="reset">Cargar otro lote</Button>
        <RouterLink v-if="phase === 'completed'" to="/expedientes" class="task-button">Ver expedientes</RouterLink>
        <Button v-else size="lg" :loading="isBusy" :disabled="files.length === 0" @click="start">
          {{ phase === 'error' ? 'Reintentar carga' : `Procesar expedientes (${files.length})` }}
        </Button>
      </div>
    </section>
  </div>
</template>
