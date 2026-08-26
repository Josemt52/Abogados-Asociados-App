import { defineComponent } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ResolutionEdit from '@/pages/ResolutionEdit/ResolutionEdit.vue';

const mocks = vi.hoisted(() => ({
    getEditor: vi.fn(),
    saveEditor: vi.fn(),
    finalizeEditor: vi.fn(),
    push: vi.fn(),
    toastSuccess: vi.fn(),
    toastError: vi.fn(),
}));

vi.mock('vue-router', () => ({
    onBeforeRouteLeave: vi.fn(),
    useRoute: () => ({
        params: { expedienteId: '10', resolucionId: '20' },
    }),
    useRouter: () => ({
        push: mocks.push,
    }),
}));

vi.mock('@/api', () => ({
    expedientesAPI: {
        getEditorResolucion: mocks.getEditor,
        guardarEditorResolucion: mocks.saveEditor,
        finalizarEditorResolucion: mocks.finalizeEditor,
    },
}));

vi.mock('@/composables/useToast', () => ({
    useToast: () => ({
        success: mocks.toastSuccess,
        error: mocks.toastError,
    }),
}));

const editorPayload = {
    expediente_id: 10,
    resolucion_id: 20,
    numero: 4,
    estado: 'pendiente' as const,
    document_name: 'expediente_resolucion_4.docx',
    header: [{ label: 'Expediente', value: 'EXP-ORIGINAL' }],
    header_data: {
        numero: 'EXP-ORIGINAL',
        materia: 'Materia original',
        juzgado: '',
        especialista: '',
        tercero: '',
        demandado: '',
        demandante: '',
    },
    content: {
        type: 'doc',
        content: [{ type: 'paragraph', attrs: { textAlign: 'left' } }],
    },
    version: 0,
    saved_at: null,
};

const RichTextEditorStub = defineComponent({
    name: 'RichTextEditor',
    props: {
        modelValue: { type: Object, required: true },
        disabled: Boolean,
        ariaLabel: String,
    },
    emits: ['update:modelValue'],
    template: '<div><slot name="before-content" /></div>',
});

describe('ResolutionEdit', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mocks.getEditor.mockResolvedValue(structuredClone(editorPayload));
        mocks.saveEditor.mockImplementation(
            async (
                _expedienteId: number,
                _resolucionId: number,
                content: object,
                headerData: object,
            ) => ({
                ...structuredClone(editorPayload),
                content,
                header_data: headerData,
                version: 1,
            }),
        );
    });

    it('guarda los cambios de la cabecera junto con el borrador', async () => {
        const wrapper = mount(ResolutionEdit, {
            global: {
                stubs: { RichTextEditor: RichTextEditorStub },
            },
        });
        await flushPromises();

        const numberInput = wrapper.get<HTMLInputElement>('#header-numero');
        const materiaInput = wrapper.get<HTMLInputElement>('#header-materia');
        expect(numberInput.element.value).toBe('EXP-ORIGINAL');

        await numberInput.setValue('EXP-ACTUALIZADO');
        await materiaInput.setValue('Materia actualizada');

        const saveButton = wrapper
            .findAll('button')
            .find((button) => button.text().trim() === 'Guardar');
        expect(saveButton).toBeDefined();
        expect(saveButton?.attributes('disabled')).toBeUndefined();

        await saveButton?.trigger('click');
        await flushPromises();

        expect(mocks.saveEditor).toHaveBeenCalledWith(
            10,
            20,
            editorPayload.content,
            expect.objectContaining({
                numero: 'EXP-ACTUALIZADO',
                materia: 'Materia actualizada',
            }),
            0,
        );
        expect(mocks.toastSuccess).toHaveBeenCalledWith('Borrador guardado correctamente.');

        wrapper.unmount();
    });
});
