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
}>(), {
    pdf: true,
})

const emit = defineEmits<{
    view: []
    download: [language: 'id' | 'en']
}>()

const { t } = useLocale()
</script>

<template>
    <button
        type="button"
        class="btn btn-outline-secondary btn-sm"
        @click="emit('view')"
    >
        <i class="fa-solid fa-eye" />
        {{ t.common.view }}
    </button>

    <template v-if="pdf">
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
