<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useLocale, type Locale } from '@/Composables/useLocale'

defineEmits<{
    'toggle-sidebar': []
}>()

const page = usePage()

const { locale, setLocale } = useLocale()

const languages: { code: Locale; label: string }[] = [
    { code: 'id', label: 'ID' },
    { code: 'en', label: 'EN' },
]

const user = computed(() => page.props.auth?.user)

const initials = computed(() => {
    if (!user.value?.name) {
        return 'GU'
    }

    return user.value.name
        .split(' ')
        .filter(Boolean)
        .map(word => word.charAt(0))
        .join('')
        .substring(0, 2)
        .toUpperCase()
})
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-[60px] items-center border-b border-border bg-white px-4 sm:px-8"
    >
        <!-- Mobile hamburger -->
        <button
            class="mr-3 text-slate-500 hover:text-primary lg:hidden"
            aria-label="Open menu"
            @click="$emit('toggle-sidebar')"
        >
            <i class="fa-solid fa-bars text-lg" />
        </button>

        <div class="ml-auto flex items-center gap-3">
            <!-- Language switcher -->
            <div
                class="flex items-center gap-1 rounded-md border border-border p-0.5"
            >
                <button
                    v-for="language in languages"
                    :key="language.code"
                    type="button"
                    class="rounded px-2 py-1 text-xs font-semibold transition-colors"
                    :class="locale === language.code
                        ? 'bg-primary text-white'
                        : 'text-slate-500 hover:text-primary'"
                    @click="setLocale(language.code)"
                >
                    {{ language.label }}
                </button>
            </div>

            <div class="text-right">
                <div class="text-sm font-bold">
                    {{ user.name }}
                </div>

                <div class="text-xs text-slate-400">
                    {{ user.employee_id }}
                </div>
            </div>

            <div
                class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-xs font-bold text-white"
            >
                {{ initials }}
            </div>
        </div>
    </header>
</template>
