<script setup>
defineProps({
    title: String,
    description: String,
})
</script>

<template>
    <div
        class="mb-6 flex items-end justify-between"
    >
        <div>
            <h1 class="text-2xl font-bold">
                {{ title }}
            </h1>

            <p
                class="mt-1 text-sm text-slate-500"
            >
                {{ description }}
            </p>
        </div>

        <div>
            <slot name="actions" />
        </div>
    </div>
</template>