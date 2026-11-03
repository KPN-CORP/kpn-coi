<script setup lang="ts">
import { computed } from 'vue'

/**
 * A table header cell that sorts its column: the label plus an up/down
 * arrow, as a real button (keyboard reachable) with aria-sort on the <th>.
 * With no `column` it is a plain header. Extra classes land on the <th>.
 * Sorting itself stays with the page (server- or client-side); this only
 * shows the state and asks for a change.
 */
const props = defineProps<{
    label: string
    column?: string | null
    // The page's current sort column and direction.
    sort?: string | null
    direction?: string | null
}>()

const emit = defineEmits<{
    sort: [column: string]
}>()

const active = computed(() => !!props.column && props.sort === props.column)

const ariaSort = computed(() => {
    if (!props.column) {
        return undefined
    }

    if (!active.value) {
        return 'none'
    }

    return props.direction === 'desc' ? 'descending' : 'ascending'
})

const icon = computed(() => {
    if (!active.value) {
        return 'fa-solid fa-sort text-slate-300'
    }

    return props.direction === 'desc'
        ? 'fa-solid fa-sort-down text-slate-600'
        : 'fa-solid fa-sort-up text-slate-600'
})
</script>

<template>
    <th
        class="whitespace-nowrap"
        :aria-sort="ariaSort"
    >
        <button
            v-if="column"
            type="button"
            class="inline-flex select-none items-center gap-1.5 uppercase tracking-[inherit] [font:inherit] transition-colors hover:text-slate-700"
            @click="emit('sort', column)"
        >
            {{ label }}
            <i :class="icon" />
        </button>

        <template v-else>
            {{ label }}
        </template>
    </th>
</template>
