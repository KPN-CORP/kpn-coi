<script setup lang="ts">
import { useLocale } from '@/Composables/useLocale'

/** Employee (blue) or Non-employee (amber), as the declaration's `type`. */
defineProps<{
    type: 'employee' | 'non_employee' | string
}>()

const { t } = useLocale()
</script>

<template>
    <span
        class="inline-block whitespace-nowrap rounded-md border px-2 py-1 text-xs font-medium"
        :class="type === 'employee'
            ? 'border-blue-200 bg-blue-50 text-blue-700'
            : 'border-amber-200 bg-amber-50 text-amber-700'"
    >
        {{ type === 'employee' ? t.common.employee : t.common.nonEmployee }}
    </span>
</template>
