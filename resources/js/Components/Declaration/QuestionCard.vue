<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
})
</script>

<template>
    <div
        class="mb-5 rounded-md border border-border bg-white p-5 shadow-sm"
    >
        <div
            class="mb-4 text-sm font-semibold text-slate-800"
        >
            {{ title }}
        </div>

        <slot />
    </div>
</template>