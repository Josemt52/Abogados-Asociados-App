<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { ArrowLeft } from '@lucide/vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import {
    type OnlyOfficeDocumentType,
    type OnlyOfficeEditorSession,
    type OnlyOfficeMode,
} from '@/api';
import OnlyOfficeEditor from '@/components/OnlyOfficeEditor.vue';
import Button from '@/components/UI/Button.vue';

const route = useRoute();
const router = useRouter();

const firstParam = (value: string | string[]): string => (Array.isArray(value) ? value[0] : value);

const expedienteId = computed(() => Number(firstParam(route.params.expedienteId as string | string[])));
const documentId = computed(() => Number(firstParam(route.params.documentId as string | string[])));
const documentType = computed<OnlyOfficeDocumentType>(() =>
    firstParam(route.params.type as string | string[]) === 'resolucion'
        ? 'resolucion'
        : 'expediente',
);
const mode = computed<OnlyOfficeMode>(() => (route.query.mode === 'view' ? 'view' : 'edit'));

const session = ref<OnlyOfficeEditorSession | null>(null);
const editorReady = ref(false);
const dirty = ref(false);
let allowLeave = false;

const fileName = computed(
    () =>
        session.value?.document.fileName ||
        (documentType.value === 'resolucion' ? 'Resolución' : 'Documento del expediente'),
);
const returnToExpediente = async (): Promise<void> => {
    allowLeave = true;
    await router.push({ name: 'expediente-detail', params: { id: expedienteId.value } });
};

const handleClose = async (): Promise<void> => {
    if (
        dirty.value &&
        !window.confirm(
            'ONLYOFFICE todavía indica cambios pendientes. ¿Desea cerrar el editor de todas formas?',
        )
    ) {
        return;
    }

    await returnToExpediente();
};

const handleBeforeUnload = (event: BeforeUnloadEvent): void => {
    if (!dirty.value) {
        return;
    }

    event.preventDefault();
    event.returnValue = '';
};

const markDocumentSaveForVerification = (): void => {
    try {
        sessionStorage.setItem(
            `onlyoffice:verify-save:${expedienteId.value}`,
            String(Date.now()),
        );
    } catch {
        // Storage can be unavailable in privacy modes; the backend still
        // blocks stale consolidated documents while a session is active.
    }
};

onBeforeRouteLeave((to) => {
    const canLeave =
        allowLeave ||
        !dirty.value ||
        window.confirm(
            'ONLYOFFICE todavía indica cambios pendientes. ¿Desea salir del editor de todas formas?',
        );

    if (!canLeave) {
        return false;
    }

    if (to.name === 'expediente-detail' && mode.value === 'edit') {
        markDocumentSaveForVerification();
    }

    return true;
});

onMounted(() => window.addEventListener('beforeunload', handleBeforeUnload));
onBeforeUnmount(() => window.removeEventListener('beforeunload', handleBeforeUnload));
</script>

<template>
    <main class="flex h-screen min-h-[36rem] flex-col bg-white" aria-label="Editor de documentos">
        <header class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-300 px-5 py-4">
            <div class="min-w-0">
                <p class="text-sm font-medium uppercase tracking-wide text-gray-500">
                    {{ documentType === 'resolucion' ? 'Editar resolución' : 'Editar documento' }}
                </p>
                <h1 class="truncate text-xl font-bold text-gray-950 sm:text-2xl">{{ fileName }}</h1>
                <p class="mt-1 text-sm text-gray-600" aria-live="polite">
                    <template v-if="dirty">Hay cambios pendientes de sincronizar.</template>
                    <template v-else-if="editorReady">
                        ONLYOFFICE gestiona el guardado. Cierre el editor para volver al expediente.
                    </template>
                    <template v-else>Preparando el documento.</template>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Button variant="outline" size="lg" @click="handleClose">
                    <template #icon><ArrowLeft class="h-5 w-5" /></template>
                    Cerrar editor
                </Button>
            </div>
        </header>

        <OnlyOfficeEditor
            :document-type="documentType"
            :document-id="documentId"
            :mode="mode"
            @ready="editorReady = true"
            @dirty-change="dirty = $event"
            @session-loaded="session = $event"
        />
    </main>
</template>
