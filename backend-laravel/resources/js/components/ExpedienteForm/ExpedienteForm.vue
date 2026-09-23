<script setup lang="ts">
import { reactive, ref } from 'vue';
import { CheckCircle2, FileText, Info } from '@lucide/vue';
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
        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5">
            <div class="flex items-start gap-3">
                <Info class="mt-0.5 h-6 w-6 shrink-0 text-blue-700" aria-hidden="true" />
                <div>
                    <p class="text-sm font-bold uppercase tracking-wide text-blue-800">
                        {{ props.expediente?.id ? 'Edición de expediente' : 'Nuevo expediente' }}
                    </p>
                    <p class="mt-1 text-base leading-relaxed text-blue-950">
                        Complete primero los datos principales. Los campos marcados con <strong>*</strong> son obligatorios.
                    </p>
                </div>
            </div>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-5 border-b border-slate-200 pb-4">
                <h4 class="text-lg font-bold text-slate-950">1. Datos principales</h4>
                <p class="mt-1 text-sm text-slate-600">Identifique el expediente y su situación actual.</p>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <label for="numero" class="mb-2 block text-base font-bold text-slate-800">
                        Número de expediente *
                    </label>
                    <input
                        id="numero"
                        v-model="formData.numero"
                        type="text"
                        name="numero"
                        required
                        aria-describedby="numero-help"
                        :aria-invalid="Boolean(errors.numero)"
                        placeholder="Ejemplo: EXP-2024-001"
                        class="min-h-12 w-full rounded-xl border-2 bg-white px-4 py-2.5 text-base text-slate-950 shadow-sm placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-100"
                        :class="errors.numero ? 'border-red-500' : 'border-slate-300'"
                        @input="clearError('numero')"
                    />
                    <p id="numero-help" class="mt-2 text-sm text-slate-500">Escriba el número tal como aparece en el documento.</p>
                    <p v-if="errors.numero" class="mt-1 text-sm font-medium text-red-700">
                        {{ errors.numero }}
                    </p>
                </div>

                <div>
                    <label for="estado" class="mb-2 block text-base font-bold text-slate-800">Situación actual</label>
                    <input
                        id="estado"
                        v-model="formData.estado"
                        type="text"
                        name="estado"
                        placeholder="Ejemplo: En trámite"
                        class="min-h-12 w-full rounded-xl border-2 border-slate-300 bg-white px-4 py-2.5 text-base text-slate-950 shadow-sm placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-100"
                    />
                </div>

                <div>
                    <label for="materia" class="mb-2 block text-base font-bold text-slate-800">Materia</label>
                    <input
                        id="materia"
                        v-model="formData.materia"
                        type="text"
                        name="materia"
                        placeholder="Ejemplo: Civil, penal o laboral"
                        class="min-h-12 w-full rounded-xl border-2 border-slate-300 bg-white px-4 py-2.5 text-base text-slate-950 shadow-sm placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-100"
                    />
                </div>

                <div>
                    <label for="juzgado" class="mb-2 block text-base font-bold text-slate-800">Juzgado</label>
                    <input
                        id="juzgado"
                        v-model="formData.juzgado"
                        type="text"
                        name="juzgado"
                        placeholder="Nombre del juzgado"
                        class="min-h-12 w-full rounded-xl border-2 border-slate-300 bg-white px-4 py-2.5 text-base text-slate-950 shadow-sm placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-100"
                    />
                </div>

                <div>
                    <label for="especialista" class="mb-2 block text-base font-bold text-slate-800">Especialista</label>
                    <input
                        id="especialista"
                        v-model="formData.especialista"
                        type="text"
                        name="especialista"
                        placeholder="Nombre de la persona responsable"
                        class="min-h-12 w-full rounded-xl border-2 border-slate-300 bg-white px-4 py-2.5 text-base text-slate-950 shadow-sm placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-100"
                    />
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-5 border-b border-slate-200 pb-4">
                <h4 class="text-lg font-bold text-slate-950">2. Personas vinculadas</h4>
                <p class="mt-1 text-sm text-slate-600">Si hay varias personas, escriba una por línea.</p>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <div>
                    <label for="demandante" class="mb-2 block text-base font-bold text-slate-800">Demandantes</label>
                    <textarea
                        id="demandante"
                        v-model="formData.demandante"
                        name="demandante"
                        rows="4"
                        maxlength="5000"
                        placeholder="Nombre completo\nOtra persona"
                        class="w-full rounded-xl border-2 border-slate-300 bg-white px-4 py-3 text-base text-slate-950 shadow-sm placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-100"
                    />
                </div>

                <div>
                    <label for="demandado" class="mb-2 block text-base font-bold text-slate-800">Demandados</label>
                    <textarea
                        id="demandado"
                        v-model="formData.demandado"
                        name="demandado"
                        rows="4"
                        maxlength="5000"
                        placeholder="Nombre completo\nOtra persona"
                        class="w-full rounded-xl border-2 border-slate-300 bg-white px-4 py-3 text-base text-slate-950 shadow-sm placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-100"
                    />
                </div>

                <div>
                    <label for="tercero" class="mb-2 block text-base font-bold text-slate-800">Terceros</label>
                    <textarea
                        id="tercero"
                        v-model="formData.tercero"
                        name="tercero"
                        rows="4"
                        maxlength="5000"
                        placeholder="Nombre completo\nOtra persona"
                        class="w-full rounded-xl border-2 border-slate-300 bg-white px-4 py-3 text-base text-slate-950 shadow-sm placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-100"
                    />
                </div>
            </div>
        </section>

        <section v-if="!props.expediente?.id" class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <div class="flex items-start gap-3">
                <input
                    id="tieneDocumento"
                    v-model="tieneDocumento"
                    type="checkbox"
                    class="mt-1 h-5 w-5 rounded border-amber-400 text-amber-600 focus:ring-amber-500"
                    @change="handleDocumentToggle"
                />
                <div class="flex-1">
                    <label for="tieneDocumento" class="text-base font-bold text-amber-950">
                        3. Ya tengo el documento inicial
                    </label>
                    <p class="mt-1 text-sm leading-relaxed text-amber-900">
                        Márquelo solo si desea adjuntar ahora el PDF o Word del expediente. Si no lo tiene, podrá hacerlo después.
                    </p>

                    <div v-if="tieneDocumento" class="mt-5 rounded-xl border border-amber-200 bg-white p-4">
                        <label class="mb-3 block text-base font-bold text-slate-800">Seleccione el documento *</label>
                        <label class="inline-flex min-h-12 cursor-pointer items-center rounded-xl border-2 border-blue-700 bg-blue-700 px-5 py-2.5 text-base font-bold text-white shadow-sm transition hover:bg-blue-800 focus-within:outline-none focus-within:ring-4 focus-within:ring-blue-200">
                            <FileText class="mr-2 h-5 w-5" aria-hidden="true" />
                            {{ archivoSeleccionado ? 'Cambiar archivo' : 'Elegir archivo' }}
                            <input
                                type="file"
                                accept=".pdf,.doc,.docx"
                                class="sr-only"
                                @change="handleFileChange"
                            />
                        </label>
                        <div v-if="archivoSeleccionado" class="mt-4 flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900">
                            <CheckCircle2 class="h-5 w-5 shrink-0 text-emerald-700" aria-hidden="true" />
                            <span class="break-all">Archivo seleccionado: {{ archivoSeleccionado.name }}</span>
                        </div>
                        <p v-if="errors.archivo" class="mt-3 text-sm font-medium text-red-700">
                            {{ errors.archivo }}
                        </p>
                        <p class="mt-3 text-sm text-slate-600">Formatos permitidos: PDF, DOC y DOCX. Tamaño máximo: 10 MB.</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 border-t-2 border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-end">
            <Button type="button" variant="outline" :disabled="loading" @click="emit('cancel')">Cancelar</Button>
            <Button type="submit" variant="primary" size="lg" :loading="loading">
                {{ props.expediente?.id ? 'Guardar cambios' : 'Crear expediente' }}
            </Button>
        </div>
    </form>
</template>
