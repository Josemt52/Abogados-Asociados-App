import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CargaMasiva from '@/pages/CargaMasiva/CargaMasiva.vue';

const mocks = vi.hoisted(() => ({
    create: vi.fn(),
    upload: vi.fn(),
    getProgress: vi.fn(),
    toastSuccess: vi.fn(),
    toastError: vi.fn(),
}));

vi.mock('@/api', () => ({
    cargasMasivasAPI: {
        create: mocks.create,
        upload: mocks.upload,
        getProgress: mocks.getProgress,
    },
}));

vi.mock('@/composables/useToast', () => ({
    useToast: () => ({
        success: mocks.toastSuccess,
        error: mocks.toastError,
    }),
}));

const mountPage = () =>
    mount(CargaMasiva, {
        global: {
            stubs: {
                RouterLink: {
                    props: ['to'],
                    template: '<a><slot /></a>',
                },
            },
        },
    });

const selectFiles = async (wrapper: ReturnType<typeof mountPage>, files: File[]): Promise<void> => {
    const input = wrapper.get<HTMLInputElement>('input[type="file"]');
    Object.defineProperty(input.element, 'files', {
        configurable: true,
        value: files,
    });
    await input.trigger('change');
};

describe('CargaMasiva', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('limita la selección a 50 documentos Word', async () => {
        const wrapper = mountPage();
        const files = Array.from(
            { length: 51 },
            (_, index) => new File(['word'], `expediente-${index}.docx`, { type: 'application/zip' }),
        );

        await selectFiles(wrapper, files);

        expect(wrapper.text()).toContain('50 / 50');
        expect(mocks.toastError).toHaveBeenCalledWith(
            'Puedes procesar como máximo 50 documentos por lote.',
        );
        wrapper.unmount();
    });

    it('conserva documentos diferentes aunque compartan nombre y metadatos', async () => {
        const wrapper = mountPage();
        const options = { type: 'application/zip', lastModified: 1234 };

        await selectFiles(wrapper, [
            new File(['contenido uno'], 'expediente.docx', options),
            new File(['contenido dos'], 'expediente.docx', options),
        ]);

        expect(wrapper.text()).toContain('2 / 50');
        wrapper.unmount();
    });

    it('bloquea una segunda creación mientras el servidor reserva el lote', async () => {
        const wrapper = mountPage();
        let resolveCreate: ((value: unknown) => void) | undefined;
        mocks.create.mockReturnValue(
            new Promise((resolve) => {
                resolveCreate = resolve;
            }),
        );
        await selectFiles(wrapper, [
            new File(['uno'], 'uno.docx', { type: 'application/zip' }),
        ]);
        const button = wrapper
            .findAll('button')
            .find((candidate) => candidate.text().includes('Procesar expedientes'));

        await button?.trigger('click');
        await button?.trigger('click');

        expect(mocks.create).toHaveBeenCalledTimes(1);
        resolveCreate?.({
            id: 'batch-1',
            estado: 'cargando',
            total: 1,
            recibidos: 0,
            procesados: 0,
            progreso: 0,
            cargas: [{ id: 10, nombre: 'uno.docx' }],
        });
        await flushPromises();
        wrapper.unmount();
    });

    it('sube uno por uno y muestra el contador procesado del servidor', async () => {
        const wrapper = mountPage();
        const files = [
            new File(['uno'], 'uno.docx', { type: 'application/zip' }),
            new File(['dos'], 'dos.doc', { type: 'application/msword' }),
        ];
        mocks.create.mockResolvedValue({
            id: 'batch-1',
            estado: 'cargando',
            total: 2,
            recibidos: 0,
            procesados: 0,
            progreso: 0,
            cargas: [
                { id: 10, nombre: 'uno.docx' },
                { id: 11, nombre: 'dos.doc' },
            ],
        });
        mocks.upload
            .mockResolvedValueOnce({
                id: 'batch-1',
                estado: 'procesando',
                total: 2,
                recibidos: 1,
                procesados: 1,
                progreso: 50,
            })
            .mockResolvedValueOnce({
                id: 'batch-1',
                estado: 'procesando',
                total: 2,
                recibidos: 2,
                procesados: 1,
                progreso: 50,
            });
        mocks.getProgress.mockResolvedValue({
            id: 'batch-1',
            estado: 'completado',
            total: 2,
            recibidos: 2,
            procesados: 2,
            progreso: 100,
        });

        await selectFiles(wrapper, files);
        const button = wrapper
            .findAll('button')
            .find((candidate) => candidate.text().includes('Procesar expedientes'));
        await button?.trigger('click');
        await flushPromises();

        expect(mocks.upload).toHaveBeenCalledTimes(2);
        expect(mocks.upload.mock.calls[0][1]).toBe(10);
        expect(mocks.upload.mock.calls[1][1]).toBe(11);
        expect(wrapper.text()).toContain('2 de 2 procesados');
        expect(wrapper.text()).toContain('Lote procesado');
        expect(mocks.toastSuccess).toHaveBeenCalledWith('La carga masiva terminó correctamente.');
        wrapper.unmount();
    });
});
