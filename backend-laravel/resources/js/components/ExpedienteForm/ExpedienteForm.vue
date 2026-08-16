<script setup lang="ts">
import { reactive, ref } from 'vue';
import { expedientesAPI, type Expediente } from '@/api';
import Button from '@/components/UI/Button.vue';
import { useToast } from '@/composables/useToast';

interface ExpedienteFormData {
    numero: string;
    materia: string;
    juzgado: string;
    especialista: string;
    tercero: string;
    demandado: string;
    demandante: string;
    estado: string;
}

const props = defineProps<{
    expediente?: Partial<Expediente>;
}>();

const emit = defineEmits<{
    success: [expediente: Expediente];
    cancel: [];
}>();

const toast = useToast();
const loading = ref(false);
const tieneDocumento = ref(false);
const archivoSeleccionado = ref<File | null>(null);
const errors = reactive<Record<string, string>>({});

const formData = reactive<ExpedienteFormData>({
    numero: props.expediente?.numero ?? '',
    materia: props.expediente?.materia ?? '',
    juzgado: props.expediente?.juzgado ?? '',
    especialista: props.expediente?.especialista ?? '',
    tercero: props.expediente?.tercero ?? '',
    demandado: props.expediente?.demandado ?? '',
    demandante: props.expediente?.demandante ?? '',
    estado: props.expediente?.estado ?? '',
});

const clearErrors = (): void => {
    Object.keys(errors).forEach((key) => delete errors[key]);
};

const clearError = (field: string): void => {
    if (errors[field]) {
        delete errors[field];
    }
};

const validateForm = (): boolean => {
    clearErrors();

    if (!formData.numero.trim()) {
        errors.numero = 'El número de expediente es obligatorio';
    }

    if (tieneDocumento.value && !archivoSeleccionado.value) {
        errors.archivo = 'Debe seleccionar un archivo';
    }

    return Object.keys(errors).length === 0;
};

const handleSubmit = async (): Promise<void> => {
    if (!validateForm()) {
        return;
    }

    loading.value = true;

    try {
        if (props.expediente?.id) {
            const updatedExpediente = await expedientesAPI.update(props.expediente.id, { ...formData });
            toast.success('Expediente actualizado correctamente');
            emit('success', updatedExpediente);
            return;
        }

        const newExpediente = await expedientesAPI.create({ ...formData });

        if (tieneDocumento.value && archivoSeleccionado.value) {
            try {
                const uploadedExpediente = await expedientesAPI.uploadFile(
                    newExpediente.id,
                    archivoSeleccionado.value,
                );
                toast.success('Expediente creado y documento subido correctamente');
                emit('success', uploadedExpediente);
            } catch (documentError) {
                console.error('Error con documento, eliminando expediente:', documentError);
                await expedientesAPI.delete(newExpediente.id);
                toast.error('Error al procesar el documento. El expediente no fue creado.');
                throw documentError;
            }
        } else {
            toast.success('Expediente creado correctamente');
            emit('success', newExpediente);
        }
    } catch (error) {
        // Los errores HTTP también son tratados por el interceptor de la API.
        console.error('Error en handleSubmit:', error);
    } finally {
        loading.value = false;
    }
};

const handleFileChange = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (file) {
        archivoSeleccionado.value = file;
        clearError('archivo');
    }
};

