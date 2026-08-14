<script setup lang="ts">
import { computed, getCurrentInstance } from 'vue';

interface TableColumn {
  key: string;
  label?: string;
  header?: string;
}

// Las filas son deliberadamente genéricas: la tabla se comparte entre
// expedientes, usuarios y cualquier otro recurso del dominio.
type TableRow = any;

const props = withDefaults(
  defineProps<{
    columns: TableColumn[];
    rows?: TableRow[];
    data?: TableRow[];
    loading?: boolean;
    emptyMessage?: string;
  }>(),
  {
    rows: () => [],
    data: undefined,
    loading: false,
    emptyMessage: 'No hay datos disponibles',
  },
);

const emit = defineEmits<{
  'row-click': [row: TableRow];
}>();

const instance = getCurrentInstance();
const hasRowClickListener = Boolean(instance?.vnode.props?.onRowClick);

const displayedRows = computed(() =>
  props.data !== undefined && props.rows.length === 0 ? props.data : props.rows,
);

const columnLabel = (column: TableColumn) =>
  column.label ?? column.header ?? column.key;

const cellValue = (row: TableRow, key: string) => row[key];
</script>

<template>
  <div
    v-if="props.loading"
    class="rounded-lg bg-white shadow-sm"
  >
    <div class="p-8 text-center">
      <div class="animate-pulse">
        <div class="mx-auto mb-4 h-4 w-3/4 rounded bg-gray-200" />
        <div class="mx-auto mb-4 h-4 w-1/2 rounded bg-gray-200" />
        <div class="mx-auto h-4 w-5/6 rounded bg-gray-200" />
      </div>
    </div>
  </div>

  <div
    v-else
    class="overflow-hidden rounded-lg bg-white shadow-sm"
  >
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th
              v-for="column in props.columns"
              :key="column.key"
              class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
            >
              {{ columnLabel(column) }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <tr v-if="displayedRows.length === 0">
            <td
              :colspan="props.columns.length"
              class="px-6 py-12 text-center text-gray-500"
            >
              {{ props.emptyMessage }}
            </td>
          </tr>
          <template v-else>
            <tr
              v-for="(row, index) in displayedRows"
              :key="String(row.id ?? index)"
              :class="[
                hasRowClickListener ? 'cursor-pointer hover:bg-gray-50' : '',
                'transition-colors',
              ]"
              @click="emit('row-click', row)"
            >
              <td
                v-for="column in props.columns"
                :key="column.key"
                class="px-6 py-4 text-sm text-gray-900"
                style="max-width: 200px"
              >
                <slot
                  :name="`cell-${column.key}`"
                  :value="cellValue(row, column.key)"
                  :row="row"
                  :column="column"
                >
                  <div
                    v-if="typeof cellValue(row, column.key) === 'string'"
                    class="truncate"
                    :title="String(cellValue(row, column.key))"
                  >
                    {{ cellValue(row, column.key) }}
                  </div>
                  <template v-else>
                    {{ cellValue(row, column.key) }}
                  </template>
                </slot>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>
