<script setup lang="ts">
import { computed } from 'vue'
import Pill, { type PillTone } from '@/Components/UI/Pill.vue'
import { useLocale } from '@/Composables/useLocale'
import type { FormStatus } from './types'

const props = defineProps<{
    status: FormStatus
}>()

const { t } = useLocale()

const badge = computed((): { label: string, tone: PillTone } => {
    switch (props.status) {
        case 'signed':
            return { label: t.value.forms.statusSigned, tone: 'success' }

        case 'draft':
            return { label: t.value.forms.statusDraft, tone: 'warning' }

        case 'pending':
            return { label: t.value.forms.statusNotStarted, tone: 'danger' }

        default:
            return { label: t.value.forms.statusNoTemplate, tone: 'neutral' }
    }
})
</script>

<template>
    <Pill :tone="badge.tone">
        {{ badge.label }}
    </Pill>
</template>
