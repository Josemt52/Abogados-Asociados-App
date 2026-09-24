import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import { describe, expect, it, vi } from 'vitest';
import NuevoExpediente from '@/pages/Expedientes/NuevoExpediente.vue';
import BackButton from '@/components/UI/BackButton.vue';

const mocks = vi.hoisted(() => ({ create: vi.fn() }));
vi.mock('@/api', () => ({ expedientesAPI: { create: mocks.create } }));
vi.mock('@/composables/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }));
const makeRouter = () => createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/main', component: { template: '<div />' } },
    { path: '/expedientes', component: { template: '<div />' } },
    { path: '/expedientes/nuevo', component: NuevoExpediente },
    { path: '/expedientes/:id', name: 'expediente-detail', component: { template: '<div />' } },
  ],
});
describe('Nuevo expediente y navegación', () => {
  it('crea con el formulario existente y abre el expediente creado', async () => {
    mocks.create.mockResolvedValue({ id: 9, numero: 'NUEVO-2026' });
    const router = makeRouter();
    await router.push('/expedientes/nuevo');
    const wrapper = mount(NuevoExpediente, { global: { plugins: [router] } });
    await wrapper.get('#numero').setValue('NUEVO-2026');
    await wrapper.get('form').trigger('submit');
    await flushPromises();
    expect(mocks.create).toHaveBeenCalledWith(expect.objectContaining({ numero: 'NUEVO-2026' }));
    expect(router.currentRoute.value.params.id).toBe('9');
    wrapper.unmount();
  });
  it('volver utiliza la página anterior cuando existe', async () => {
    const router = makeRouter();
    await router.push('/main');
    await router.push('/expedientes');
    const wrapper = mount(BackButton, { global: { plugins: [router] } });
    await wrapper.get('button').trigger('click');
    await flushPromises();
    expect(router.currentRoute.value.path).toBe('/main');
    wrapper.unmount();
  });
  it('un acceso directo puede volver al menú sin salir de la aplicación', async () => {
    const router = makeRouter();
    await router.push('/expedientes');
    const wrapper = mount(BackButton, { global: { plugins: [router] } });
    await wrapper.get('button').trigger('click');
    await flushPromises();
    expect(router.currentRoute.value.path).toBe('/main');
    wrapper.unmount();
  });
});

