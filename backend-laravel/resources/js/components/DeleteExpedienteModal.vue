<script setup lang="ts">
import { ref } from 'vue';
import { expedientesAPI, type Expediente } from '@/api';
import Modal from '@/components/UI/Modal.vue';
import { getApiErrorMessage } from '@/utils/apiError';

const props = defineProps<{ expediente: Expediente }>();
const emit = defineEmits<{ close: []; deleted: [] }>();
const busy = ref(false);
const error = ref('');
const remove = async () => {
  if (busy.value) return;
  busy.value = true;
  error.value = '';
  try {
    await expedientesAPI.delete(props.expediente.id);
    emit('deleted');
  } catch (cause) {
    error.value = await getApiErrorMessage(cause, 'No se pudo eliminar el expediente. Intente nuevamente.');
  } finally { busy.value = false; }
};
</script>

<template>
  <Modal open title="Confirmar eliminación" size="lg" @close="!busy && emit('close')">
    <div class="simple-dialog">
      <h2>Expediente N.º {{ expediente.numero }}</h2>
      <p>{{ expediente.materia || 'Sin materia registrada' }}</p>
      <p>Se eliminarán este expediente y sus documentos. Esta acción no se puede deshacer.</p>
      <p v-if="error" role="alert" class="plain-notice">{{ error }}</p>
      <div class="record-actions">
        <button type="button" class="task-button" :disabled="busy" @click="emit('close')">Cancelar</button>
        <button type="button" class="task-button" :disabled="busy" @click="remove">
          {{ busy ? 'Eliminando…' : 'Sí, eliminar expediente' }}
        </button>
      </div>
    </div>
  </Modal>
</template>
