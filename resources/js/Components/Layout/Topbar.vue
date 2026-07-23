<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useLocale, type Locale } from '@/Composables/useLocale'
import FlagIcon from '@/Components/UI/FlagIcon.vue'

defineEmits<{
    'toggle-sidebar': []
}>()

const page = usePage()

const { locale, setLocale } = useLocale()

// Endonyms: a language is always listed in its own language, so the switcher
// reads the same whichever locale is active.
const languages: { code: Locale; label: string }[] = [
    { code: 'id', label: 'Bahasa' },
    { code: 'en', label: 'English' },
]

const activeLanguage = computed(
    () => languages.find(language => language.code === locale.value)
        ?? languages[0],
)

// Only the alternatives are listed -- the current language is already on the
// trigger, so repeating it in the menu is just another thing to read past.
const otherLanguages = computed(
    () => languages.filter(language => language.code !== locale.value),
)

const open = ref(false)

function choose(code: Locale) {
    setLocale(code)

    open.value = false
}

const closeOnEscape = (event: KeyboardEvent) => {
    if (open.value && event.key === 'Escape') {
        open.value = false
    }
}

onMounted(() => document.addEventListener('keydown', closeOnEscape))
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape))

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
            <div class="relative shrink-0">
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-md px-1 py-1.5 text-sm font-semibold text-primary transition-colors hover:bg-slate-50"
                    aria-haspopup="menu"
                    :aria-expanded="open"
                    :aria-label="activeLanguage.label"
                    @click="open = !open"
                >
                    <FlagIcon :locale="activeLanguage.code" />

                    <!-- Narrow screens keep the flag only: it identifies the
                         language on its own, and the room goes to the name. -->
                    <span class="hidden sm:inline">
                        {{ activeLanguage.label }}
                    </span>

                    <!-- The icon is wrapped rather than carrying `hidden`
                         itself: FontAwesome's .fa-solid sets its own display at
                         the same specificity and, loading after Tailwind, wins.
                         The span has no such rule, so the toggle sticks. -->
                    <span class="hidden sm:inline-block">
                        <i
                            class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200"
                            :class="open && 'rotate-180'"
                        />
                    </span>
                </button>

                <!-- Click-away catcher, so the menu closes on any outside click -->
                <div
                    v-show="open"
                    class="fixed inset-0 z-40"
                    @click="open = false"
                />

                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-75"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div
                        v-show="open"
                        class="absolute left-0 z-50 mt-2 min-w-[150px] origin-top-left rounded-xl bg-white p-1.5 shadow-lg ring-1 ring-black/5"
                        role="menu"
                    >
                        <button
                            v-for="language in otherLanguages"
                            :key="language.code"
                            type="button"
                            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
                            role="menuitem"
                            @click="choose(language.code)"
                        >
                            <FlagIcon :locale="language.code" />

                            <span>{{ language.label }}</span>
                        </button>
                    </div>
                </Transition>
            </div>

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
