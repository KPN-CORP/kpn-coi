<script setup lang="ts">
import { useLocale } from '@/Composables/useLocale'
import type { FormTypeOption } from './types'

/**
 * Form type dropdown for the template list, report, dashboard and team view.
 * `allowAll` adds an "All Form Types" choice (template list only -- reports
 * and dashboards always show one type, since forms are not comparable).
 */
withDefaults(
    defineProps<{
        options: FormTypeOption[]
        allowAll?: boolean
        disabled?: boolean
    }>(),
    {
        allowAll: false,
        disabled: false,
    },
)

const model = defineModel<number | string | null>()

const { t, locale } = useLocale()
</script>

<template>
    <select
        v-model="model"
        class="w-full rounded-md border border-border px-3 py-2 text-sm"
        :disabled="disabled"
        :aria-label="t.forms.formType"
    >
        <option
            v-if="allowAll"
            value=""
        >
            {{ t.forms.allFormTypes }}
        </option>

        <option
            v-for="option in options"
            :key="option.id"
            :value="option.id"
        >
            {{ option.name[locale] }}{{ option.is_active ? '' : ` ${t.forms.inactiveSuffix}` }}
        </option>
    </select>
</template>
