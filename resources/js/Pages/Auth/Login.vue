<script setup lang="ts">
import AuthLayout from '@/Layouts/AuthLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { useLocale, type Locale } from '@/Composables/useLocale'

const { t, locale, setLocale } = useLocale()

const languages: { code: Locale; label: string }[] = [
    { code: 'id', label: 'ID' },
    { code: 'en', label: 'EN' },
]

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

function submit() {
    form.post(route('login'))
}
</script>

<template>
    <Head title="Login" />

    <AuthLayout>
        <div class="mb-4 flex justify-end">
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
        </div>

        <h1
            class="mb-6 text-center text-2xl font-bold"
        >
            {{ t.login.title }}
        </h1>

        <form
            class="space-y-4"
            @submit.prevent="submit"
        >
            <div>
                <label
                    class="mb-1 block text-sm font-medium"
                >
                    {{ t.login.email }}
                </label>

                <input
                    v-model="form.email"
                    type="email"
                    class="w-full rounded-md border border-border px-3 py-2"
                >

                <div
                    v-if="form.errors.email"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.email }}
                </div>
            </div>

            <div>
                <label
                    class="mb-1 block text-sm font-medium"
                >
                    {{ t.login.password }}
                </label>

                <input
                    v-model="form.password"
                    type="password"
                    class="w-full rounded-md border border-border px-3 py-2"
                >

                <div
                    v-if="form.errors.password"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.password }}
                </div>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-md bg-primary py-2 text-white"
            >
                {{ t.login.signIn }}
            </button>
        </form>
    </AuthLayout>
</template>
