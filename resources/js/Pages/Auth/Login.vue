<script setup lang="ts">
import { ref } from 'vue'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { useLocale } from '@/Composables/useLocale'
import LanguageSwitcher from '@/Components/UI/LanguageSwitcher.vue'

const { t } = useLocale()

const showPassword = ref(false)

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

function submit() {
    form.post(
        route('login'),
        {
            // Never leave a typed password sitting in memory after a failure.
            onFinish: () => form.reset('password'),
        },
    )
}
</script>

<template>
    <Head title="Login" />

    <AuthLayout>
        <!-- Language, matching the top bar's switcher -->

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
                {{ t.login.title }}
            </h1>

            <p class="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-slate-500">
                {{ t.login.subtitle }}
            </p>
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
                    {{ t.login.email }}
                </label>

                <div class="relative">
                    <i class="fa-regular fa-envelope pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400" />

                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        required
                        :placeholder="t.login.emailPlaceholder"
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

            <!-- Password -->

            <div>
                <label
                    for="password"
                    class="mb-1.5 block text-sm font-medium text-slate-700"
                >
                    {{ t.login.password }}
                </label>

                <div class="relative">
                    <i class="fa-solid fa-lock pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400" />

                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="current-password"
                        required
                        :placeholder="t.login.passwordPlaceholder"
                        class="w-full rounded-md border py-2.5 pl-9 pr-10 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        :class="form.errors.password ? 'border-red-500 bg-red-50' : 'border-border'"
                    >

                    <button
                        type="button"
                        class="absolute right-0 top-0 flex h-full w-10 items-center justify-center text-slate-400 transition-colors hover:text-primary"
                        :aria-label="showPassword ? t.login.hidePassword : t.login.showPassword"
                        tabindex="-1"
                        @click="showPassword = !showPassword"
                    >
                        <i
                            class="fa-solid text-sm"
                            :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"
                        />
                    </button>
                </div>

                <p
                    v-if="form.errors.password"
                    class="mt-1.5 text-xs text-red-600"
                >
                    {{ form.errors.password }}
                </p>
            </div>

            <!-- Remember me: the form already carried this flag, but nothing
                 on the page ever set it. -->

            <label class="flex cursor-pointer items-center gap-2 pt-1">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary"
                >

                <span class="text-sm text-slate-600">
                    {{ t.login.rememberMe }}
                </span>
            </label>

            <button
                type="submit"
                :disabled="form.processing"
                class="flex w-full items-center justify-center gap-2 rounded-md bg-primary py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[var(--primary-hover)] disabled:cursor-not-allowed disabled:opacity-60"
            >
                <i
                    v-if="form.processing"
                    class="fa-solid fa-spinner fa-spin"
                />

                {{ form.processing ? t.login.signingIn : t.login.signIn }}
            </button>
        </form>

        <p class="mt-6 border-t border-border pt-4 text-center text-xs text-slate-400">
            {{ t.login.ssoHint }}
        </p>
    </AuthLayout>
</template>
