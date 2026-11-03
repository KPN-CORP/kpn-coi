<script setup lang="ts">
/**
 * The rounded status pill (signed / draft / not started ...). The tone picks
 * the colour; the caller gives the text, so every status vocabulary (COI,
 * forms) shares one look.
 */
export type PillTone = 'success' | 'warning' | 'danger' | 'neutral'

withDefaults(defineProps<{
    tone?: PillTone
}>(), {
    tone: 'neutral',
})

const TONES: Record<PillTone, string> = {
    success: 'bg-green-50 text-green-700 border-green-200',
    warning: 'bg-amber-50 text-amber-700 border-amber-200',
    danger: 'bg-red-50 text-red-700 border-red-200',
    neutral: 'bg-slate-50 text-slate-500 border-slate-200',
}
</script>

<template>
    <span
        class="inline-flex whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-semibold"
        :class="TONES[tone]"
    >
        <slot />
    </span>
</template>
