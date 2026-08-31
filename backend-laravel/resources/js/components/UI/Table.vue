<script setup lang="ts">
import { computed, getCurrentInstance } from 'vue';

interface TableColumn {
  key: string;
  label?: string;
  header?: string;
  headerClass?: string;
  cellClass?: string;
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
    fixedLayout?: boolean;
    stackOnMobile?: boolean;
  }>(),
  {
    rows: () => [],
    data: undefined,
    loading: false,
    emptyMessage: 'No hay datos disponibles',
    fixedLayout: false,
    stackOnMobile: false,
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
    <div :class="props.stackOnMobile ? 'overflow-visible' : 'overflow-x-auto'">
      <table
        :class="[
          'min-w-full divide-y divide-gray-200',
          props.fixedLayout ? 'table-fixed' : '',
          props.stackOnMobile ? 'stacked-table' : '',
        ]"
      >
        <thead class="bg-gray-50">
          <tr>
            <th
              v-for="column in props.columns"
              :key="column.key"
              :class="[
                'px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500',
                column.headerClass,
              ]"
            >
              {{ columnLabel(column) }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <tr v-if="displayedRows.length === 0">
            <td
              :colspan="props.columns.length"
              class="empty-cell px-6 py-12 text-center text-gray-500"
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
                'data-row transition-colors',
              ]"
              @click="emit('row-click', row)"
            >
              <td
                v-for="column in props.columns"
                :key="column.key"
                :data-label="columnLabel(column)"
                :class="[
                  'px-6 py-4 text-sm text-gray-900',
                  column.cellClass ?? 'max-w-[200px]',
                ]"
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

<style scoped>
@media (max-width: 1279px) {
  .stacked-table,
  .stacked-table tbody {
    display: block;
    width: 100%;
  }

  .stacked-table thead {
    display: none;
  }

  .stacked-table tbody {
    padding: 0.75rem;
  }

  .stacked-table tbody .data-row {
    display: block;
    overflow: hidden;
    margin-bottom: 0.75rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
  }

  .stacked-table tbody tr:last-child {
    margin-bottom: 0;
  }

  .stacked-table td[data-label] {
    display: grid;
    grid-template-columns: minmax(7rem, 9rem) minmax(0, 1fr);
    max-width: none !important;
    gap: 0.75rem;
    align-items: center;
    padding: 0.75rem 1rem;
    overflow-wrap: anywhere;
  }

  .stacked-table td[data-label]::before {
    content: attr(data-label);
    color: #6b7280;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    line-height: 1rem;
    text-transform: uppercase;
  }

  .stacked-table .empty-cell {
    display: block;
    max-width: none;
  }
}
</style>
