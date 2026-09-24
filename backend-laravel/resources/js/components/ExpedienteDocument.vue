<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { expedientesAPI, type Expediente } from '@/api';
import Modal from '@/components/UI/Modal.vue';
import { getApiErrorMessage } from '@/utils/apiError';
import { isValidPdfBlob, pdfFilename } from '@/utils/pdf';

const props = withDefaults(defineProps<{ expediente: Expediente; mode?: 'view' | 'print' }>(), { mode: 'view' });
const emit = defineEmits<{ close: [] }>();
const busy = ref(true);
const error = ref('');
const pdfUrl = ref('');
const originalUrl = ref('');
const frame = ref<HTMLIFrameElement | null>(null);
const ready = ref(false);
const printHelp = ref('');
let disposed = false;

const load = async () => {
  if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value);
  if (originalUrl.value) URL.revokeObjectURL(originalUrl.value);
  pdfUrl.value = '';
  originalUrl.value = '';
  ready.value = false;
  printHelp.value = '';
  busy.value = true;
  error.value = '';
  try {
    if (props.expediente.archivo) {
      const original = await expedientesAPI.downloadFile(props.expediente.id);
      if (disposed) return;
      const isPdf = await isValidPdfBlob(original);
      if (disposed) return;
      if (isPdf) {
        pdfUrl.value = URL.createObjectURL(new Blob([original], { type: 'application/pdf' }));
        return;
      }
      originalUrl.value = URL.createObjectURL(original);
    }
    const pdf = await expedientesAPI.generatePdf(props.expediente.id);
    if (disposed) return;
    if (!(await isValidPdfBlob(pdf))) throw new Error('El documento no se pudo preparar para imprimir.');
    if (disposed) return;
    pdfUrl.value = URL.createObjectURL(new Blob([pdf], { type: 'application/pdf' }));
  } catch (cause) {
    const message = await getApiErrorMessage(cause, 'No se pudo preparar el documento. Intente nuevamente.');
    if (!disposed) error.value = message;
  } finally { if (!disposed) busy.value = false; }
};

const print = () => {
  printHelp.value = 'Si no aparece la ventana de impresión, abra el PDF y use el botón de impresora del visor.';
  try {
    if (!frame.value?.contentWindow) return;
    frame.value.contentWindow.focus();
    frame.value.contentWindow.print();
  } catch { /* El enlace al PDF permite imprimir cuando el visor impide el acceso al iframe. */ }
};
onMounted(load);
onBeforeUnmount(() => {
  disposed = true;
  if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value);
  if (originalUrl.value) URL.revokeObjectURL(originalUrl.value);
});
</script>

<template>
  <Modal open :title="`${mode === 'print' ? 'Imprimir' : 'Ver'} expediente ${expediente.numero}`" size="full" @close="emit('close')">
    <div class="simple-dialog">
      <p v-if="busy" role="status">Preparando documento…</p>
      <template v-else-if="pdfUrl">
        <div class="document-toolbar">
          <button type="button" class="task-button" :disabled="!ready" @click="print">Imprimir expediente</button>
          <a :href="pdfUrl" target="_blank" rel="noopener" class="plain-button">Abrir PDF</a>
          <a :href="pdfUrl" :download="pdfFilename(expediente.nombre_archivo, `expediente_${expediente.numero}`)" class="plain-button">Descargar PDF</a>
        </div>
        <p v-if="printHelp" role="status">{{ printHelp }}</p>
        <iframe ref="frame" :src="pdfUrl" title="Documento del expediente" class="document-frame" @load="ready = true" />
      </template>
      <div v-else>
        <p role="alert" class="plain-notice">{{ error }}</p>
        <a v-if="originalUrl" :href="originalUrl" :download="expediente.nombre_archivo || 'documento'" class="plain-button">
          Descargar documento original
        </a>
        <button type="button" class="plain-button" @click="load">Intentar nuevamente</button>
      </div>
    </div>
  </Modal>
</template>
