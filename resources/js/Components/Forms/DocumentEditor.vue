<script setup lang="ts">
import { EditorContent, type Editor } from '@tiptap/vue-3'
import { computed, ref, toRef } from 'vue'
import EditorToolbar from './EditorToolbar.vue'
import { useFormEditor } from './useFormEditor'
import { useLocale } from '@/Composables/useLocale'
import type { FontOptions, FormVariable, PageLayout } from './types'

/**
 * One language of a template, edited on its page: header, body and
 * footer laid out at the paper's real width, margins and default font, with a
 * single toolbar that follows whichever part was clicked. What the admin sees
 * here is what FormDocument and the PDF print (minus pagination).
 */
const props = withDefaults(
    defineProps<{
        page: PageLayout
        variables: FormVariable[]
        fontOptions: FontOptions
        disabled?: boolean
        errors?: { header?: string, content?: string, footer?: string }
    }>(),
    {
        disabled: false,
        errors: () => ({}),
    },
)

const header = defineModel<string>('header', { required: true })
const content = defineModel<string>('content', { required: true })
const footer = defineModel<string>('footer', { required: true })

const { t } = useLocale()

type Part = 'header' | 'content' | 'footer'

const active = ref<Part>('content')
const disabled = toRef(props, 'disabled')

// Bumped on every editor transaction so the toolbar re-reads bold/table/...
// state at once. Tiptap's own reactive state is flushed on the next animation
// frame, which leaves the toolbar a step behind (or frozen in a background tab).
const revision = ref(0)
const bump = () => revision.value++

const editors = {
    header: useFormEditor({ model: header, disabled, onFocus: () => (active.value = 'header'), onTransaction: bump }),
    content: useFormEditor({ model: content, disabled, onFocus: () => (active.value = 'content'), onTransaction: bump }),
    footer: useFormEditor({ model: footer, disabled, onFocus: () => (active.value = 'footer'), onTransaction: bump }),
}

const activeEditor = computed<Editor | undefined>(() => editors[active.value].value)

const partLabel = computed(() => ({
    header: t.value.forms.header,
    content: t.value.forms.body,
    footer: t.value.forms.footer,
}[active.value]))

function focusPart(part: Part) {
    if (props.disabled) {
        return
    }

    active.value = part
    // Clicking the empty space of a zone should land the cursor in it.
    editors[part].value?.commands.focus('end')
}

// Same distances from the paper edge as the PDF and FormDocument.
const headerGap = computed(() => Math.min(10, props.page.margin_top / 3))

const paperStyle = computed(() => ({
    width: `${props.page.width}mm`,
    minHeight: `${props.page.height}mm`,
    paddingLeft: `${props.page.margin_left}mm`,
    paddingRight: `${props.page.margin_right}mm`,
    fontFamily: props.page.font_css,
    fontSize: `${props.page.font_size}pt`,
    lineHeight: String(props.page.line_height),
}))
</script>

<template>
    <div>
        <div class="sticky top-[var(--topbar-height)] z-20">
            <EditorToolbar
                v-if="!disabled"
                :editor="activeEditor"
                :variables="variables"
                :font-options="fontOptions"
                :target="partLabel"
                :revision="revision"
            />
        </div>

        <div
            class="overflow-x-auto border border-border bg-slate-100 p-4 sm:p-8"
            :class="disabled ? 'rounded-md' : 'rounded-b-md border-t-0'"
        >
            <div
                class="form-paper form-font-inherit mx-auto flex flex-col bg-white text-black shadow-md"
                :style="paperStyle"
            >
                <!-- Header: inside the top margin -->
                <section
                    class="form-zone mb-2"
                    @focusin="active = 'header'"
                    :class="{ 'is-active': !disabled && active === 'header' }"
                    :style="{ minHeight: `${page.margin_top}mm`, paddingTop: `${headerGap}mm` }"
                    @click.self="focusPart('header')"
                >
                    <span class="form-zone-label">{{ t.forms.header }}</span>
                    <EditorContent :editor="editors.header.value" />
                </section>

                <!-- Body -->
                <section
                    class="form-zone flex-1"
                    @focusin="active = 'content'"
                    :class="{ 'is-active': !disabled && active === 'content' }"
                    @click.self="focusPart('content')"
                >
                    <span class="form-zone-label">{{ t.forms.body }}</span>
                    <EditorContent
                        class="form-zone-body"
                        :editor="editors.content.value"
                    />
                </section>

                <!-- Footer: inside the bottom margin -->
                <section
                    class="form-zone mt-4"
                    @focusin="active = 'footer'"
                    :class="{ 'is-active': !disabled && active === 'footer' }"
                    :style="{ minHeight: `${page.margin_bottom}mm`, fontSize: '0.8em' }"
                    @click.self="focusPart('footer')"
                >
                    <span class="form-zone-label">{{ t.forms.footer }}</span>
                    <EditorContent :editor="editors.footer.value" />
                </section>
            </div>
        </div>

        <ul
            v-if="errors.header || errors.content || errors.footer"
            class="mt-2 space-y-0.5 text-xs text-red-600"
        >
            <li v-if="errors.header">{{ errors.header }}</li>
            <li v-if="errors.content">{{ errors.content }}</li>
            <li v-if="errors.footer">{{ errors.footer }}</li>
        </ul>
    </div>
</template>
