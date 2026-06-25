<script setup lang="ts">
defineProps<{
    type?: 'success' | 'error'
    message?: string | null
}>()
</script>

<template>
    <div
        v-if="message"
        class="mb-4 rounded-md border px-4 py-3 text-sm"
        :class="{
            'border-green-200 bg-green-50 text-green-700':
                type === 'success',

            'border-red-200 bg-red-50 text-red-700':
                type === 'error',
        }"
    >
        <div class="flex items-center gap-2">
            <i
                v-if="type === 'success'"
                class="fa-solid fa-circle-check"
            />

            <i
                v-if="type === 'error'"
                class="fa-solid fa-circle-exclamation"
            />

            <span>
                {{ message }}
            </span>
        </div>
    </div>
</template>