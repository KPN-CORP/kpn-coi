<script setup>
import { computed } from 'vue'
import { useLocale } from '@/Composables/useLocale'

const { t } = useLocale()

const props = defineProps({
    status: {
        type: String,
        required: true,
    },

    // Optional display text override; the colour still follows `status`.
    label: {
        type: String,
        default: null,
    },
})

const badgeClass = computed(() => {
    switch (props.status) {
        case 'submitted':
        case 'approved':
            return 'bg-green-50 text-green-700 border-green-200'

        case 'draft':
            return 'bg-slate-50 text-slate-600 border-slate-200'

        case 'revision_required':
            return 'bg-amber-50 text-amber-700 border-amber-200'

        case 'rejected':
            return 'bg-red-50 text-red-700 border-red-200'

        default:
            return 'bg-slate-50 text-slate-600 border-slate-200'
    }
})

const displayLabel = computed(() => {
    if (props.label) {
        return props.label
    }

    return t.value.status[props.status]
        ?? props.status.replaceAll('_', ' ')
})
</script>

<template>
    <span
        class="inline-flex rounded-md border px-2 py-1 text-[11px] font-semibold uppercase"
        :class="badgeClass"
    >
        {{ displayLabel }}
    </span>
</template>
