<script setup lang="ts">
import type { Editor } from '@tiptap/vue-3'
import { getAttributes as attributesOf, isActive as activeIn } from '@tiptap/core'
import { computed, ref } from 'vue'
import Swal from 'sweetalert2'
import ToolbarMenu from './ToolbarMenu.vue'
import { useLocale } from '@/Composables/useLocale'
import type { FontOptions, FormVariable } from './types'

/**
 * One toolbar for the whole document: it acts on whichever part (header, body,
 * footer) was focused last, so the page carries a single set of controls
 * instead of one per editor. Table tools appear only inside a table.
 *
 * Every button runs from @mousedown.prevent on its element (see the template)
 * so clicking the toolbar never steals the selection from the editor.
 */
const props = defineProps<{
    editor: Editor | null | undefined
    variables: FormVariable[]
    fontOptions: FontOptions
    // Name of the part being edited ("Body", "Header" ...), for screen readers.
    target: string
    // Changes on every editor transaction; read below so the state helpers
    // re-evaluate immediately (see DocumentEditor).
    revision: number
}>()

const { t, locale } = useLocale()

const ed = computed(() => {
    // Dependency only: re-run every state read after each transaction.
    void props.revision

    return props.editor ?? null
})

const run = (fn: (chain: ReturnType<Editor['chain']>) => ReturnType<Editor['chain']>) => {
    if (ed.value) {
        fn(ed.value.chain().focus()).run()
    }
}

// Read the view's live state, not editor.state: the Vue binding's copy is only
// refreshed on the next animation frame.
const isActive = (name: string | Record<string, unknown>, attributes?: Record<string, unknown>) => {
    if (!ed.value) {
        return false
    }

    const state = ed.value.view.state

    return typeof name === 'string'
        ? activeIn(state, name, attributes ?? {})
        : activeIn(state, null, name)
}

const attrs = (name: string): Record<string, any> =>
    ed.value ? attributesOf(ed.value.view.state, name) : {}

// --- Paragraph style ---------------------------------------------------------

const styles = computed(() => [
    { key: 'p', label: t.value.forms.styleNormal, apply: () => run(c => c.setParagraph()), active: () => isActive('paragraph') },
    { key: 'h1', label: t.value.forms.styleHeading1, apply: () => run(c => c.toggleHeading({ level: 1 })), active: () => isActive('heading', { level: 1 }) },
    { key: 'h2', label: t.value.forms.styleHeading2, apply: () => run(c => c.toggleHeading({ level: 2 })), active: () => isActive('heading', { level: 2 }) },
    { key: 'h3', label: t.value.forms.styleHeading3, apply: () => run(c => c.toggleHeading({ level: 3 })), active: () => isActive('heading', { level: 3 }) },
])

const currentStyle = () => styles.value.find(style => style.active())?.label ?? t.value.forms.styleNormal

// --- Font / size / colour ---------------------------------------------------

