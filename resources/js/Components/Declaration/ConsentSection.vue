<script setup>
const model = defineModel()
</script>

<template>
    <div
        class="rounded-md bg-slate-100 p-5"
    >
        <label
            class="flex items-start gap-3"
        >
            <input
                v-model="model"
                type="checkbox"
                class="mt-1"
            >

            <span
                class="text-sm font-semibold text-red-700"
            >
                I confirm that this declaration
                is completed by myself and all
                information provided is true.
            </span>
        </label>
    </div>
</template>