<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { Editor, JSONContent } from '@tiptap/core';
import TextAlign from '@tiptap/extension-text-align';
import { FontSize, TextStyle } from '@tiptap/extension-text-style';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    Bold,
    Redo2,
    TextAlignCenter,
    TextAlignEnd,
    TextAlignJustify,
    TextAlignStart,
    Underline,
    Undo2,
} from '@lucide/vue';

type TextAlignment = 'left' | 'center' | 'right' | 'justify';

const props = withDefaults(
    defineProps<{
        modelValue: JSONContent;
        disabled?: boolean;
        ariaLabel?: string;
    }>(),
    {
        disabled: false,
        ariaLabel: 'Contenido de la resolución',
    },
);

const emit = defineEmits<{
    'update:modelValue': [content: JSONContent];
    change: [content: JSONContent];
}>();

const fontSizes = [8, 9, 10, 11, 12, 14, 16, 18, 20, 24];
const activeBold = ref(false);
const activeUnderline = ref(false);
const activeAlignment = ref<TextAlignment>('left');
const selectedFontSize = ref('12pt');
const canUndo = ref(false);
const canRedo = ref(false);

const syncToolbarState = (instance: Editor): void => {
    activeBold.value = instance.isActive('bold');
    activeUnderline.value = instance.isActive('underline');
    canUndo.value = instance.can().undo();
    canRedo.value = instance.can().redo();

    const fontSize = instance.getAttributes('textStyle').fontSize;
    selectedFontSize.value =
        typeof fontSize === 'string' && /^\d{1,2}pt$/.test(fontSize) ? fontSize : '12pt';

    activeAlignment.value =
        (['center', 'right', 'justify'] as const).find((alignment) =>
            instance.isActive({ textAlign: alignment }),
        ) ?? 'left';
};

const editor = useEditor({
    content: props.modelValue,
    editable: !props.disabled,
    extensions: [
        StarterKit.configure({
            blockquote: false,
            bulletList: false,
            code: false,
            codeBlock: false,
            heading: false,
            horizontalRule: false,
            italic: false,
            link: false,
            listItem: false,
            listKeymap: false,
            orderedList: false,
            strike: false,
            trailingNode: false,
        }),
        TextStyle,
        FontSize,
        TextAlign.configure({
            types: ['paragraph'],
            alignments: ['left', 'center', 'right', 'justify'],
            defaultAlignment: 'left',
        }),
    ],
    editorProps: {
        attributes: {
            class: 'legal-editor-content',
            role: 'textbox',
            'aria-multiline': 'true',
            'aria-label': props.ariaLabel,
        },
    },
    onCreate: ({ editor: instance }) => syncToolbarState(instance),
    onSelectionUpdate: ({ editor: instance }) => syncToolbarState(instance),
    onUpdate: ({ editor: instance }) => {
        syncToolbarState(instance);
        const content = instance.getJSON();
        emit('update:modelValue', content);
        emit('change', content);
    },
});

const isUnavailable = computed(() => props.disabled || !editor.value);

const runCommand = (command: (instance: Editor) => void): void => {
    const instance = editor.value;

    if (!instance || props.disabled) {
        return;
    }

    command(instance);
    syncToolbarState(instance);
};

const toggleBold = (): void =>
    runCommand((instance) => {
        instance.chain().focus().toggleBold().run();
    });

const toggleUnderline = (): void =>
    runCommand((instance) => {
        instance.chain().focus().toggleUnderline().run();
    });

const undo = (): void =>
    runCommand((instance) => {
        instance.chain().focus().undo().run();
    });

const redo = (): void =>
    runCommand((instance) => {
        instance.chain().focus().redo().run();
    });

const setAlignment = (alignment: TextAlignment): void =>
    runCommand((instance) => {
        instance.chain().focus().setTextAlign(alignment).run();
    });

const handleFontSizeChange = (event: Event): void => {
    const target = event.target as HTMLSelectElement;
    const fontSize = target.value;

    if (!/^\d{1,2}pt$/.test(fontSize)) {
        return;
    }

    runCommand((instance) => {
        instance.chain().focus().setFontSize(fontSize).run();
    });
};

const controlClasses = (active = false): string[] => [
    'inline-flex min-h-11 min-w-11 items-center justify-center rounded-md border px-3 py-2 text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40',
    active
        ? 'border-gray-800 bg-gray-800 text-white'
        : 'border-gray-300 bg-white text-gray-800 hover:bg-gray-100',
];

watch(
    () => props.disabled,
    (disabled) => editor.value?.setEditable(!disabled),
);

watch(
    () => props.modelValue,
    (content) => {
        const instance = editor.value;

        if (!instance || JSON.stringify(instance.getJSON()) === JSON.stringify(content)) {
            return;
        }

        instance.commands.setContent(content, { emitUpdate: false });
        syncToolbarState(instance);
    },
    { deep: true },
);
</script>

