<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { useNavigation, type NavigationItem } from '@/Composables/useNavigation'
import { useLocale } from '@/Composables/useLocale'
import { computed, ref } from 'vue'

const { t } = useLocale()

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

function isActive(menu: NavigationItem) {
    // Items sharing a route (one per form type) are told apart by params.
    return menu.params
        ? route().current(menu.route, menu.params)
        : route().current(menu.activePattern ?? menu.route)
}

const menus = useNavigation()

const menuKey = (menu: NavigationItem) => menu.route + JSON.stringify(menu.params ?? {})

const childActive = (menu: NavigationItem) => Boolean(menu.children?.some(isActive))

// Dropdowns the user toggled; one holding the current page is open regardless.
const expanded = ref<Record<string, boolean>>({})

const isExpanded = (menu: NavigationItem) => expanded.value[menuKey(menu)] ?? childActive(menu)

function toggle(menu: NavigationItem) {
    expanded.value[menuKey(menu)] = !isExpanded(menu)
}

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
            class="flex h-[var(--topbar-height)] shrink-0 items-center gap-3 border-b border-border px-6"
        >
            <!-- Commitment Corner logo -->
            <img
                src="https://commitment-corner.hcis.live/storage/img/commitment-corner-logo-1.png"
                alt="Commitment Corner"
                class="h-10 w-10 shrink-0 object-contain"
            >

            <span
                class="font-bold text-primary"
            >
                Commitment Corner
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

                <template
                    v-for="menu in items"
                    :key="menuKey(menu)"
                >
                    <!-- Dropdown (e.g. a form type for a manager) -->
                    <div v-if="menu.children">
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 border-l-4 border-transparent px-6 py-3 text-left text-sm text-text transition-all hover:bg-red-50 hover:text-primary"
                            :class="{ 'text-primary font-bold': childActive(menu) }"
                            :aria-expanded="isExpanded(menu)"
                            @click="toggle(menu)"
                        >
                            <i :class="menu.icon" />

                            <span>
                                {{ menu.label }}
                            </span>

                            <span
                                v-if="menu.badge"
                                class="ml-auto inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-primary px-1.5 text-xs font-bold text-white"
                            >
                                {{ menu.badge }}
                            </span>

                            <i
                                class="fa-solid fa-chevron-down text-[10px] transition-transform"
                                :class="[menu.badge ? '' : 'ml-auto', { 'rotate-180': isExpanded(menu) }]"
                            />
                        </button>

                        <div v-show="isExpanded(menu)">
                            <Link
                                v-for="child in menu.children"
                                :key="menuKey(child)"
                                :href="route(child.route, child.params)"
                                class="flex items-center gap-3 border-l-4 border-transparent py-2.5 pl-12 pr-6 text-sm text-text transition-all"
                                :class="{
                                    'border-primary bg-red-50 text-primary font-bold': isActive(child),
                                    'hover:bg-red-50 hover:text-primary': !isActive(child),
                                }"
                            >
                                <i :class="[child.icon, 'text-xs']" />

                                <span>
                                    {{ child.label }}
                                </span>

                                <span
                                    v-if="child.badge"
                                    class="ml-auto inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-primary px-1.5 text-xs font-bold text-white"
                                >
                                    {{ child.badge }}
                                </span>
                            </Link>
                        </div>
                    </div>

                    <Link
                        v-else
                        :href="route(menu.route, menu.params)"
                        class="flex items-center gap-3 border-l-4 border-transparent px-6 py-3 text-sm text-text transition-all"
                        :class="{
                            'border-primary bg-red-50 text-primary font-bold':
                                isActive(menu),
                            'hover:bg-red-50 hover:text-primary':
                                !isActive(menu),
                        }"
                    >
                        <i :class="menu.icon" />

                        <span>
                            {{ menu.label }}
                        </span>

                        <span
                            v-if="menu.badge"
                            class="ml-auto inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-primary px-1.5 text-xs font-bold text-white"
                        >
                            {{ menu.badge }}
                        </span>
                    </Link>
                </template>
            </div>
        </nav>

        <!-- Footer -->

        <button class="flex items-center gap-3 border-t border-l-4 hover:border-transparent px-6 py-3 text-sm font-light text-text transition-all border-primary hover:bg-red-50 text-primary hover:font-bold"
            @click="router.post(route('logout'))"
        >
            {{ t.nav.logout }}
        </button>
        <div
            class="border-t border-border p-4"
        >
            <div
                class="text-xs text-slate-400"
            >
                {{ t.nav.systemName }}
            </div>

            <div
                class="mt-1 text-xs text-slate-400"
            >
                {{ t.nav.version }}
            </div>
        </div>
    </aside>
</template>