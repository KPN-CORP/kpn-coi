<script setup>
defineProps({
    title: String,
    description: String,
})
</script>

<template>
    <div
        class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
    >
        <div>
            <h1 class="text-xl font-bold sm:text-2xl">
                {{ title }}
            </h1>

            <p
                class="mt-1 text-sm text-slate-500"
            >
                {{ description }}
            </p>
        </div>

        <div class="shrink-0">
            <slot name="actions" />
        </div>
    </div>
</template>