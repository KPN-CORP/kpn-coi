<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useLocale } from '@/Composables/useLocale'
import type { FormVariable } from './types'

/**
 * The toolbar's variable list with a search box: type to filter by label (in
 * either language) or key, arrows to move, Enter to insert. Lives inside a
 * ToolbarMenu panel, which closes it after a pick.
 *
 * Focusing the search box takes focus from the editor; that is fine -- the
 * insert runs through chain().focus(), which restores the editor's selection.
 */
const props = defineProps<{
    variables: FormVariable[]
}>()

const emit = defineEmits<{
    pick: [key: string]
}>()

const { t, locale } = useLocale()

const query = ref('')
const pointer = ref(0)
const input = ref<HTMLInputElement | null>(null)
const list = ref<HTMLElement | null>(null)

const token = (key: string) => `{{${key}}}`

const filtered = computed(() => {
    const needle = query.value.trim().toLowerCase()

    if (!needle) {
        return props.variables
    }

    return props.variables.filter(variable => [variable.key, variable.label.en, variable.label.id]
        .some(text => text.toLowerCase().includes(needle)))
})

watch(query, () => {
    pointer.value = 0
})

function move(step: number) {
    const count = filtered.value.length

    if (!count) {
        return
    }

    pointer.value = (pointer.value + step + count) % count

    nextTick(() => {
        list.value
            ?.querySelector<HTMLElement>(`[data-index="${pointer.value}"]`)
            ?.scrollIntoView({ block: 'nearest' })
    })
}

function pickPointed() {
    const variable = filtered.value[pointer.value]

    if (variable) {
        emit('pick', variable.key)
    }
}

onMounted(() => input.value?.focus())
</script>

<template>
    <div>
        <div class="relative mb-1">
            <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-slate-400" />
            <input
                ref="input"
                v-model="query"
                type="search"
                class="w-full rounded border border-border py-1.5 pl-7 pr-2 text-sm"
                :placeholder="t.forms.searchVariables"
                :aria-label="t.forms.searchVariables"
                role="combobox"
                aria-autocomplete="list"
                aria-controls="form-variable-list"
                :aria-activedescendant="filtered.length ? `form-variable-${pointer}` : undefined"
                @keydown.down.prevent="move(1)"
                @keydown.up.prevent="move(-1)"
                @keydown.enter.prevent="pickPointed"
            >
        </div>

        <div
            id="form-variable-list"
            ref="list"
            class="max-h-72 overflow-y-auto"
            role="listbox"
        >
            <button
                v-for="(variable, index) in filtered"
                :id="`form-variable-${index}`"
                :key="variable.key"
                type="button"
                role="option"
                :data-index="index"
                :aria-selected="index === pointer"
                class="form-menu-item flex-col !items-start !gap-0"
                :class="{ 'bg-red-50': index === pointer }"
                @mousedown.prevent
                @mouseenter="pointer = index"
                @click="emit('pick', variable.key)"
            >
                <span class="text-sm text-slate-700">{{ variable.label[locale] }}</span>
                <span class="font-mono text-[11px] text-slate-400">{{ token(variable.key) }}</span>
            </button>

            <p
                v-if="!filtered.length"
                class="px-2 py-3 text-center text-xs text-slate-400"
            >
                {{ t.common.noResults }}
            </p>
        </div>
    </div>
</template>
