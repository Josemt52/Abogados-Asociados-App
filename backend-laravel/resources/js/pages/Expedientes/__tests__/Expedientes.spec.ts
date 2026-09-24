import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Expedientes from '@/pages/Expedientes/Expedientes.vue';
import DeleteExpedienteModal from '@/components/DeleteExpedienteModal.vue';
import Main from '@/pages/Main/Main.vue';

const mocks = vi.hoisted(() => ({ getAll: vi.fn(), remove: vi.fn() }));
vi.mock('@/api', () => ({ expedientesAPI: { getAll: mocks.getAll, delete: mocks.remove } }));
vi.mock('@/composables/useAuth', () => ({ useAuth: () => ({ isAdmin: false }) }));
const record = { id: 7, numero: '001-2026', materia: 'Civil', demandante: 'Persona A', demandado: 'Persona B' };
const wrappers: ReturnType<typeof mount>[] = [];
const setup = async (action = 'ver', main = false) => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/main', name: 'main', component: Main },
      { path: '/expedientes', name: 'expedientes', component: Expedientes },
      { path: '/expedientes/nuevo', name: 'expediente-new', component: { template: '<div>Nuevo expediente</div>' } },
      { path: '/expedientes/:id', name: 'expediente-detail', component: { template: '<div>Editor destino</div>' } },
      { path: '/carga-masiva', component: { template: '<div />' } },
    ],
  });
  await router.push(main ? '/main' : '/expedientes?accion=' + action);
  await router.isReady();
  const wrapper = mount(main ? Main : Expedientes, { global: { plugins: [router], stubs: { Teleport: true } } });
  wrappers.push(wrapper);
  return { wrapper, router };
};
const search = async (wrapper: ReturnType<typeof mount>, number = '001-2026') => {
  await wrapper.get('input').setValue(number);
  await wrapper.get('form').trigger('submit');
  await flushPromises();
};
beforeEach(() => {
  vi.clearAllMocks();
  mocks.getAll.mockResolvedValue([record]);
  mocks.remove.mockResolvedValue(undefined);
});
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); });
describe('Flujo sencillo de expedientes', () => {
  it('muestra exactamente las cinco opciones del menú en orden', async () => {
    const { wrapper } = await setup('ver', true);
    expect(wrapper.findAll('nav a').map(link => link.text())).toEqual([
      'Ver expedientes', 'Nuevo expediente', 'Actualizar expedientes', 'Eliminar expedientes', 'Carga masiva de expedientes',
    ]);
    expect(wrapper.find('aside').exists()).toBe(false);
    expect(wrapper.find('a[href="/paneladmin"]').exists()).toBe(false);
    expect(wrapper.findAll('nav a')[1].attributes('href')).toBe('/expedientes/nuevo');
  });
  it('carga la lista al entrar y muestra las cuatro acciones al encontrar el número', async () => {
    const { wrapper } = await setup();
    await flushPromises();
    expect(mocks.getAll).toHaveBeenCalledOnce();
    expect(wrapper.get('table').text()).toContain('001-2026');
    await search(wrapper, ' 001-2026 ');
    expect(wrapper.get('.record-actions').text()).toContain('Ver expediente 001-2026');
    expect(wrapper.get('.record-actions').findAll('button, a')).toHaveLength(4);
    expect(mocks.remove).not.toHaveBeenCalled();
  });
  it('no selecciona coincidencias parciales', async () => {
    const { wrapper } = await setup('eliminar');
    await search(wrapper, '001');
    expect(wrapper.text()).toContain('No se encontró');
    expect(wrapper.findComponent(DeleteExpedienteModal).exists()).toBe(false);
    expect(mocks.remove).not.toHaveBeenCalled();
  });
  it.each(['editar', 'actualizar'])('%s envía el expediente encontrado al editor existente', async (action) => {
    const { wrapper, router } = await setup(action);
    await search(wrapper);
    expect(router.currentRoute.value.name).toBe('expediente-detail');
    expect(router.currentRoute.value.params.id).toBe('7');
    expect(router.currentRoute.value.query.editor).toBe('true');
  });
  it('distingue un fallo de consulta de un expediente inexistente', async () => {
    mocks.getAll.mockRejectedValue(new Error('sin conexión'));
    const { wrapper } = await setup();
    await search(wrapper);
    expect(wrapper.get('[role="alert"]').text()).toContain('sin conexión');
    expect(wrapper.text()).not.toContain('No se encontró');
  });
  it('descarta una respuesta si el usuario cambia el número mientras busca', async () => {
    let resolve!: (rows: typeof record[]) => void;
    mocks.getAll.mockReturnValue(new Promise(done => { resolve = done; }));
    const { wrapper } = await setup();
    await wrapper.get('input').setValue('001-2026');
    await wrapper.get('form').trigger('submit');
    await wrapper.get('input').setValue('002-2026');
    resolve([record]);
    await flushPromises();
    expect(wrapper.find('.search-result').exists()).toBe(false);
  });
  it('cancelar no elimina y confirmar elimina solo el expediente encontrado', async () => {
    const { wrapper } = await setup('eliminar');
    await search(wrapper);
    expect(wrapper.findComponent(DeleteExpedienteModal).text()).toContain('001-2026');
    expect(mocks.remove).not.toHaveBeenCalled();
    await wrapper.findAll('button').find(button => button.text() === 'Cancelar')!.trigger('click');
    expect(mocks.remove).not.toHaveBeenCalled();
    await wrapper.get('.record-actions').findAll('button').find(button => button.text() === 'Eliminar expediente')!.trigger('click');
    await wrapper.findAll('button').find(button => button.text() === 'Sí, eliminar expediente')!.trigger('click');
    await flushPromises();
    expect(mocks.remove).toHaveBeenCalledExactlyOnceWith(7);
    expect(wrapper.text()).toContain('fue eliminado');
    expect(wrapper.find('.search-result').exists()).toBe(false);
    expect(wrapper.find('table').exists()).toBe(false);
  });
  it('mantiene la confirmación abierta si falla la eliminación', async () => {
    mocks.remove.mockRejectedValue(new Error('sin conexión'));
    const { wrapper } = await setup('eliminar');
    await search(wrapper);
    await wrapper.findAll('button').find(button => button.text() === 'Sí, eliminar expediente')!.trigger('click');
    await flushPromises();
    expect(wrapper.findComponent(DeleteExpedienteModal).exists()).toBe(true);
    expect(wrapper.get('[role="alert"]').text()).toContain('sin conexión');
  });
  it('permite seleccionar de la lista sin escribir el número', async () => {
    const { wrapper } = await setup();
    await flushPromises();
    await wrapper.get('button[aria-label="Seleccionar expediente 001-2026"]').trigger('click');
    expect(wrapper.get('.search-result').text()).toContain('001-2026');
    expect(mocks.remove).not.toHaveBeenCalled();
  });
  it('seleccionar de la lista de actualizar abre el editor', async () => {
    const { wrapper, router } = await setup('actualizar');
    await flushPromises();
    await wrapper.get('button[aria-label="Seleccionar expediente 001-2026"]').trigger('click');
    await flushPromises();
    expect(router.currentRoute.value.query.editor).toBe('true');
  });
  it('pagina la lista sin omitir expedientes', async () => {
    mocks.getAll.mockResolvedValue(Array.from({ length: 16 }, (_, index) => ({ ...record, id: index + 1, numero: 'EXP-' + (index + 1) })));
    const { wrapper } = await setup();
    await flushPromises();
    expect(wrapper.findAll('tbody tr')).toHaveLength(15);
    await wrapper.findAll('button').find(button => button.text() === 'Siguiente')!.trigger('click');
    expect(wrapper.findAll('tbody tr')).toHaveLength(1);
    expect(wrapper.get('tbody').text()).toContain('EXP-16');
  });
});
