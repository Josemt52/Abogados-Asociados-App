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
    it('muestra herramientas de formato, listas y sangría', async () => {
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
            'Cursiva',
            'Alinear a la izquierda',
            'Centrar',
            'Alinear a la derecha',
            'Justificar',
            'Lista numerada',
            'Lista con viñetas',
            'Lista con guiones',
            'Aumentar sangría de lista',
            'Reducir sangría de lista',
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
    it('crea numeración, cambia a guiones y conserva su estructura al recargar', async () => {
        wrapper = mount(RichTextEditor, { props: { modelValue: {
            type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Primer punto' }] }],
        } } });
        await waitForEditor();
        await wrapper.get('button[aria-label="Lista numerada"]').trigger('click');
        expect(wrapper.find('[role="textbox"] ol li').exists()).toBe(true);
        await wrapper.get('button[aria-label="Lista con guiones"]').trigger('click');
        expect(wrapper.find('[role="textbox"] ul[data-marker="dash"] li').exists()).toBe(true);
        const events = wrapper.emitted('update:modelValue')!;
        const saved = events[events.length - 1][0] as Record<string, unknown>;
        wrapper.unmount();
        wrapper = mount(RichTextEditor, { props: { modelValue: saved } });
        await waitForEditor();
        expect(wrapper.get('[role="textbox"] ul').attributes('data-marker')).toBe('dash');
        expect(wrapper.text()).toContain('Primer punto');
        await wrapper.get('button[aria-label="Lista con viñetas"]').trigger('click');
        expect(wrapper.get('[role="textbox"] ul').attributes('data-marker')).toBe('bullet');
        await wrapper.get('button[aria-label="Lista con viñetas"]').trigger('click');
        expect(wrapper.find('[role="textbox"] ul').exists()).toBe(false);
    });
    it('permite cursiva y desactiva los controles al guardar', async () => {
        wrapper = mount(RichTextEditor, { props: { modelValue: emptyDocument } });
        await waitForEditor();
        await wrapper.get('button[aria-label="Cursiva"]').trigger('click');
        expect(wrapper.get('button[aria-label="Cursiva"]').attributes('aria-pressed')).toBe('true');
        await wrapper.setProps({ disabled: true });
        expect(wrapper.get('button[aria-label="Lista numerada"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('button[aria-label="Cursiva"]').attributes('disabled')).toBeDefined();
    });
});
