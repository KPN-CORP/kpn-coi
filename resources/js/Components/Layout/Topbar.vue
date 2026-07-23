<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import LanguageSwitcher from '@/Components/UI/LanguageSwitcher.vue'

defineEmits<{
    'toggle-sidebar': []
}>()

const page = usePage()

const user = computed(() => page.props.auth?.user)
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-[var(--topbar-height)] items-center border-b border-border bg-white px-4 sm:px-8"
    >
        <!-- Mobile hamburger -->
        <button
            class="mr-3 text-slate-500 hover:text-primary lg:hidden"
            aria-label="Open menu"
            @click="$emit('toggle-sidebar')"
        >
            <i class="fa-solid fa-bars text-lg" />
        </button>

        <div class="ml-auto flex min-w-0 items-center gap-3 sm:gap-5">
            <!-- Language switcher -->
            <LanguageSwitcher collapse-label />

            <!-- Divider -->
            <span
                class="h-8 w-px shrink-0 bg-slate-200"
                aria-hidden="true"
            />

            <!-- Signed-in user -->
            <div class="flex min-w-0 items-center gap-3">
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-teal-400 text-white"
                >
                    <i class="fa-solid fa-user text-sm" />
                </div>

                <!-- Kept on every width; a long name truncates rather than
                     pushing the row wider than the header. -->
                <div class="min-w-0 leading-tight">
                    <div class="truncate text-sm font-bold text-slate-800">
                        {{ user?.name }}
                    </div>

                    <div class="truncate text-xs text-slate-400">
                        {{ user?.employee_id }}
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>
