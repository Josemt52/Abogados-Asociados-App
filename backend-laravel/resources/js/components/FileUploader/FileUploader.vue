<script setup lang="ts">
import { computed, ref } from 'vue';
import { AlertCircle, File as FileIcon, Upload, X } from '@lucide/vue';
import Button from '@/components/UI/Button.vue';
import ProgressBar from '@/components/UI/ProgressBar.vue';
import { useToast } from '@/composables/useToast';

interface FileUploaderProps {
    onUpload: (file: File, onProgress?: (progress: number) => void) => Promise<void>;
    accept?: string;
    maxSize?: number;
    loading?: boolean;
    multiple?: boolean;
}

const props = defineProps<FileUploaderProps>();
const toast = useToast();

const accept = computed(() => props.accept ?? '.pdf,.doc,.docx');
const maxSize = computed(
    () => props.maxSize ?? Number.parseInt(import.meta.env.VITE_MAX_FILE_SIZE || '10485760', 10),
);
const loading = computed(() => props.loading ?? false);
const multiple = computed(() => props.multiple ?? false);

const dragOver = ref(false);
const selectedFiles = ref<File[]>([]);
const uploadProgress = ref(0);
const isUploading = ref(false);

const formatFileSize = (bytes: number): string => {
    if (bytes === 0) {
        return '0 Bytes';
    }

    const unit = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const index = Math.floor(Math.log(bytes) / Math.log(unit));

    return `${Math.round((bytes / Math.pow(unit, index)) * 100) / 100} ${sizes[index]}`;
};

const validateFileType = (file: File, acceptedTypes: string[]): boolean => {
    const fileExtension = `.${file.name.split('.').pop()?.toLowerCase()}`;
    const fileMimeType = file.type.toLowerCase();

    return acceptedTypes.some((type) => {
        const normalizedType = type.trim().toLowerCase();

        if (normalizedType.startsWith('.')) {
            return fileExtension === normalizedType;
        }

        if (normalizedType.includes('/')) {
            return (
                fileMimeType === normalizedType ||
                fileMimeType.startsWith(normalizedType.replace('*', ''))
            );
        }

        return false;
    });
};

const validateFile = (file: File): boolean => {
    if (file.size > maxSize.value) {
        toast.error(`El archivo es demasiado grande. Máximo ${formatFileSize(maxSize.value)}`);
        return false;
    }

    const acceptedTypes = accept.value.split(',').map((type) => type.trim());
    if (!validateFileType(file, acceptedTypes)) {
        toast.error(`Tipo de archivo no permitido. Tipos aceptados: ${accept.value}`);
        return false;
    }

    return true;
};

const handleFileSelect = (files: FileList | null): void => {
    if (!files) {
        return;
    }

    const validFiles = Array.from(files).filter(validateFile);
    selectedFiles.value = multiple.value
        ? [...selectedFiles.value, ...validFiles]
        : validFiles.slice(0, 1);
};

const handleDrop = (event: DragEvent): void => {
    dragOver.value = false;
    handleFileSelect(event.dataTransfer?.files ?? null);
};

const handleFileChange = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    handleFileSelect(input.files);
    input.value = '';
};

const handleUpload = async (file: File): Promise<void> => {
    isUploading.value = true;
    uploadProgress.value = 0;

    try {
        await props.onUpload(file, (progress) => {
            uploadProgress.value = progress;
        });

        selectedFiles.value = selectedFiles.value.filter((selectedFile) => selectedFile !== file);
    } catch {
        // El contenedor conoce el recurso y muestra el mensaje de error apropiado.
    } finally {
        isUploading.value = false;
        uploadProgress.value = 0;
    }
};

const removeFile = (file: File): void => {
    selectedFiles.value = selectedFiles.value.filter((selectedFile) => selectedFile !== file);
};

const clearAllFiles = (): void => {
    selectedFiles.value = [];
};
</script>

