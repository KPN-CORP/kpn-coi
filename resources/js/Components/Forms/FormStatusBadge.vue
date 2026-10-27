<script setup lang="ts">
import { computed } from 'vue'
import { useLocale } from '@/Composables/useLocale'
import type { FormStatus } from './types'

const props = defineProps<{
    status: FormStatus
}>()

const { t } = useLocale()

const badge = computed(() => {
    switch (props.status) {
        case 'signed':
            return { label: t.value.forms.statusSigned, classes: 'bg-green-50 text-green-700 border-green-200' }

        case 'draft':
            return { label: t.value.forms.statusDraft, classes: 'bg-amber-50 text-amber-700 border-amber-200' }

        case 'pending':
            return { label: t.value.forms.statusNotStarted, classes: 'bg-red-50 text-red-700 border-red-200' }

        default:
            return { label: t.value.forms.statusNoTemplate, classes: 'bg-slate-50 text-slate-500 border-slate-200' }
    }
})
</script>

<template>
    <span
        class="inline-flex whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-semibold"
        :class="badge.classes"
    >
        {{ badge.label }}
    </span>
</template>
