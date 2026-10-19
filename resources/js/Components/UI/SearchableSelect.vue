<script setup lang="ts">
import Multiselect from '@vueform/multiselect'
import { computed, ref } from 'vue'
import { useLocale } from '@/Composables/useLocale'

/**
 * A single-choice dropdown you can type into to filter the options -- the
 * app's replacement for a plain <select>. Built on the same
 * @vueform/multiselect as MultiSelect.vue (themed in coi.css).
 *
 * Use it like a select: v-model plus @change, which fires with the new value
 * after the model is updated.
 */

export interface SelectOption {
    value: string | number
    label: string
}

const props = withDefaults(defineProps<{
    modelValue: string | number | null | undefined
    options: SelectOption[]
    placeholder?: string
    disabled?: boolean
    ariaLabel?: string
    id?: string
    // Inside a modal or a scrolling box the list would be clipped; render it
    // on <body> instead.
    appendToBody?: boolean
}>(), {
    placeholder: undefined,
    ariaLabel: undefined,
    id: undefined,
})

const emit = defineEmits<{
    'update:modelValue': [string | number]
    change: [string | number]
}>()

const { t } = useLocale()

// The library treats an empty value as "nothing selected" and would show the
// placeholder instead of an "All ..." option whose value is ''. Swap '' for a
// marker on the way in and back on the way out.
const EMPTY = '__empty__'

const toInner = (value: string | number | null | undefined) => (value === '' || value === null || value === undefined ? EMPTY : value)
const toOuter = (value: string | number) => (value === EMPTY ? '' : value)

const innerOptions = computed(() => props.options.map(option => ({
    value: toInner(option.value),
    label: option.label,
})))

const hasEmptyOption = computed(() => props.options.some(option => option.value === ''))

const value = computed({
    // With no "All ..." option, an empty model shows the placeholder.
    get: () => (hasEmptyOption.value ? toInner(props.modelValue) : (props.modelValue ?? null)),
    set: (next: string | number | null) => {
        if (next === null) {
            return
        }

        const outer = toOuter(next)

        emit('update:modelValue', outer)
        emit('change', outer)
    },
})

// --- Open upward when the list would run off the bottom of the screen ------
// (e.g. a field at the foot of a sidebar). Decided just before it opens --
// on pointer down or keyboard focus -- so the list never flashes downward.

// Matches --ms-max-height in coi.css (16rem) plus the list's border/margin.
const LIST_MAX_HEIGHT = 16 * 16 + 12
const OPTION_HEIGHT = 36

const wrapper = ref<HTMLElement | null>(null)
const direction = ref<'bottom' | 'top'>('bottom')

function chooseDirection() {
    const box = wrapper.value?.getBoundingClientRect()

    if (!box) {
        return
    }

    const needed = Math.min(LIST_MAX_HEIGHT, Math.max(1, props.options.length) * OPTION_HEIGHT + 12)
    const below = window.innerHeight - box.bottom
    const above = box.top

    direction.value = below < needed && above > below ? 'top' : 'bottom'
}
</script>

<template>
    <!-- The wrapper takes the page's classes (w-48, w-full...): the library's
         own width: 100% would otherwise win over them. -->
    <div
        ref="wrapper"
        class="searchable-select-wrap"
        @pointerdown.capture="chooseDirection"
        @focusin="chooseDirection"
    >
        <Multiselect
            :id="id"
            v-model="value"
            :options="innerOptions"
            mode="single"
            searchable
            :can-deselect="false"
            :can-clear="false"
            :disabled="disabled"
            :placeholder="placeholder ?? t.common.selectPlaceholder"
            :no-results-text="t.common.noResults"
            :no-options-text="t.common.noResults"
            :aria="ariaLabel ? { 'aria-label': ariaLabel } : {}"
            :append-to-body="appendToBody"
            :open-direction="direction"
            class="searchable-select"
        />
    </div>
</template>
