import { shallowMount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Main from '@/pages/Main/Main.vue';

describe('Main', () => {
    it('muestra la identidad completa y centrada sobre el menú', () => {
        const wrapper = shallowMount(Main, {
            global: {
                stubs: { RouterLink: true },
            },
        });

        expect(wrapper.get('.home-identity h1').text()).toBe(
            'Abogados y Asociados Arias Carrazco',
        );
        expect(wrapper.get('.home-identity').classes()).toContain('home-identity');
    });
});
