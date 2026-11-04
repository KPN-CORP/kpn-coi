<script setup lang="ts">
import { useLocale } from '@/Composables/useLocale'

/**
 * The View / PDF ID / PDF EN buttons of a submitted declaration or signed
 * form. One component for every table (COI and form, employee and admin) so
 * the buttons look the same everywhere: View grey, PDFs in the primary outline.
 */
withDefaults(defineProps<{
    // No PDF for a replaced form version (FormController::pdf refuses it).
    pdf?: boolean
    // A past year's uploaded document: nothing to render, one button that
    // downloads the file as uploaded (emitted as a download).
    file?: boolean
}>(), {
    pdf: true,
    file: false,
})

const emit = defineEmits<{
    view: []
    download: [language: 'id' | 'en']
}>()

const { t } = useLocale()
</script>

<template>
    <button
        v-if="file"
        type="button"
        class="btn btn-outline-primary-custom btn-sm"
        :title="t.forms.uploadedDocument"
        @click="emit('download', 'en')"
    >
        <i class="fa-solid fa-paperclip" />
        {{ t.forms.document }}
    </button>

    <button
        v-if="!file"
        type="button"
        class="btn btn-outline-secondary btn-sm"
        @click="emit('view')"
    >
        <i class="fa-solid fa-eye" />
        {{ t.common.view }}
    </button>

    <template v-if="pdf && !file">
        <button
            type="button"
            class="btn btn-outline-primary-custom btn-sm"
            :title="`PDF ${t.forms.indonesian}`"
            @click="emit('download', 'id')"
        >
            <i class="fa-solid fa-file-pdf" />
            ID
        </button>

        <button
            type="button"
            class="btn btn-outline-primary-custom btn-sm"
            :title="`PDF ${t.forms.english}`"
            @click="emit('download', 'en')"
        >
            <i class="fa-solid fa-file-pdf" />
            EN
        </button>
    </template>
</template>
