<script setup lang="ts">
import SearchableSelect from '@/Components/UI/SearchableSelect.vue'
import { useLocale } from '@/Composables/useLocale'
import { computed } from 'vue'
import type { FormTypeOption } from './types'

/**
 * Form type dropdown for the template list, report, dashboard and team view.
 * `allowAll` adds an "All Form Types" choice (template list only -- reports
 * and dashboards always show one type, since forms are not comparable).
 */
const props = withDefaults(
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

const selectOptions = computed(() => [
    ...(props.allowAll ? [{ value: '', label: t.value.forms.allFormTypes }] : []),
    ...props.options.map(option => ({
        value: option.id,
        label: `${option.name[locale.value]}${option.is_active ? '' : ` ${t.value.forms.inactiveSuffix}`}`,
    })),
])
</script>

<template>
    <SearchableSelect
        v-model="model"
        :options="selectOptions"
        class="w-full"
        :disabled="disabled"
        :aria-label="t.forms.formType"
    />
</template>
