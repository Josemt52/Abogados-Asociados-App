import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { VueWrapper } from '@vue/test-utils';
import RichTextEditor from '@/components/RichTextEditor.vue';

const emptyDocument = {
    type: 'doc',
    content: [{ type: 'paragraph', attrs: { textAlign: 'left' } }],
};

let wrapper: VueWrapper | null = null;

const waitForEditor = async (): Promise<void> => {
    await vi.waitFor(() => {
        expect(wrapper?.find('[role="textbox"]').exists()).toBe(true);
    });
};

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
});

describe('RichTextEditor', () => {
    it('muestra únicamente las herramientas básicas autorizadas', async () => {
        wrapper = mount(RichTextEditor, {
            props: { modelValue: emptyDocument },
        });
        await waitForEditor();

        expect(wrapper.get('[role="toolbar"]').attributes('aria-label')).toBe(
            'Herramientas de formato',
        );
        expect(wrapper.get('section').classes()).toContain('overflow-clip');
        expect(wrapper.get('[role="toolbar"]').classes()).toEqual(
            expect.arrayContaining(['sticky', 'top-0', 'z-10']),
        );
        expect(wrapper.get('[role="textbox"]').attributes('aria-label')).toBe(
            'Contenido de la resolución',
        );

        const labels = wrapper
            .findAll('button[aria-label]')
            .map((button) => button.attributes('aria-label'));

        expect(labels).toEqual([
            'Deshacer',
            'Rehacer',
            'Negrita',
            'Subrayado',
            'Alinear a la izquierda',
            'Centrar',
            'Alinear a la derecha',
            'Justificar',
        ]);
        expect(wrapper.find('[aria-label="Insertar enlace"]').exists()).toBe(false);
        expect(wrapper.find('[aria-label="Insertar imagen"]').exists()).toBe(false);
    });

    it('activa negrita, alineación y tamaño desde la barra', async () => {
        wrapper = mount(RichTextEditor, {
            props: { modelValue: emptyDocument },
        });
        await waitForEditor();

        const bold = wrapper.get('button[aria-label="Negrita"]');
        await bold.trigger('click');
        expect(bold.attributes('aria-pressed')).toBe('true');

        const center = wrapper.get('button[aria-label="Centrar"]');
        await center.trigger('click');
        expect(center.attributes('aria-pressed')).toBe('true');

        const size = wrapper.get('select[aria-label="Tamaño de texto"]');
        await size.setValue('14pt');
        expect((size.element as HTMLSelectElement).value).toBe('14pt');
    });
});
