<script setup lang="ts">
import PageHeader from '@/Components/UI/PageHeader.vue'
import FlashMessage from '@/Components/UI/FlashMessage.vue'
import MultiSelect from '@/Components/MultiSelect.vue'
import Modal from '@/Components/UI/Modal.vue'
import DocumentEditor from '@/Components/Forms/DocumentEditor.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useLocale } from '@/Composables/useLocale'
import { usePermission } from '@/Composables/usePermission'
import FormDocument from '@/Components/Forms/FormDocument.vue'
import FormTypeSelect from '@/Components/Forms/FormTypeSelect.vue'
import type { FontOptions, FormSample, FormTypeOption, FormVariable, PageLayout } from '@/Components/Forms/types'
import SearchableSelect from '@/Components/UI/SearchableSelect.vue'
import type { Flash } from '@/Config/inertia'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Swal from 'sweetalert2'

interface TemplateData {
    id: number
    form_type_id: number
    period: number
    title_en: string
    title_id: string
    content_en: string
    content_id: string
    header_en: string
    header_id: string
    footer_en: string
    footer_id: string
    paper_size: string
    orientation: 'portrait' | 'landscape'
    margin_top: number
    margin_right: number
    margin_bottom: number
    margin_left: number
    font_family: string
    font_size: number
    line_height: number
    business_units: string[]
    publish_at: string | null
    is_active: boolean
    signed_count: number
    locked: boolean
    can_manage: boolean
}

const props = defineProps<{
    template: TemplateData | null
    businessUnitOptions: string[]
    unitRestricted: boolean
    variables: FormVariable[]
    // Each variable as display HTML per language, formatted by the server
    // (FormVariableService::displayValues), signature included.
    previewValues: Record<Language, Record<string, string>>
    samples: FormSample[]
    formTypes: FormTypeOption[]
    defaultFormType: number | null
    paperSizes: { key: string, width: number, height: number }[]
    fontOptions: FontOptions
}>()

const { t, locale } = useLocale()
const { can } = usePermission()
const page = usePage<{ flash: Flash }>()

type Language = 'en' | 'id'

const language = ref<Language>(locale.value)
const showPreview = ref(false)
const previewLanguage = ref<Language>(locale.value)

function openPreview() {
    previewLanguage.value = language.value
    showPreview.value = true
}

