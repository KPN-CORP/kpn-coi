<script setup lang="ts">
/**
 * One labelled control in a filter bar (label above, control in the slot).
 * Pass `for` when the control has an id, so the label focuses it.
 */
defineProps<{
    label: string
    for?: string
}>()
</script>

<template>
    <div class="flex flex-col gap-2">
        <label
            :for="$props.for"
            class="text-sm font-medium text-slate-700"
        >
            {{ label }}
        </label>

        <slot />
    </div>
</template>
