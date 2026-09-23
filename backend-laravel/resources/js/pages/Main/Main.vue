<script setup lang="ts">
import { computed, ref, unref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import {
  ArrowRight,
  CheckCircle2,
  ClipboardList,
  FilePlus2,
  FolderOpen,
  Search,
  Upload,
} from '@lucide/vue';
import { useAuth } from '@/composables/useAuth';

const router = useRouter();
const auth = useAuth();
const user = computed(() => unref(auth.user));
const searchTerm = ref('');

const quickActions = [
  {
    title: 'Registrar un expediente nuevo',
    description: 'Use esta opción si va a ingresar un expediente por primera vez.',
    helper: 'Paso 1: complete los datos y adjunte el documento.',
    icon: FilePlus2,
    href: '/expedientes?create=true',
    tone: 'amber',
  },
  {
    title: 'Ver expedientes',
    description: 'Consulte, abra o continúe trabajando en un expediente existente.',
    helper: 'Busque por número, materia, juzgado o persona.',
    icon: FolderOpen,
    href: '/expedientes',
    tone: 'sky',
  },
  {
    title: 'Cargar varios documentos',
    description: 'Registre varios archivos Word o PDF en un solo proceso.',
    helper: 'Puede cargar hasta 50 documentos a la vez.',
    icon: Upload,
    href: '/carga-masiva',
    tone: 'violet',
  },
];

const openSearch = (): void => {
  const term = searchTerm.value.trim();

  void router.push({
    path: '/expedientes',
    query: term ? { search: term } : {},
  });
};
</script>

<template>
  <div class="min-h-[calc(100vh-5rem)] bg-slate-100 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
    <div class="mx-auto max-w-6xl">
      <section class="relative overflow-hidden rounded-3xl bg-slate-950 px-6 py-8 text-white shadow-xl sm:px-8 lg:px-10 lg:py-10">
        <div class="pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full bg-sky-500/30 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full bg-violet-600/25 blur-3xl"></div>

        <div class="relative max-w-3xl">
          <p class="inline-flex items-center rounded-full bg-sky-400/15 px-3 py-1 text-sm font-bold text-sky-200 ring-1 ring-inset ring-sky-300/30">
            Inicio · tareas de hoy
          </p>
          <h1 class="mt-5 text-3xl font-black tracking-tight sm:text-4xl lg:text-5xl">
            ¿Qué necesita hacer hoy?
          </h1>
          <p class="mt-3 max-w-2xl text-base leading-7 text-slate-200 sm:text-lg">
            Hola, <span class="font-bold text-white">{{ user?.nombre || user?.username }}</span>. Elija una acción; cada pantalla le indicará el siguiente paso.
          </p>
        </div>
      </section>

      <section class="relative z-10 mx-auto -mt-5 max-w-5xl rounded-3xl border-2 border-sky-200 bg-white p-5 shadow-xl shadow-slate-300/50 sm:p-7">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-xl font-black text-slate-950 sm:text-2xl">Buscar un expediente</h2>
            <p id="main-search-help" class="mt-1 text-sm leading-5 text-slate-600">
              Escriba el número o un dato que recuerde. No necesita completar todos los campos.
            </p>
          </div>
          <span class="inline-flex w-fit items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-sm font-bold text-emerald-800">
            <CheckCircle2 class="h-4 w-4" aria-hidden="true" />
            Búsqueda rápida
          </span>
        </div>

        <form class="mt-5 flex flex-col gap-3 md:flex-row" role="search" @submit.prevent="openSearch">
          <label class="sr-only" for="main-expediente-search">Buscar un expediente</label>
          <div class="relative flex-1">
            <Search class="pointer-events-none absolute left-5 top-1/2 h-6 w-6 -translate-y-1/2 text-slate-500" aria-hidden="true" />
            <input
              id="main-expediente-search"
              v-model="searchTerm"
              type="search"
              autocomplete="off"
              aria-describedby="main-search-help"
              placeholder="Ejemplo: 12345-2024, civil o nombre de una parte"
              class="min-h-16 w-full rounded-2xl border-2 border-slate-300 bg-white py-4 pl-14 pr-5 text-lg font-medium text-slate-950 shadow-sm placeholder:text-slate-400 focus:border-sky-600 focus:outline-none focus:ring-4 focus:ring-sky-100"
            />
          </div>
          <button
            type="submit"
            class="inline-flex min-h-16 items-center justify-center rounded-2xl bg-sky-600 px-6 py-4 text-base font-extrabold text-white shadow-lg shadow-sky-200 transition-colors hover:bg-sky-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-sky-300"
          >
            <Search class="mr-2 h-5 w-5" aria-hidden="true" />
            Buscar ahora
          </button>
        </form>
      </section>

      <section class="mt-8" aria-labelledby="quick-actions-heading">
        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p class="text-sm font-extrabold uppercase tracking-[0.14em] text-sky-700">Acciones frecuentes</p>
            <h2 id="quick-actions-heading" class="mt-1 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">
              Elija lo que quiere hacer
            </h2>
          </div>
          <p class="text-sm font-medium text-slate-600">Cada botón abre una tarea concreta.</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
          <RouterLink
            v-for="action in quickActions"
            :key="action.title"
            :to="action.href"
            :class="[
              'group flex min-h-72 flex-col rounded-3xl border-2 p-6 shadow-md transition-all duration-200 hover:-translate-y-1 hover:shadow-xl focus:outline-none focus-visible:ring-4 sm:p-7',
              action.tone === 'amber'
                ? 'border-amber-300 bg-amber-50 focus-visible:ring-amber-300'
                : action.tone === 'violet'
                  ? 'border-violet-300 bg-violet-50 focus-visible:ring-violet-300'
                  : 'border-sky-300 bg-sky-50 focus-visible:ring-sky-300',
            ]"
          >
            <span
              :class="[
                'flex h-14 w-14 items-center justify-center rounded-2xl text-white shadow-md transition-transform group-hover:scale-110',
                action.tone === 'amber' ? 'bg-amber-500' : action.tone === 'violet' ? 'bg-violet-600' : 'bg-sky-600',
              ]"
            >
              <component :is="action.icon" class="h-7 w-7" aria-hidden="true" />
            </span>
            <h3 class="mt-5 text-xl font-black leading-6 text-slate-950">{{ action.title }}</h3>
            <p class="mt-3 text-base leading-6 text-slate-700">{{ action.description }}</p>
            <p
              :class="[
                'mt-4 rounded-xl px-3 py-2 text-sm font-bold leading-5',
                action.tone === 'amber'
                  ? 'bg-amber-100 text-amber-950'
                  : action.tone === 'violet'
                    ? 'bg-violet-100 text-violet-950'
                    : 'bg-sky-100 text-sky-950',
              ]"
            >
              {{ action.helper }}
            </p>
            <span class="mt-auto inline-flex items-center pt-5 text-sm font-extrabold text-slate-950">
              Abrir esta tarea
              <ArrowRight class="ml-2 h-5 w-5 transition-transform group-hover:translate-x-1" aria-hidden="true" />
            </span>
          </RouterLink>
        </div>
      </section>

      <section class="mt-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7" aria-labelledby="guide-heading">
        <div class="flex items-start gap-4">
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-800">
            <ClipboardList class="h-6 w-6" aria-hidden="true" />
          </span>
          <div>
            <p class="text-sm font-extrabold uppercase tracking-[0.14em] text-emerald-700">Guía rápida</p>
            <h2 id="guide-heading" class="mt-1 text-xl font-black text-slate-950">Tres pasos para trabajar con tranquilidad</h2>
          </div>
        </div>
        <ol class="mt-6 grid gap-4 md:grid-cols-3">
          <li class="rounded-2xl bg-slate-50 p-4">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-950 text-sm font-black text-white">1</span>
            <p class="mt-3 font-extrabold text-slate-950">Busque o cree</p>
            <p class="mt-1 text-sm leading-5 text-slate-600">Encuentre el expediente o registre uno nuevo.</p>
          </li>
          <li class="rounded-2xl bg-slate-50 p-4">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-sky-600 text-sm font-black text-white">2</span>
            <p class="mt-3 font-extrabold text-slate-950">Complete la información</p>
            <p class="mt-1 text-sm leading-5 text-slate-600">La pantalla le mostrará qué dato falta y qué sigue.</p>
          </li>
          <li class="rounded-2xl bg-slate-50 p-4">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-600 text-sm font-black text-white">3</span>
            <p class="mt-3 font-extrabold text-slate-950">Guarde o descargue</p>
            <p class="mt-1 text-sm leading-5 text-slate-600">Confirme el resultado antes de cerrar la tarea.</p>
          </li>
        </ol>
      </section>
    </div>
  </div>
</template>
