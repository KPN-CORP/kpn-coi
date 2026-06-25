<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

defineProps<{
    links: any[]
}>()
</script>

<template>
    <div
        v-if="links.length > 3"
        class="mt-6 flex flex-wrap gap-2"
    >
        <Link
            v-for="link in links"
            :key="link.label"
            :href="link.url ?? ''"
            class="rounded-md border px-3 py-2 text-sm"
            :class="{
                'border-primary bg-primary text-white':
                    link.active,

                'pointer-events-none opacity-50':
                    !link.url,
            }"
            v-html="link.label"
        />
    </div>
</template>