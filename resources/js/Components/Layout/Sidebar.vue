<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { useNavigation } from '@/Composables/useNavigation'
import { computed } from 'vue'

defineProps<{
    navigation: {
        label: string
        icon: string
        route: string
    }[]
    open?: boolean
}>()

defineEmits<{
    close: []
}>()

function isActive(routeName: string) {
    return route().current(routeName)
}

const menus = useNavigation()

const groupedMenus = computed(() => {
    return menus.value.reduce((groups, menu) => {
        (groups[menu.section] ??= []).push(menu)
        return groups
    }, {} as Record<string, typeof menus.value>)
})
</script>

<template>
    <aside
        class="fixed left-0 top-0 z-50 flex h-screen w-[260px] flex-col border-r border-border bg-white transform transition-transform duration-200 ease-in-out lg:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'"
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

            <!-- Mobile close button -->
            <button
                class="ml-auto text-slate-400 hover:text-primary lg:hidden"
                aria-label="Close menu"
                @click="$emit('close')"
            >
                <i class="fa-solid fa-xmark text-lg" />
            </button>
        </div>

        <!-- Menu -->

        <nav class="flex-1 overflow-y-auto py-4">
            <div
                v-for="(items, section) in groupedMenus"
                :key="section"
                class="mb-6"
            >
                <div
                    class="px-6 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-300"
                >
                    {{ section }}
                </div>

                <Link
                    v-for="menu in items"
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
            </div>
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