// Browsers re-quote font names when the editor reads them back.
const normalizeFont = (value: string) => value.replace(/[\s'"]+/g, '').toLowerCase()

function currentFont() {
    const family = attrs('textStyle').fontFamily

    return family
        ? props.fontOptions.fonts.find(font => normalizeFont(font.css) === normalizeFont(family))
        : undefined
}

const currentFontLabel = () => currentFont()?.label ?? t.value.forms.defaultFont

function currentSize() {
    const size = attrs('textStyle').fontSize

    return size ? String(parseFloat(size)) : ''
}

function currentColour(kind: 'text' | 'highlight'): string | null {
    const attributes = attrs('textStyle')

    return (kind === 'text' ? attributes.color : attributes.backgroundColor) ?? null
}

function setColour(kind: 'text' | 'highlight', colour: string | null) {
    run((chain) => {
        if (kind === 'text') {
            return colour ? chain.setColor(colour) : chain.unsetColor()
        }

        return colour ? chain.setBackgroundColor(colour) : chain.unsetBackgroundColor()
    })
}

// --- Alignment ---------------------------------------------------------------

const alignments = computed(() => [
    { key: 'left', icon: 'fa-solid fa-align-left', label: t.value.forms.alignLeft },
    { key: 'center', icon: 'fa-solid fa-align-center', label: t.value.forms.alignCenter },
    { key: 'right', icon: 'fa-solid fa-align-right', label: t.value.forms.alignRight },
    { key: 'justify', icon: 'fa-solid fa-align-justify', label: t.value.forms.alignJustify },
])

const currentAlignIcon = () =>
    alignments.value.find(align => isActive({ textAlign: align.key }))?.icon ?? 'fa-solid fa-align-left'

// --- Insert: variable, table, image, line, tab -------------------------------

const token = (key: string) => `{{${key}}}`

const fileInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

async function onImageChosen(event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ''

    if (!file || !ed.value) {
        return
    }

    const target = ed.value
    const body = new FormData()
    body.append('image', file)

    uploading.value = true

    try {
        const response = await fetch(route('admin.master.images.store'), {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        })

        const data = await response.json().catch(() => ({}))

        if (!response.ok || !data.url) {
            throw new Error(data?.errors?.image?.[0] ?? data?.message ?? '')
        }

        target.chain().focus().setImage({ src: data.url, alt: file.name }).run()
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: t.value.forms.imageUploadFailed,
            text: (error as Error).message || t.value.forms.imageRules,
            confirmButtonColor: '#ab2f2b',
        })
    } finally {
        uploading.value = false
    }
}

// --- Table ---------------------------------------------------------------------

const inTable = () => isActive('table')

function toggleBorders() {
    const borderless = Boolean(attrs('table').borderless)

    run(c => c.updateAttributes('table', { borderless: !borderless }))
}

async function setRowHeight() {
    if (!ed.value) {
        return
    }

    const target = ed.value
    const current = attributesOf(target.view.state, 'tableRow').height ?? ''

    const { isConfirmed, value } = await Swal.fire({
        title: t.value.forms.rowHeight,
        text: t.value.forms.rowHeightHint,
        input: 'number',
        inputValue: current,
        inputAttributes: { min: '0', max: '500', step: '1' },
        showCancelButton: true,
        confirmButtonText: t.value.common.save,
        cancelButtonText: t.value.common.cancel,
        confirmButtonColor: '#ab2f2b',
        reverseButtons: true,
    })

    if (!isConfirmed) {
        return
    }

    const height = Math.min(500, Math.max(0, parseInt(String(value), 10) || 0))

    target.chain().focus().updateAttributes('tableRow', { height: height || null }).run()
}
</script>