<template>
    <section class="overflow-clip rounded-xl border border-gray-300 bg-gray-100 shadow-sm">
        <div
            class="sticky top-0 z-10 flex flex-wrap items-center gap-2 border-b border-gray-300 bg-white p-3"
            role="toolbar"
            aria-label="Herramientas de formato"
        >
            <button
                type="button"
                :class="controlClasses()"
                :disabled="isUnavailable || !canUndo"
                title="Deshacer (Ctrl+Z)"
                aria-label="Deshacer"
                @click="undo"
            >
                <Undo2 class="h-5 w-5" aria-hidden="true" />
            </button>
            <button
                type="button"
                :class="controlClasses()"
                :disabled="isUnavailable || !canRedo"
                title="Rehacer (Ctrl+Y)"
                aria-label="Rehacer"
                @click="redo"
            >
                <Redo2 class="h-5 w-5" aria-hidden="true" />
            </button>

            <span class="mx-1 h-8 w-px bg-gray-300" aria-hidden="true" />

            <button
                type="button"
                :class="controlClasses(activeBold)"
                :disabled="isUnavailable"
                :aria-pressed="activeBold"
                title="Negrita (Ctrl+B)"
                aria-label="Negrita"
                @click="toggleBold"
            >
                <Bold class="h-5 w-5" aria-hidden="true" />
            </button>
            <button
                type="button"
                :class="controlClasses(activeUnderline)"
                :disabled="isUnavailable"
                :aria-pressed="activeUnderline"
                title="Subrayado (Ctrl+U)"
                aria-label="Subrayado"
                @click="toggleUnderline"
            >
                <Underline class="h-5 w-5" aria-hidden="true" />
            </button>

            <label class="flex min-h-11 items-center gap-2 rounded-md border border-gray-300 bg-white px-3">
                <span class="text-sm font-medium text-gray-700">Tamaño</span>
                <select
                    :value="selectedFontSize"
                    :disabled="isUnavailable"
                    class="bg-white py-2 text-sm font-medium text-gray-900 focus:outline-none"
                    aria-label="Tamaño de texto"
                    @change="handleFontSizeChange"
                >
                    <option v-for="size in fontSizes" :key="size" :value="`${size}pt`">
                        {{ size }}
                    </option>
                </select>
            </label>

            <span class="mx-1 h-8 w-px bg-gray-300" aria-hidden="true" />

            <button
                type="button"
                :class="controlClasses(activeAlignment === 'left')"
                :disabled="isUnavailable"
                :aria-pressed="activeAlignment === 'left'"
                title="Alinear a la izquierda"
                aria-label="Alinear a la izquierda"
                @click="setAlignment('left')"
            >
                <TextAlignStart class="h-5 w-5" aria-hidden="true" />
            </button>
            <button
                type="button"
                :class="controlClasses(activeAlignment === 'center')"
                :disabled="isUnavailable"
                :aria-pressed="activeAlignment === 'center'"
                title="Centrar"
                aria-label="Centrar"
                @click="setAlignment('center')"
            >
                <TextAlignCenter class="h-5 w-5" aria-hidden="true" />
            </button>
            <button
                type="button"
                :class="controlClasses(activeAlignment === 'right')"
                :disabled="isUnavailable"
                :aria-pressed="activeAlignment === 'right'"
                title="Alinear a la derecha"
                aria-label="Alinear a la derecha"
                @click="setAlignment('right')"
            >
                <TextAlignEnd class="h-5 w-5" aria-hidden="true" />
            </button>
            <button
                type="button"
                :class="controlClasses(activeAlignment === 'justify')"
                :disabled="isUnavailable"
                :aria-pressed="activeAlignment === 'justify'"
                title="Justificar"
                aria-label="Justificar"
                @click="setAlignment('justify')"
            >
                <TextAlignJustify class="h-5 w-5" aria-hidden="true" />
            </button>
        </div>

        <div class="overflow-x-auto p-3 sm:p-6">
            <div class="mx-auto min-h-[70rem] w-full max-w-[52rem] bg-white px-6 py-10 shadow sm:px-12 sm:py-12">
                <slot name="before-content" />
                <EditorContent v-if="editor" :editor="editor" />
            </div>
        </div>
    </section>
</template>

<style scoped>
:deep(.legal-editor-content) {
    min-height: 48rem;
    outline: none;
    color: #111827;
    font-family: Arial, sans-serif;
    font-size: 12pt;
    line-height: 1.5;
}

:deep(.legal-editor-content p) {
    min-height: 1.5em;
    margin: 0 0 0.65rem;
    white-space: pre-wrap;
}

:deep(.legal-editor-content p:last-child) {
    margin-bottom: 0;
}

:deep(.legal-editor-content.ProseMirror-focused) {
    outline: none;
}
</style>
