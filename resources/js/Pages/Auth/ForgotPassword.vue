<script setup lang="ts">
import AuthLayout from '@/Layouts/AuthLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { useLocale } from '@/Composables/useLocale'
import LanguageSwitcher from '@/Components/UI/LanguageSwitcher.vue'

defineProps<{
    status?: string
}>()

const { t } = useLocale()

const form = useForm({
    email: '',
})

function submit() {
    form.post(route('password.email'))
}
</script>

<template>
    <Head title="Forgot Password" />

    <AuthLayout>
        <!-- Language, matching the login screen's switcher -->

        <div class="mb-6 flex justify-end">
            <LanguageSwitcher align="right" />
        </div>

        <!-- Brand -->

        <div class="mb-7 text-center">
            <img
                src="https://compliance.hcis.live/storage/img/commitment-corner-logo-1.png"
                alt=""
                class="mx-auto mb-4 h-14 w-14 object-contain"
            >

            <h1 class="text-2xl font-bold text-primary">
                {{ t.forgotPassword.title }}
            </h1>

            <p class="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-slate-500">
                {{ t.forgotPassword.subtitle }}
            </p>
        </div>

        <!-- Success message once a link has been sent -->

        <div
            v-if="status"
            class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2.5 text-sm text-green-700"
        >
            {{ status }}
        </div>

        <form
            class="space-y-4"
            @submit.prevent="submit"
        >
            <!-- Email -->

            <div>
                <label
                    for="email"
                    class="mb-1.5 block text-sm font-medium text-slate-700"
                >
                    {{ t.forgotPassword.email }}
                </label>

                <div class="relative">
                    <i class="fa-regular fa-envelope pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400" />

                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        required
                        :placeholder="t.forgotPassword.emailPlaceholder"
                        class="w-full rounded-md border py-2.5 pl-9 pr-3 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        :class="form.errors.email ? 'border-red-500 bg-red-50' : 'border-border'"
                    >
                </div>

                <p
                    v-if="form.errors.email"
                    class="mt-1.5 text-xs text-red-600"
                >
                    {{ form.errors.email }}
                </p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="flex w-full items-center justify-center gap-2 rounded-md bg-primary py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[var(--primary-hover)] disabled:cursor-not-allowed disabled:opacity-60"
            >
                <i
                    v-if="form.processing"
                    class="fa-solid fa-spinner fa-spin"
                />

                {{ form.processing ? t.forgotPassword.sending : t.forgotPassword.sendLink }}
            </button>
        </form>

        <p class="mt-6 border-t border-border pt-4 text-center text-sm">
            <Link
                :href="route('login')"
                class="font-medium text-primary hover:underline"
            >
                {{ t.forgotPassword.backToLogin }}
            </Link>
        </p>
    </AuthLayout>
</template>
