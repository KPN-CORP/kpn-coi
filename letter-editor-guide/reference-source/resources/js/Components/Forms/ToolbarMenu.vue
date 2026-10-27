<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'

/**
 * A toolbar button that opens a small panel (Insert, colours, alignment ...).
 * Closes on outside click, Escape, or when the panel calls `close`.
 */
withDefaults(
    defineProps<{
        title: string
        icon?: string
        label?: string
        align?: 'left' | 'right'
        width?: string
        active?: boolean
    }>(),
    {
        icon: '',
        label: '',
        align: 'left',
        width: 'w-56',
        active: false,
    },
)

const open = ref(false)
const root = ref<HTMLElement | null>(null)

function onDocumentClick(event: MouseEvent) {
    if (root.value && !root.value.contains(event.target as Node)) {
        open.value = false
    }
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        open.value = false
    }
}

watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('mousedown', onDocumentClick)
        document.addEventListener('keydown', onKeydown)
    } else {
        document.removeEventListener('mousedown', onDocumentClick)
        document.removeEventListener('keydown', onKeydown)
    }
})

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocumentClick)
    document.removeEventListener('keydown', onKeydown)
})

const close = () => {
    open.value = false
}
</script>

<template>
    <div
        ref="root"
        class="relative"
    >
        <button
            type="button"
            class="form-tb-btn gap-1.5 px-2"
            :class="{ 'is-active': active || open }"
            :title="title"
            :aria-label="title"
            :aria-expanded="open"
            @mousedown.prevent
            @click="open = !open"
        >
            <slot name="button">
                <i
                    v-if="icon"
                    :class="icon"
                />
                <span
                    v-if="label"
                    class="max-w-[7rem] truncate text-xs font-medium"
                >{{ label }}</span>
            </slot>
            <i class="fa-solid fa-chevron-down text-[9px] opacity-60" />
        </button>

        <div
            v-if="open"
            class="absolute z-30 mt-1 rounded-md border border-border bg-white p-1 shadow-lg"
            :class="[width, align === 'right' ? 'right-0' : 'left-0']"
        >
            <slot :close="close" />
        </div>
    </div>
</template>
