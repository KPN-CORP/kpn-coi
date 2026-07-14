<script setup lang="ts">
import Sidebar from '@/Components/Layout/Sidebar.vue'
import Topbar from '@/Components/Layout/Topbar.vue'

defineProps({
    navigation: {
        type: Array,
        default: () => [],
    },

    user: {
        type: Object,
        default: () => ({
            name: 'Guest User',
            employee_id: '',
            initials: 'GU',
        }),
    },
})

</script>

<template>
    <div class="flex min-h-screen bg-page">
        <Sidebar :navigation="navigation" />

        <div class="ml-[260px] flex min-h-screen min-w-0 flex-1 flex-col">
            <Topbar :user="user" />

            <main class="min-w-0 p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