function defaultPublishAt(): string {
    const now = new Date()
    const pad = (value: number) => String(value).padStart(2, '0')

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`
}

const form = useForm({
    form_type_id: props.template?.form_type_id ?? props.defaultFormType ?? props.formTypes[0]?.id ?? null,
    period: props.template?.period ?? new Date().getFullYear(),
    title_en: props.template?.title_en ?? '',
    title_id: props.template?.title_id ?? '',
    content_en: props.template?.content_en ?? '',
    content_id: props.template?.content_id ?? '',
    header_en: props.template?.header_en ?? '',
    header_id: props.template?.header_id ?? '',
    footer_en: props.template?.footer_en ?? '',
    footer_id: props.template?.footer_id ?? '',
    paper_size: props.template?.paper_size ?? 'a4',
    orientation: props.template?.orientation ?? 'portrait',
    margin_top: props.template?.margin_top ?? 30,
    margin_right: props.template?.margin_right ?? 12.7,
    margin_bottom: props.template?.margin_bottom ?? 20,
    margin_left: props.template?.margin_left ?? 12.7,
    font_family: props.template?.font_family ?? props.fontOptions.fonts[0]?.key ?? '',
    font_size: props.template?.font_size ?? 10,
    line_height: props.template?.line_height ?? 1.5,
    business_units: props.template?.business_units ?? [] as string[],
    publish_at: props.template?.publish_at ?? defaultPublishAt(),
    is_active: props.template?.is_active ?? true,
})

const isEdit = computed(() => props.template !== null)

// Signed templates are evidence of what was agreed to; templates reaching
// units outside the admin's role are not theirs to change.
const readOnly = computed(() => {
    if (!props.template) {
        return !can('form_master.create')
    }

    return props.template.locked
        || !props.template.can_manage
        || !can('form_master.update')
})

const businessUnitSelect = computed(() =>
    props.businessUnitOptions.map(unit => ({ code: unit, name: unit })),
)

const languageHasError = (lang: Language) =>
    ['header', 'content', 'footer'].some(part => Boolean(form.errors[`${part}_${lang}`]))

// --- Header/footer: usually identical (a logo), so offer a one-click copy ---

const otherLanguage = (lang: Language): Language => (lang === 'en' ? 'id' : 'en')

const otherLabel = (lang: Language) =>
    otherLanguage(lang) === 'en' ? t.value.forms.english : t.value.forms.indonesian

const blank = (html: string) => !html || (html.replace(/<[^>]*>/g, '').trim() === '' && !html.includes('<img'))

function hasOther(part: 'header' | 'footer', lang: Language) {
    return !blank(form[`${part}_${otherLanguage(lang)}`])
}

async function copyFromOther(part: 'header' | 'footer', lang: Language, close?: () => void) {
    close?.()

    if (!blank(form[`${part}_${lang}`])) {
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            title: t.value.forms.copyReplaceTitle,
            showCancelButton: true,
            confirmButtonText: t.value.forms.copyFrom,
            cancelButtonText: t.value.common.cancel,
            confirmButtonColor: '#ab2f2b',
            reverseButtons: true,
        })

        if (!isConfirmed) {
            return
        }
    }

    form[`${part}_${lang}`] = form[`${part}_${otherLanguage(lang)}`]
}

const tabs = computed(() => [
    { key: 'en' as Language, label: t.value.forms.english, flag: 'EN' },
    { key: 'id' as Language, label: t.value.forms.indonesian, flag: 'ID' },
])

// --- Preview: the admin's own data in place of each {{variable}} ---------

const escapeHtml = (value: string) => value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')

// Mirrors FormVariableService::render() so the preview matches what
// signers get. Only the admin's own input is shown here; what employees see is
// sanitised and rendered on the server.
function renderPreview(html: string, lang: Language): string {
    const values: Record<string, string> = { ...props.previewValues[lang], period: escapeHtml(String(form.period)) }

    // The chip styling is editor-only; the server strips it on save.
    return html.replaceAll(' class="form-variable"', '').replace(/\{\{\s*([a-z_]+)\s*\}\}/g, (match, key: string) => values[key] ?? match)
}

const preview = computed(() => {
    const lang = previewLanguage.value

    return {
        content: renderPreview(form[`content_${lang}`], lang),
        header: renderPreview(form[`header_${lang}`], lang),
        footer: renderPreview(form[`footer_${lang}`], lang),
    }
})

// Computed once per error change -- a fresh object per render would push new
// props into both languages' editors on every keystroke.
const editorErrors = computed(() => Object.fromEntries(
    (['en', 'id'] as Language[]).map(lang => [lang, {
        header: form.errors[`header_${lang}`],
        content: form.errors[`content_${lang}`],
        footer: form.errors[`footer_${lang}`],
    }]),
) as Record<Language, { header?: string, content?: string, footer?: string }>)

// --- Page setup -----------------------------------------------------------

const paperLabels: Record<string, string> = {
    a4: 'A4',
    f4: 'F4 / Folio',
    letter: 'Letter',
    legal: 'Legal',
}

const marginFields = computed(() => [
    { key: 'margin_top' as const, label: t.value.forms.marginTopShort, icon: 'fa-solid fa-arrow-up' },
    { key: 'margin_bottom' as const, label: t.value.forms.marginBottomShort, icon: 'fa-solid fa-arrow-down' },
    { key: 'margin_left' as const, label: t.value.forms.marginLeftShort, icon: 'fa-solid fa-arrow-left' },
    { key: 'margin_right' as const, label: t.value.forms.marginRightShort, icon: 'fa-solid fa-arrow-right' },
])

// The template's default font, shown in every editor and the preview.
const baseFont = computed(() => ({
    css: props.fontOptions.fonts.find(font => font.key === form.font_family)?.css ?? props.fontOptions.fonts[0]?.css ?? 'sans-serif',
    size: Number(form.font_size) || 10,
    lineHeight: Number(form.line_height) || 1.5,
}))

// Mirrors FormTemplate::pageLayout() so the preview is to scale.
const pageLayout = computed<PageLayout>(() => {
    const size = props.paperSizes.find(item => item.key === form.paper_size) ?? props.paperSizes[0]
    const landscape = form.orientation === 'landscape'

    return {
        paper_size: form.paper_size,
        orientation: landscape ? 'landscape' : 'portrait',
        width: landscape ? size.height : size.width,
        height: landscape ? size.width : size.height,
        margin_top: Number(form.margin_top) || 0,
        margin_right: Number(form.margin_right) || 0,
        margin_bottom: Number(form.margin_bottom) || 0,
        margin_left: Number(form.margin_left) || 0,
        font_family: form.font_family,
        font_css: baseFont.value.css,
        font_size: baseFont.value.size,
        line_height: baseFont.value.lineHeight,
    }
})

// --- Samples: replace both languages' title and body in one go ----------

const selectedSample = ref('')

const isBlank = (html: string) => html.replace(/<[^>]*>/g, '').trim() === ''

// Only the selected form type's samples (plus untyped ones).
const availableSamples = computed(() => {
    const code = props.formTypes.find(type => type.id === Number(form.form_type_id))?.code

    return props.samples.filter(sample => !sample.form_type || sample.form_type === code)
})

const paperSizeOptions = computed(() => props.paperSizes.map(size => ({ value: size.key, label: paperLabels[size.key] ?? size.key })))

const orientationOptions = computed(() => [
    { value: 'portrait', label: t.value.forms.portrait },
    { value: 'landscape', label: t.value.forms.landscape },
])

const fontFamilyOptions = computed(() => props.fontOptions.fonts.map(font => ({ value: font.key, label: font.label })))

const fontSizeOptions = computed(() => props.fontOptions.sizes.map(size => ({ value: size, label: `${size} pt` })))

const lineHeightOptions = computed(() => props.fontOptions.lineHeights.map(height => ({ value: height, label: String(height) })))

const sampleOptions = computed(() => [
    { value: '', label: t.value.forms.chooseSample },
    ...availableSamples.value.map(sample => ({ value: sample.key, label: sample.name[locale.value] })),
])

async function applySample() {
    const sample = availableSamples.value.find(item => item.key === selectedSample.value)

    if (!sample) {
        return
    }

    const hasContent = [form.title_en, form.title_id].some(value => value.trim() !== '')
        || !isBlank(form.content_en)
        || !isBlank(form.content_id)

    if (hasContent) {
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            title: t.value.forms.sampleReplaceTitle,
            text: t.value.forms.sampleReplaceText,
            showCancelButton: true,
            confirmButtonText: t.value.forms.useSample,
            cancelButtonText: t.value.common.cancel,
            confirmButtonColor: '#ab2f2b',
            reverseButtons: true,
        })

        if (!isConfirmed) {
            selectedSample.value = ''
            return
        }
    }

    form.title_en = sample.title_en
    form.title_id = sample.title_id
    form.content_en = sample.content_en
    form.content_id = sample.content_id

    // A sample with its own letterhead brings its page setup along.
    if (sample.header_en !== undefined) form.header_en = sample.header_en
    if (sample.header_id !== undefined) form.header_id = sample.header_id
    if (sample.footer_en !== undefined) form.footer_en = sample.footer_en
    if (sample.footer_id !== undefined) form.footer_id = sample.footer_id
    if (sample.paper_size) form.paper_size = sample.paper_size
    if (sample.orientation) form.orientation = sample.orientation
    if (sample.margin_top !== undefined) form.margin_top = sample.margin_top
    if (sample.margin_right !== undefined) form.margin_right = sample.margin_right
    if (sample.margin_bottom !== undefined) form.margin_bottom = sample.margin_bottom
    if (sample.margin_left !== undefined) form.margin_left = sample.margin_left
    if (sample.font_family) form.font_family = sample.font_family
    if (sample.font_size !== undefined) form.font_size = sample.font_size
    if (sample.line_height !== undefined) form.line_height = sample.line_height
    selectedSample.value = ''
}

function submit() {
    const options = {
        preserveScroll: true,
        onError: (errors: Record<string, string>) => {
            // Jump to the language that needs attention.
            if (!languageHasError(language.value)) {
                const other: Language = language.value === 'en' ? 'id' : 'en'

                if (errors[`title_${other}`] || errors[`content_${other}`]) {
                    language.value = other
                }
            }
        },
    }

    if (props.template) {
        form.put(route('admin.master.templates.update', props.template.id), options)
    } else {
        form.post(route('admin.master.templates.store'), options)
    }
}

function duplicate() {
    if (props.template) {
        router.post(route('admin.master.templates.duplicate', props.template.id))
    }
}
</script>

<template>
    <Head :title="isEdit ? t.forms.editTemplate : t.forms.createTemplate" />

    <AdminLayout>
        <PageHeader
            :title="isEdit ? (readOnly ? t.forms.viewTemplate : t.forms.editTemplate) : t.forms.createTemplate"
            :description="t.forms.templatesDescription"
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('admin.master.templates.index')"
                        class="btn btn-outline-secondary"
                    >
                        <i class="fa-solid fa-arrow-left" />
                        {{ t.forms.back }}
                    </Link>

                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        @click="openPreview"
                    >
                        <i class="fa-solid fa-eye" />
                        {{ t.forms.preview }}
                    </button>

                    <button
                        v-if="!readOnly"
                        type="button"
                        class="btn-primary-custom"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        <i
                            class="fa-solid"
                            :class="form.processing ? 'fa-circle-notch fa-spin' : 'fa-floppy-disk'"
                        />
                        {{ form.processing ? t.common.saving : t.forms.save }}
                    </button>
                </div>
            </template>
        </PageHeader>

        <FlashMessage
            type="success"
            :message="page.props.flash?.success"
        />

        <FlashMessage
            type="error"
            :message="page.props.flash?.error"
        />

        <div
            v-if="template?.locked"
            class="mb-6 flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 sm:flex-row sm:items-center sm:justify-between"
        >
            <span class="flex items-start gap-2">
                <i class="fa-solid fa-lock mt-0.5" />
                {{ t.forms.lockedNotice }}
            </span>

            <button
                v-if="template.can_manage && can('form_master.create')"
                type="button"
                class="btn btn-outline-secondary btn-sm shrink-0 bg-white"
                @click="duplicate"
            >
                <i class="fa-solid fa-copy" />
                {{ t.forms.duplicate }}
            </button>
        </div>

        <div
            v-else-if="template && !template.can_manage"
            class="mb-6 flex items-start gap-2 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700"
        >
            <i class="fa-solid fa-circle-info mt-0.5" />
            {{ t.forms.readOnlyNotice }}
        </div>

        <form
            class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_20rem]"
            @submit.prevent="submit"
        >
            <!-- Settings: a sidebar on wide screens, on top otherwise -->
            <aside class="grid content-start gap-4 md:grid-cols-2 2xl:order-2 2xl:sticky 2xl:top-[calc(var(--topbar-height)+1rem)] 2xl:grid-cols-1 2xl:self-start">
                <section class="form-side-card">
                    <h2 class="form-side-title">
                        <i class="fa-solid fa-sliders" />
                        {{ t.forms.settings }}
                    </h2>

                    <div class="space-y-3">
                        <div
                            v-for="tab in tabs"
                            :key="tab.key"
                        >
                            <label class="form-side-label">
                                {{ t.forms.templateName }}
                                <span class="ml-1 rounded bg-slate-100 px-1 text-[10px] font-bold text-slate-500">{{ tab.flag }}</span>
                            </label>
                            <input
                                v-model="form[`title_${tab.key}`]"
                                type="text"
                                maxlength="255"
                                class="form-side-input"
                                :disabled="readOnly"
                            >
                            <p
                                v-if="form.errors[`title_${tab.key}`]"
                                class="form-side-error"
                            >
                                {{ form.errors[`title_${tab.key}`] }}
                            </p>
                        </div>
                        <p class="form-side-hint -mt-1">{{ t.forms.templateNameHint }}</p>

                        <div>
                            <label class="form-side-label">{{ t.forms.formType }}</label>
                            <FormTypeSelect
                                v-model="form.form_type_id"
                                :options="formTypes"
                                :disabled="readOnly"
                            />
                            <p
                                v-if="form.errors.form_type_id"
                                class="form-side-error"
                            >
                                {{ form.errors.form_type_id }}
                            </p>
                        </div>

                        <div class="grid grid-cols-[6rem_minmax(0,1fr)] gap-3">
                            <div>
                                <label class="form-side-label">{{ t.forms.formPeriod }}</label>
                                <input
                                    v-model.number="form.period"
                                    type="number"
                                    min="2000"
                                    max="2100"
                                    class="form-side-input"
                                    :disabled="readOnly"
                                >
                            </div>
                            <div>
                                <label class="form-side-label">{{ t.forms.formPublishAt }}</label>
                                <input
                                    v-model="form.publish_at"
                                    type="datetime-local"
                                    class="form-side-input"
                                    :disabled="readOnly"
                                >
                            </div>
                        </div>
                        <p
                            v-if="form.errors.period || form.errors.publish_at"
                            class="form-side-error"
                        >
                            {{ form.errors.period ?? form.errors.publish_at }}
                        </p>

                        <div>
                            <label class="form-side-label">{{ t.forms.formBusinessUnits }}</label>
                            <div :class="{ 'pointer-events-none opacity-70': readOnly }">
                                <MultiSelect
                                    v-model="form.business_units"
                                    :options="businessUnitSelect"
                                    :placeholder="t.forms.selectBusinessUnits"
                                />
                            </div>
                            <p class="form-side-hint">
                                {{ unitRestricted ? t.forms.restrictedHint : t.forms.formBusinessUnitsHint }}
                            </p>
                            <p
                                v-if="form.errors.business_units"
                                class="form-side-error"
                            >
                                {{ form.errors.business_units }}
                            </p>
                        </div>

                        <label class="flex items-center justify-between gap-3 rounded-md bg-slate-50 px-3 py-2">
                            <span>
                                <span class="text-sm font-medium text-slate-700">{{ t.forms.formActive }}</span>
                                <span class="block text-xs text-slate-500">{{ t.forms.formActiveHint }}</span>
                            </span>
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="h-4 w-4 shrink-0"
                                :disabled="readOnly"
                            >
                        </label>
                    </div>
                </section>

                <section class="form-side-card">
                    <h2 class="form-side-title">
                        <i class="fa-solid fa-file" />
                        {{ t.forms.pageSetup }}
                    </h2>

                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-side-label">{{ t.forms.paperSize }}</label>
                                <SearchableSelect
                                    v-model="form.paper_size"
                                    :options="paperSizeOptions"
                                    class="w-full"
                                    :disabled="readOnly"
                                />
                            </div>
                            <div>
                                <label class="form-side-label">{{ t.forms.orientation }}</label>
                                <SearchableSelect
                                    v-model="form.orientation"
                                    :options="orientationOptions"
                                    class="w-full"
                                    :disabled="readOnly"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="form-side-label">{{ t.forms.margins }} (mm)</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label
                                    v-for="field in marginFields"
                                    :key="field.key"
                                    class="flex items-center gap-2 rounded-md border border-border px-2"
                                    :title="field.label"
                                >
                                    <i :class="[field.icon, 'text-xs text-slate-400']" />
                                    <span class="sr-only">{{ field.label }}</span>
                                    <input
                                        v-model.number="form[field.key]"
                                        type="number"
                                        step="0.1"
                                        min="5"
                                        max="80"
                                        class="w-full border-0 bg-transparent px-0 py-1.5 text-sm focus:ring-0"
                                        :disabled="readOnly"
                                    >
                                </label>
                            </div>
                            <p
                                v-for="field in marginFields.filter(item => form.errors[item.key])"
                                :key="field.key"
                                class="form-side-error"
                            >
                                {{ form.errors[field.key] }}
                            </p>
                        </div>

                        <div>
                            <label class="form-side-label">{{ t.forms.documentFont }}</label>
                            <SearchableSelect
                                v-model="form.font_family"
                                :options="fontFamilyOptions"
                                class="w-full"
                                :disabled="readOnly"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-side-label">{{ t.forms.fontSize }}</label>
                                <SearchableSelect
                                    v-model="form.font_size"
                                    :options="fontSizeOptions"
                                    class="w-full"
                                    :disabled="readOnly"
                                />
                            </div>
                            <div>
                                <label class="form-side-label">{{ t.forms.lineSpacing }}</label>
                                <SearchableSelect
                                    v-model="form.line_height"
                                    :options="lineHeightOptions"
                                    class="w-full"
                                    :disabled="readOnly"
                                />
                            </div>
                        </div>
                        <p class="form-side-hint">{{ t.forms.documentFontHint }}</p>
                    </div>
                </section>

                <section
                    v-if="!readOnly && availableSamples.length"
                    class="form-side-card md:col-span-2 2xl:col-span-1"
                >
                    <h2 class="form-side-title">
                        <i class="fa-solid fa-wand-magic-sparkles" />
                        {{ t.forms.useSample }}
                    </h2>
                    <SearchableSelect
                        v-model="selectedSample"
                        :options="sampleOptions"
                        class="w-full"
                        :aria-label="t.forms.useSample"
                        @change="applySample"
                    />
                </section>
            </aside>

            <!-- The document, one language at a time -->
            <div class="min-w-0 2xl:order-1">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div
                        class="inline-flex rounded-md border border-border bg-white p-0.5 shadow-sm"
                        role="tablist"
                    >
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            role="tab"
                            :aria-selected="language === tab.key"
                            class="flex items-center gap-2 rounded px-3 py-1.5 text-sm font-medium"
                            :class="language === tab.key
                                ? 'bg-primary text-white'
                                : 'text-slate-600 hover:text-slate-800'"
                            @click="language = tab.key"
                        >
                            <span
                                class="rounded px-1 text-[10px] font-bold"
                                :class="language === tab.key ? 'bg-white/20' : 'bg-slate-100'"
                            >{{ tab.flag }}</span>
                            {{ tab.label }}
                            <i
                                v-if="languageHasError(tab.key)"
                                class="fa-solid fa-circle-exclamation"
                                :class="language === tab.key ? 'text-white' : 'text-red-500'"
                            />
                        </button>
                    </div>

                    <div
                        v-if="!readOnly"
                        class="flex flex-wrap gap-2 text-xs"
                    >
                        <button
                            v-for="part in (['header', 'footer'] as const)"
                            v-show="hasOther(part, language)"
                            :key="part"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-md border border-border bg-white px-2.5 py-1.5 font-medium text-slate-600 hover:text-primary"
                            @click="copyFromOther(part, language)"
                        >
                            <i class="fa-solid fa-copy" />
                            {{ part === 'header' ? t.forms.copyHeaderFrom : t.forms.copyFooterFrom }} {{ otherLabel(language) }}
                        </button>
                    </div>
                </div>

                <p
                    v-if="!readOnly"
                    class="mb-2 text-xs text-slate-500"
                >
                    {{ t.forms.editorHint }}
                </p>

                <!-- Both languages stay mounted so neither loses its undo history. -->
                <DocumentEditor
                    v-for="tab in tabs"
                    v-show="language === tab.key"
                    :key="tab.key"
                    v-model:header="form[`header_${tab.key}`]"
                    v-model:content="form[`content_${tab.key}`]"
                    v-model:footer="form[`footer_${tab.key}`]"
                    :page="pageLayout"
                    :variables="variables"
                    :font-options="fontOptions"
                    :disabled="readOnly"
                    :errors="editorErrors[tab.key]"
                />
            </div>
        </form>

        <Modal
            :show="showPreview"
            max-width="max-w-5xl"
            @close="showPreview = false"
        >
            <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-3">
                <div>
                    <h3 class="font-bold">{{ t.forms.preview }}</h3>
                    <p class="text-xs text-slate-500">{{ t.forms.previewHint }}</p>
                </div>

                <div class="flex items-center gap-3">
                    <div class="inline-flex rounded-md border border-border p-0.5">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            class="rounded px-2.5 py-1 text-xs font-semibold"
                            :class="previewLanguage === tab.key ? 'bg-primary text-white' : 'text-slate-600'"
                            @click="previewLanguage = tab.key"
                        >
                            {{ tab.flag }}
                        </button>
                    </div>
                    <button
                        type="button"
                        class="text-slate-400 hover:text-primary"
                        :aria-label="t.common.close"
                        @click="showPreview = false"
                    >
                        <i class="fa-solid fa-xmark text-lg" />
                    </button>
                </div>
            </div>

            <div class="overflow-y-auto">
                <FormDocument
                    :page="pageLayout"
                    :content="preview.content"
                    :header="preview.header"
                    :footer="preview.footer"
                />
            </div>
        </Modal>
    </AdminLayout>
</template>
