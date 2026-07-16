<script setup lang="ts">
import Sidebar from '@/Components/Layout/Sidebar.vue'
import Topbar from '@/Components/Layout/Topbar.vue'
import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

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

const sidebarOpen = ref(false)

// Close the mobile drawer after navigating to a new page.
let stopNavListener: (() => void) | undefined

onMounted(() => {
    stopNavListener = router.on('navigate', () => {
        sidebarOpen.value = false
    })
})

onUnmounted(() => {
    stopNavListener?.()
})
</script>

<template>
    <div class="flex min-h-screen bg-page">
        <Sidebar
            :navigation="navigation"
            :open="sidebarOpen"
            @close="sidebarOpen = false"
        />

        <!-- Mobile backdrop -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-black/50 lg:hidden"
            @click="sidebarOpen = false"
        />

        <div class="flex min-h-screen min-w-0 flex-1 flex-col lg:ml-[260px]">
            <Topbar
                :user="user"
                @toggle-sidebar="sidebarOpen = !sidebarOpen"
            />

            <main class="min-w-0 p-4 sm:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