const handleDocumentToggle = (): void => {
    if (!tieneDocumento.value) {
        archivoSeleccionado.value = null;
        clearError('archivo');
    }
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="handleSubmit">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
                <label for="numero" class="mb-1 block text-sm font-medium text-gray-700">
                    Número de Expediente *
                </label>
                <input
                    id="numero"
                    v-model="formData.numero"
                    type="text"
                    name="numero"
                    placeholder="EXP-2024-001"
                    class="w-full rounded-md border px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    :class="errors.numero ? 'border-red-500' : 'border-gray-300'"
                    @input="clearError('numero')"
                />
                <p v-if="errors.numero" class="mt-1 text-sm text-red-600">
                    {{ errors.numero }}
                </p>
            </div>

            <div>
                <label for="materia" class="mb-1 block text-sm font-medium text-gray-700">Materia</label>
                <input
                    id="materia"
                    v-model="formData.materia"
                    type="text"
                    name="materia"
                    placeholder="Civil, Penal, Laboral..."
                    class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
            </div>

            <div>
                <label for="juzgado" class="mb-1 block text-sm font-medium text-gray-700">Juzgado</label>
                <input
                    id="juzgado"
                    v-model="formData.juzgado"
                    type="text"
                    name="juzgado"
                    placeholder="Juzgado Civil..."
                    class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
            </div>

            <div>
                <label for="especialista" class="mb-1 block text-sm font-medium text-gray-700">Especialista</label>
                <input
                    id="especialista"
                    v-model="formData.especialista"
                    type="text"
                    name="especialista"
                    placeholder="Nombre del especialista..."
                    class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
            </div>

            <div>
                <label for="tercero" class="mb-1 block text-sm font-medium text-gray-700">Tercero</label>
                <input
                    id="tercero"
                    v-model="formData.tercero"
                    type="text"
                    name="tercero"
                    placeholder="Tercero involucrado..."
                    class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
            </div>

            <div>
                <label for="demandado" class="mb-1 block text-sm font-medium text-gray-700">Demandado</label>
                <input
                    id="demandado"
                    v-model="formData.demandado"
                    type="text"
                    name="demandado"
                    placeholder="Nombre del demandado..."
                    class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
            </div>

            <div>
                <label for="demandante" class="mb-1 block text-sm font-medium text-gray-700">Demandante</label>
                <input
                    id="demandante"
                    v-model="formData.demandante"
                    type="text"
                    name="demandante"
                    placeholder="Nombre del demandante..."
                    class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
            </div>

            <div>
                <label for="estado" class="mb-1 block text-sm font-medium text-gray-700">Estado</label>
                <input
                    id="estado"
                    v-model="formData.estado"
                    type="text"
                    name="estado"
                    placeholder="Estado del expediente..."
                    class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
            </div>
        </div>

        <div v-if="!props.expediente?.id" class="border-t border-gray-200 pt-6">
            <div class="flex items-start">
                <div class="flex h-5 items-center">
                    <input
                        id="tieneDocumento"
                        v-model="tieneDocumento"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        @change="handleDocumentToggle"
                    />
                </div>
                <div class="ml-3">
                    <label for="tieneDocumento" class="font-medium text-gray-700">
                        ¿Ya existe un documento del expediente?
                    </label>
                    <p class="text-sm text-gray-500">
                        Si marca esta opción, deberá subir el documento existente. Si no, podrá crear su primera
                        resolución desde el detalle del expediente.
                    </p>
                </div>
            </div>

            <div v-if="tieneDocumento" class="mt-4">
                <label class="mb-2 block text-sm font-medium text-gray-700">Subir Documento *</label>
                <div class="mt-1 flex items-center">
                    <label
                        class="relative cursor-pointer rounded-md bg-white font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2"
                    >
                        <span
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                        >
                            {{ archivoSeleccionado ? 'Cambiar archivo' : 'Seleccionar archivo' }}
                        </span>
                        <input
                            type="file"
                            accept=".pdf,.doc,.docx"
                            class="sr-only"
                            @change="handleFileChange"
                        />
                    </label>
                    <span v-if="archivoSeleccionado" class="ml-3 text-sm text-gray-600">
                        {{ archivoSeleccionado.name }}
                    </span>
                </div>
                <p v-if="errors.archivo" class="mt-1 text-sm text-red-600">
                    {{ errors.archivo }}
                </p>
                <p class="mt-1 text-xs text-gray-500">Formatos permitidos: PDF, DOC, DOCX (máx. 10MB)</p>
            </div>
        </div>

        <div class="flex justify-end space-x-3 border-t border-gray-200 pt-6">
            <Button type="button" variant="outline" @click="emit('cancel')">Cancelar</Button>
            <Button type="submit" variant="primary" :loading="loading">
                {{ props.expediente?.id ? 'Actualizar' : 'Crear' }} Expediente
            </Button>
        </div>
    </form>
</template>