<template>
    <div
        class="form-toolbar rounded-t-md border border-border bg-white shadow-sm"
        :class="{ 'pointer-events-none opacity-60': !ed }"
    >
        <div
            class="flex flex-wrap items-center gap-0.5 px-2 py-1.5"
            role="toolbar"
            :aria-label="`${t.forms.editingPart}: ${target}`"
        >
            <button
                type="button"
                class="form-tb-btn"
                :title="t.forms.undo"
                :aria-label="t.forms.undo"
                @mousedown.prevent="run(c => c.undo())"
            >
                <i class="fa-solid fa-rotate-left" />
            </button>
            <button
                type="button"
                class="form-tb-btn"
                :title="t.forms.redo"
                :aria-label="t.forms.redo"
                @mousedown.prevent="run(c => c.redo())"
            >
                <i class="fa-solid fa-rotate-right" />
            </button>

            <span class="form-tb-sep" />

            <!-- Paragraph style -->
            <ToolbarMenu
                :title="t.forms.paragraphStyle"
                :label="currentStyle()"
                width="w-44"
            >
                <template #default="{ close }">
                    <button
                        v-for="style in styles"
                        :key="style.key"
                        type="button"
                        class="form-menu-item"
                        :class="{ 'is-active': style.active() }"
                        @mousedown.prevent="style.apply(); close()"
                    >
                        <span :class="{ 'text-base font-bold': style.key === 'h1', 'font-bold': style.key === 'h2', 'font-semibold': style.key === 'h3' }">
                            {{ style.label }}
                        </span>
                    </button>
                </template>
            </ToolbarMenu>

            <!-- Font family -->
            <ToolbarMenu
                :title="t.forms.fontFamily"
                :label="currentFontLabel()"
                width="w-52"
            >
                <template #default="{ close }">
                    <button
                        type="button"
                        class="form-menu-item"
                        :class="{ 'is-active': !currentFont() }"
                        @mousedown.prevent="run(c => c.unsetFontFamily()); close()"
                    >
                        {{ t.forms.defaultFont }}
                    </button>
                    <button
                        v-for="font in fontOptions.fonts"
                        :key="font.key"
                        type="button"
                        class="form-menu-item"
                        :class="{ 'is-active': currentFont()?.key === font.key }"
                        :style="{ fontFamily: font.css }"
                        @mousedown.prevent="run(c => c.setFontFamily(font.css)); close()"
                    >
                        {{ font.label }}
                    </button>
                </template>
            </ToolbarMenu>

            <!-- Font size -->
            <ToolbarMenu
                :title="t.forms.fontSize"
                :label="currentSize() || t.forms.defaultSize"
                width="w-28"
            >
                <template #default="{ close }">
                    <div class="max-h-64 overflow-y-auto">
                        <button
                            type="button"
                            class="form-menu-item"
                            :class="{ 'is-active': !currentSize() }"
                            @mousedown.prevent="run(c => c.unsetFontSize()); close()"
                        >
                            {{ t.forms.defaultSize }}
                        </button>
                        <button
                            v-for="size in fontOptions.sizes"
                            :key="size"
                            type="button"
                            class="form-menu-item"
                            :class="{ 'is-active': currentSize() === String(size) }"
                            @mousedown.prevent="run(c => c.setFontSize(`${size}pt`)); close()"
                        >
                            {{ size }} pt
                        </button>
                    </div>
                </template>
            </ToolbarMenu>

            <span class="form-tb-sep" />

            <button
                v-for="mark in [
                    { name: 'bold', icon: 'fa-bold', title: t.forms.bold, action: () => run(c => c.toggleBold()) },
                    { name: 'italic', icon: 'fa-italic', title: t.forms.italic, action: () => run(c => c.toggleItalic()) },
                    { name: 'underline', icon: 'fa-underline', title: t.forms.underline, action: () => run(c => c.toggleUnderline()) },
                    { name: 'strike', icon: 'fa-strikethrough', title: t.forms.strikethrough, action: () => run(c => c.toggleStrike()) },
                ]"
                :key="mark.name"
                type="button"
                class="form-tb-btn"
                :class="{ 'is-active': isActive(mark.name) }"
                :title="mark.title"
                :aria-label="mark.title"
                @mousedown.prevent="mark.action"
            >
                <i :class="['fa-solid', mark.icon]" />
            </button>

            <!-- Text colour / highlight -->
            <ToolbarMenu
                v-for="kind in (['text', 'highlight'] as const)"
                :key="kind"
                :title="kind === 'text' ? t.forms.textColor : t.forms.highlight"
                width="w-44"
            >
                <template #button>
                    <span class="flex flex-col items-center leading-none">
                        <i :class="kind === 'text' ? 'fa-solid fa-font' : 'fa-solid fa-highlighter'" />
                        <span
                            class="mt-0.5 h-1 w-4 rounded-sm border border-slate-200"
                            :style="{ background: currentColour(kind) ?? (kind === 'text' ? '#000000' : 'transparent') }"
                        />
                    </span>
                </template>
                <template #default="{ close }">
                    <div class="grid grid-cols-5 gap-1.5 p-1">
                        <button
                            v-for="colour in (kind === 'text' ? fontOptions.textColors : fontOptions.highlightColors)"
                            :key="colour"
                            type="button"
                            class="h-6 w-6 rounded border border-slate-200 hover:scale-110"
                            :style="{ background: colour }"
                            :aria-label="colour"
                            @mousedown.prevent="setColour(kind, colour); close()"
                        />
                    </div>
                    <button
                        type="button"
                        class="form-menu-item"
                        @mousedown.prevent="setColour(kind, null); close()"
                    >
                        <i class="fa-solid fa-droplet-slash w-4 text-slate-400" />
                        {{ t.forms.noColor }}
                    </button>
                </template>
            </ToolbarMenu>

            <span class="form-tb-sep" />

            <!-- Alignment -->
            <ToolbarMenu
                :title="t.forms.alignment"
                :icon="currentAlignIcon()"
                width="w-44"
            >
                <template #default="{ close }">
                    <button
                        v-for="align in alignments"
                        :key="align.key"
                        type="button"
                        class="form-menu-item"
                        :class="{ 'is-active': isActive({ textAlign: align.key }) }"
                        @mousedown.prevent="run(c => c.setTextAlign(align.key)); close()"
                    >
                        <i :class="[align.icon, 'w-4']" />
                        {{ align.label }}
                    </button>
                </template>
            </ToolbarMenu>

            <button
                type="button"
                class="form-tb-btn"
                :class="{ 'is-active': isActive('bulletList') }"
                :title="t.forms.bulletList"
                :aria-label="t.forms.bulletList"
                @mousedown.prevent="run(c => c.toggleBulletList())"
            >
                <i class="fa-solid fa-list-ul" />
            </button>
            <button
                type="button"
                class="form-tb-btn"
                :class="{ 'is-active': isActive('orderedList') }"
                :title="t.forms.numberedList"
                :aria-label="t.forms.numberedList"
                @mousedown.prevent="run(c => c.toggleOrderedList())"
            >
                <i class="fa-solid fa-list-ol" />
            </button>

            <span class="form-tb-sep" />

            <!-- Insert -->
            <ToolbarMenu
                :title="t.forms.insert"
                icon="fa-solid fa-plus"
                :label="t.forms.insert"
                width="w-52"
            >
                <template #default="{ close }">
                    <button
                        type="button"
                        class="form-menu-item"
                        @mousedown.prevent="run(c => c.insertTable({ rows: 3, cols: 2, withHeaderRow: false })); close()"
                    >
                        <i class="fa-solid fa-table w-4 text-slate-500" />
                        {{ t.forms.insertTable }}
                    </button>
                    <button
                        type="button"
                        class="form-menu-item"
                        :disabled="uploading"
                        @mousedown.prevent="fileInput?.click(); close()"
                    >
                        <i
                            class="w-4 text-slate-500"
                            :class="uploading ? 'fa-solid fa-circle-notch fa-spin' : 'fa-solid fa-image'"
                        />
                        {{ t.forms.insertImage }}
                    </button>
                    <button
                        type="button"
                        class="form-menu-item"
                        @mousedown.prevent="run(c => c.setHorizontalRule()); close()"
                    >
                        <i class="fa-solid fa-minus w-4 text-slate-500" />
                        {{ t.forms.insertLine }}
                    </button>
                    <button
                        type="button"
                        class="form-menu-item"
                        @mousedown.prevent="run(c => c.insertFormTab()); close()"
                    >
                        <i class="fa-solid fa-right-long w-4 text-slate-500" />
                        {{ t.forms.insertTab }}
                    </button>
                    <button
                        type="button"
                        class="form-menu-item"
                        :class="{ 'is-active': isActive('blockquote') }"
                        @mousedown.prevent="run(c => c.toggleBlockquote()); close()"
                    >
                        <i class="fa-solid fa-quote-left w-4 text-slate-500" />
                        {{ t.forms.quote }}
                    </button>
                </template>
            </ToolbarMenu>

            <!-- More text options -->
            <ToolbarMenu
                :title="t.forms.more"
                icon="fa-solid fa-ellipsis"
                width="w-52"
            >
                <template #default="{ close }">
                    <button
                        type="button"
                        class="form-menu-item"
                        :class="{ 'is-active': isActive('superscript') }"
                        @mousedown.prevent="run(c => c.toggleSuperscript()); close()"
                    >
                        <i class="fa-solid fa-superscript w-4 text-slate-500" />
                        {{ t.forms.superscript }}
                    </button>
                    <button
                        type="button"
                        class="form-menu-item"
                        :class="{ 'is-active': isActive('subscript') }"
                        @mousedown.prevent="run(c => c.toggleSubscript()); close()"
                    >
                        <i class="fa-solid fa-subscript w-4 text-slate-500" />
                        {{ t.forms.subscript }}
                    </button>
                    <button
                        type="button"
                        class="form-menu-item"
                        @mousedown.prevent="run(c => c.unsetAllMarks()); close()"
                    >
                        <i class="fa-solid fa-text-slash w-4 text-slate-500" />
                        {{ t.forms.clearFormatting }}
                    </button>
                </template>
            </ToolbarMenu>

            <div class="ml-auto">
                <!-- Variables: the feature admins reach for most, kept prominent -->
                <ToolbarMenu
                    :title="t.forms.insertVariable"
                    align="right"
                    width="w-64"
                >
                    <template #button>
                        <span class="flex items-center gap-1.5 rounded bg-primary px-2 py-1 text-xs font-medium text-white">
                            <i class="fa-solid fa-code" />
                            {{ t.forms.variable }}
                        </span>
                    </template>
                    <template #default="{ close }">
                        <div class="max-h-72 overflow-y-auto">
                            <button
                                v-for="variable in variables"
                                :key="variable.key"
                                type="button"
                                class="form-menu-item flex-col !items-start !gap-0"
                                @mousedown.prevent="run(c => c.insertFormVariable(variable.key)); close()"
                            >
                                <span class="text-sm text-slate-700">{{ variable.label[locale] }}</span>
                                <span class="font-mono text-[11px] text-slate-400">{{ token(variable.key) }}</span>
                            </button>
                        </div>
                    </template>
                </ToolbarMenu>
            </div>
        </div>

        <!-- Table tools: only while the cursor is inside a table -->
        <div
            v-if="inTable()"
            class="flex flex-wrap items-center gap-0.5 border-t border-border bg-slate-50 px-2 py-1"
        >
            <span class="mr-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                <i class="fa-solid fa-table" /> {{ t.forms.table }}
            </span>

            <button
                v-for="tool in [
                    { icon: 'fa-solid fa-arrow-down-long', label: t.forms.addRow, action: () => run(c => c.addRowAfter()) },
                    { icon: 'fa-solid fa-arrow-right-long', label: t.forms.addColumn, action: () => run(c => c.addColumnAfter()) },
                    { icon: 'fa-solid fa-square-minus', label: t.forms.deleteRow, action: () => run(c => c.deleteRow()) },
                    { icon: 'fa-solid fa-table-columns', label: t.forms.deleteColumn, action: () => run(c => c.deleteColumn()) },
                    { icon: 'fa-solid fa-object-group', label: t.forms.mergeCells, action: () => run(c => c.mergeOrSplit()) },
                    { icon: 'fa-solid fa-arrows-up-down', label: t.forms.rowHeight, action: setRowHeight },
                ]"
                :key="tool.label"
                type="button"
                class="form-tb-btn gap-1.5 px-2 text-xs"
                :title="tool.label"
                @mousedown.prevent="tool.action"
            >
                <i :class="tool.icon" />
                <span class="hidden 2xl:inline">{{ tool.label }}</span>
            </button>

            <button
                type="button"
                class="form-tb-btn gap-1.5 px-2 text-xs"
                :class="{ 'is-active': isActive('table', { borderless: true }) }"
                :title="t.forms.borderlessTable"
                @mousedown.prevent="toggleBorders"
            >
                <i class="fa-solid fa-border-none" />
                <span class="hidden 2xl:inline">{{ t.forms.borderlessTable }}</span>
            </button>

            <button
                type="button"
                class="form-tb-btn ml-auto gap-1.5 px-2 text-xs text-red-600"
                :title="t.forms.deleteTable"
                @mousedown.prevent="run(c => c.deleteTable())"
            >
                <i class="fa-solid fa-trash-can" />
                <span class="hidden sm:inline">{{ t.forms.deleteTable }}</span>
            </button>

            <span class="w-full text-[11px] text-slate-400">{{ t.forms.columnResizeHint }}</span>
        </div>

        <input
            ref="fileInput"
            type="file"
            accept="image/png,image/jpeg"
            class="hidden"
            @change="onImageChosen"
        >
    </div>
</template>
