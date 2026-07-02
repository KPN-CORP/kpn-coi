<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { useNavigation } from '@/Composables/useNavigation'


defineProps<{
    navigation: {
        label: string
        icon: string
        route: string
    }[]
}>()

function isActive(routeName: string) {
    return route().current(routeName)
}

const menus = useNavigation()
</script>

<template>
    <aside
        class="fixed left-0 top-0 z-50 flex h-screen w-[260px] flex-col border-r border-border bg-white"
    >
        <!-- Logo -->

        <div
            class="flex items-center gap-3 border-b border-border px-6 py-5"
        >
            <i
                class="fa-solid fa-shield-halved text-primary text-xl"
            />

            <span
                class="font-bold text-primary"
            >
                KPN Compliance
            </span>
        </div>

        <!-- Menu -->

        <nav class="flex-1 py-4">
            <Link
                v-for="menu in menus"
                :key="menu.route"
                :href="route(menu.route)"
                class="flex items-center gap-3 border-l-4 border-transparent px-6 py-3 text-sm text-text transition-all"
                :class="{
                    'border-primary bg-red-50 text-primary font-bold':
                        isActive(menu.route),
                    'hover:bg-red-50 hover:text-primary':
                        !isActive(menu.route),
                }"
            >
                <i :class="menu.icon" />

                <span>
                    {{ menu.label }}
                </span>
            </Link>
        </nav>

        <!-- Footer -->

        <button class="flex items-center gap-3 border-t border-l-4 hover:border-transparent px-6 py-3 text-sm font-light text-text transition-all border-primary hover:bg-red-50 text-primary hover:font-bold"
            @click="router.post(route('logout'))"
        >
            Logout
        </button>
        <div
            class="border-t border-border p-4"
        >
            <div
                class="text-xs text-slate-400"
            >
                KPN Compliance System
            </div>

            <div
                class="mt-1 text-xs text-slate-400"
            >
                Version 1.0.0
            </div>
        </div>
    </aside>
</template>