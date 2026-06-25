<script setup>
defineProps({
    title: String,
    value: [String, Number],
    icon: String,
})
</script>

<template>
    <div class="rounded-md border border-border bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-500">
                    {{ title }}
                </div>

                <div class="mt-2 text-3xl font-bold">
                    {{ value }}
                </div>
            </div>

            <i
                :class="icon"
                class="text-2xl text-primary"
            />
        </div>
    </div>
</template>