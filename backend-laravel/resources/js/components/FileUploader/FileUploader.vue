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
    <div class="space-y-4">
        <div
            v-if="selectedFiles.length === 0"
            class="rounded-lg border-2 border-dashed p-8 text-center transition-colors"
            :class="dragOver ? 'border-blue-400 bg-blue-50' : 'border-gray-300 hover:border-gray-400'"
            @dragover.prevent="dragOver = true"
            @dragleave="dragOver = false"
            @drop.prevent="handleDrop"
        >
            <Upload class="mx-auto mb-4 h-12 w-12 text-gray-400" />
            <p class="mb-2 text-sm text-gray-600">
                Arrastra y suelta {{ multiple ? 'archivos' : 'un archivo' }} aquí, o
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
                    Seleccionar {{ multiple ? 'archivos' : 'archivo' }}
                </Button>
            </label>
            <p class="mt-2 text-xs text-gray-500">
                Tipos permitidos: {{ accept }} • Máximo {{ formatFileSize(maxSize) }}<template v-if="multiple">
                    por archivo</template
                >
            </p>
        </div>

        <div v-else class="space-y-3">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-medium text-gray-900">
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
                    class="rounded-lg border border-gray-200 p-4"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <FileIcon class="h-8 w-8 text-blue-600" />
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ file.name }}</p>
                                <p class="text-sm text-gray-500">{{ formatFileSize(file.size) }}</p>
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
                                Subir
                            </Button>
                            <button
                                type="button"
                                class="text-gray-400 transition-colors hover:text-red-600"
                                :disabled="isUploading || loading"
                                :aria-label="`Quitar ${file.name}`"
                                @click="removeFile(file)"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border-2 border-dashed border-gray-300 p-4 text-center">
                <label class="cursor-pointer">
                    <input
                        type="file"
                        class="hidden"
                        :accept="accept"
                        :multiple="multiple"
                        :disabled="loading || isUploading"
                        @change="handleFileChange"
                    />
                    <span class="text-sm text-gray-600">+ Agregar más archivos</span>
                </label>
            </div>
        </div>

        <div class="text-xs text-gray-500">
            <div class="flex items-center space-x-1">
                <AlertCircle class="h-3 w-3" />
                <span>Los archivos se suben individualmente. Tipos permitidos: {{ accept }}</span>
            </div>
        </div>
    </div>
</template>
