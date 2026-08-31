import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Table from '@/components/UI/Table.vue';

const columns = [
    { key: 'numero', label: 'Número de expediente', headerClass: 'xl:w-[60%]' },
    { key: 'acciones', label: 'Acciones', cellClass: 'max-w-none' },
];

const rows = [
    { id: 1, numero: '00061-2023-0-0401-JR-CI-07', acciones: '' },
];

describe('Table', () => {
    it('activa el modo apilado sin un contenedor de desplazamiento horizontal', () => {
        const wrapper = mount(Table, {
            props: {
                columns,
                rows,
                fixedLayout: true,
                stackOnMobile: true,
            },
        });

        const table = wrapper.get('table');
        const tableContainer = table.element.parentElement;

        expect(table.classes()).toEqual(expect.arrayContaining(['table-fixed', 'stacked-table']));
        expect(tableContainer?.classList.contains('overflow-visible')).toBe(true);
        expect(tableContainer?.classList.contains('overflow-x-auto')).toBe(false);
        expect(wrapper.get('td[data-label="Número de expediente"]').text()).toBe(
            '00061-2023-0-0401-JR-CI-07',
        );
        expect(wrapper.get('td[data-label="Acciones"]').classes()).toContain('max-w-none');
    });

    it('conserva el desplazamiento de la tabla compartida cuando no se solicita el modo apilado', () => {
        const wrapper = mount(Table, { props: { columns, rows } });
        const table = wrapper.get('table');
        const tableContainer = table.element.parentElement;

        expect(table.classes()).not.toContain('stacked-table');
        expect(tableContainer?.classList.contains('overflow-x-auto')).toBe(true);
    });
});
