<script setup lang="ts">
defineProps<{
    variant?: 'primary' | 'outline' | 'danger'
}>()
</script>

<template>
    <button
        :class="[
            variant === 'primary' && 'btn-primary',
            variant === 'outline' && 'btn-outline',
            variant === 'danger' && 'btn-danger',
        ]"
    >
        <slot />
    </button>
</template>