<template>
    <div class="space-y-5">
        <div
            v-if="selectedFiles.length === 0"
            class="rounded-2xl border-2 border-dashed p-8 text-center transition-colors"
            :class="dragOver ? 'border-blue-500 bg-blue-50' : 'border-slate-300 bg-slate-50 hover:border-blue-400 hover:bg-blue-50'"
            @dragover.prevent="dragOver = true"
            @dragleave="dragOver = false"
            @drop.prevent="handleDrop"
        >
            <Upload class="mx-auto mb-4 h-14 w-14 text-blue-700" aria-hidden="true" />
            <p class="mb-1 text-lg font-bold text-slate-950">
                1. Seleccione {{ multiple ? 'los documentos' : 'el documento' }}
            </p>
            <p class="mb-4 text-base text-slate-600">
                Arrastre {{ multiple ? 'los documentos' : 'el documento' }} aquí o use el botón para selecciona{{ multiple ? 'rlos' : 'rlo' }} desde este equipo.
            </p>
            <label class="inline-block">
                <input
                    type="file"
                    class="hidden"
                    :accept="accept"
                    :multiple="multiple"
                    :disabled="loading || isUploading"
                    @change="handleFileChange"
                />
                <Button
                    variant="outline"
                    as="span"
                    class="cursor-pointer"
                    :disabled="loading || isUploading"
                >
                    Elegir {{ multiple ? 'documentos' : 'documento' }}
                </Button>
            </label>
            <p class="mt-4 text-sm text-slate-600">
                Formatos permitidos: {{ accept }} · Máximo {{ formatFileSize(maxSize) }}<template v-if="multiple">
                    por archivo</template
                >
            </p>
        </div>

        <div v-else class="space-y-3">
            <div class="flex items-center justify-between">
                <h4 class="text-base font-bold text-slate-900">
                    {{ selectedFiles.length }} archivo{{ selectedFiles.length > 1 ? 's' : '' }} seleccionado{{
                        selectedFiles.length > 1 ? 's' : ''
                    }}
                </h4>
                <Button
                    v-if="selectedFiles.length > 1"
                    variant="outline"
                    size="sm"
                    :disabled="isUploading"
                    @click="clearAllFiles"
                >
                    Limpiar todo
                </Button>
            </div>

            <ProgressBar v-if="isUploading" :progress="uploadProgress" />

            <div class="space-y-2">
                <div
                    v-for="(file, index) in selectedFiles"
                    :key="`${file.name}-${file.lastModified}-${index}`"
                    class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4"
                >
                    <div class="flex items-center justify-between">
                        <div class="min-w-0 flex items-center space-x-3">
                            <div class="rounded-xl bg-white p-2 shadow-sm">
                                <FileIcon class="h-7 w-7 text-blue-700" aria-hidden="true" />
                            </div>
                            <div>
                                <p class="truncate text-base font-bold text-slate-950">{{ file.name }}</p>
                                <p class="text-sm text-emerald-800">Listo para subir · {{ formatFileSize(file.size) }}</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <Button
                                variant="primary"
                                size="sm"
                                :loading="isUploading"
                                :disabled="loading"
                                @click="handleUpload(file)"
                            >
                                Subir documento
                            </Button>
                            <button
                                type="button"
                                class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-slate-600 transition-colors hover:bg-red-100 hover:text-red-800 focus:outline-none focus:ring-4 focus:ring-red-100"
                                :disabled="isUploading || loading"
                                :aria-label="`Quitar ${file.name}`"
                                @click="removeFile(file)"
                            >
                                <X class="mr-1 h-5 w-5" aria-hidden="true" />
                                Quitar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 p-4 text-center hover:border-blue-400">
                <label class="cursor-pointer">
                    <input
                        type="file"
                        class="hidden"
                        :accept="accept"
                        :multiple="multiple"
                        :disabled="loading || isUploading"
                        @change="handleFileChange"
                    />
                    <span class="text-base font-semibold text-blue-800">+ Agregar otro documento</span>
                </label>
            </div>
        </div>

        <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-950">
            <div class="flex items-center space-x-2">
                <AlertCircle class="h-4 w-4 shrink-0 text-blue-700" aria-hidden="true" />
                <span>Revise el nombre antes de subir. Cada documento se carga de forma individual.</span>
            </div>
        </div>
    </div>
</template>
