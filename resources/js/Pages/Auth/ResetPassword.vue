<script setup lang="ts">
import { computed, ref } from 'vue'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { useLocale } from '@/Composables/useLocale'
import LanguageSwitcher from '@/Components/UI/LanguageSwitcher.vue'

const props = defineProps<{
    email: string
    token: string
}>()

const { t } = useLocale()

const showPassword = ref(false)

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
})

// Mirrors the server-side Password::defaults() policy set in AppServiceProvider.
// Purely for live feedback -- the backend remains the source of truth.
const rules = computed(() => {
    const pw = form.password
    return {
        min: pw.length >= 8,
        mixedCase: /[a-z]/.test(pw) && /[A-Z]/.test(pw),
        number: /\d/.test(pw),
        symbol: /[^A-Za-z0-9]/.test(pw),
        match: pw.length > 0 && pw === form.password_confirmation,
    }
})

const allValid = computed(() => Object.values(rules.value).every(Boolean))

function submit() {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Reset Password" />

    <AuthLayout>
        <!-- Language, matching the login screen's switcher -->

        <div class="mb-6 flex justify-end">
            <LanguageSwitcher align="right" />
        </div>

        <!-- Brand -->

        <div class="mb-7 text-center">
            <img
                src="https://commitment-corner.hcis.live/storage/img/commitment-corner-logo-1.png"
                alt=""
                class="mx-auto mb-4 h-14 w-14 object-contain"
            >

            <h1 class="text-2xl font-bold text-primary">
                {{ t.resetPassword.title }}
            </h1>

            <p class="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-slate-500">
                {{ t.resetPassword.subtitle }}
            </p>
        </div>

        <form
            class="space-y-4"
            @submit.prevent="submit"
        >
            <!-- Email (read-only, carried from the reset link) -->

            <div>
                <label
                    for="email"
                    class="mb-1.5 block text-sm font-medium text-slate-700"
                >
                    {{ t.resetPassword.email }}
                </label>

                <div class="relative">
                    <i class="fa-regular fa-envelope pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400" />

                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        readonly
                        class="w-full cursor-not-allowed rounded-md border border-border bg-slate-50 py-2.5 pl-9 pr-3 text-sm text-slate-500"
                    >
                </div>

                <p
                    v-if="form.errors.email"
                    class="mt-1.5 text-xs text-red-600"
                >
                    {{ form.errors.email }}
                </p>
            </div>

            <!-- New password -->

            <div>
                <label
                    for="password"
                    class="mb-1.5 block text-sm font-medium text-slate-700"
                >
                    {{ t.resetPassword.newPassword }}
                </label>

                <div class="relative">
                    <i class="fa-solid fa-lock pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400" />

                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="new-password"
                        required
                        :placeholder="t.resetPassword.newPasswordPlaceholder"
                        class="w-full rounded-md border py-2.5 pl-9 pr-10 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        :class="form.errors.password ? 'border-red-500 bg-red-50' : 'border-border'"
                    >

                    <button
                        type="button"
                        class="absolute right-0 top-0 flex h-full w-10 items-center justify-center text-slate-400 transition-colors hover:text-primary"
                        :aria-label="showPassword ? t.resetPassword.hidePassword : t.resetPassword.showPassword"
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

            <!-- Confirm password -->

            <div>
                <label
                    for="password_confirmation"
                    class="mb-1.5 block text-sm font-medium text-slate-700"
                >
                    {{ t.resetPassword.confirmPassword }}
                </label>

                <div class="relative">
                    <i class="fa-solid fa-lock pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400" />

                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="new-password"
                        required
                        :placeholder="t.resetPassword.confirmPasswordPlaceholder"
                        class="w-full rounded-md border border-border py-2.5 pl-9 pr-3 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                </div>
            </div>

            <!-- Live password-requirement checklist -->

            <div class="rounded-md bg-slate-50 px-3 py-3">
                <p class="mb-2 text-xs font-medium text-slate-600">
                    {{ t.resetPassword.requirementsTitle }}
                </p>

                <ul class="space-y-1.5 text-xs">
                    <li
                        v-for="rule in [
                            { key: 'min', label: t.resetPassword.ruleMin, ok: rules.min },
                            { key: 'mixedCase', label: t.resetPassword.ruleMixedCase, ok: rules.mixedCase },
                            { key: 'number', label: t.resetPassword.ruleNumber, ok: rules.number },
                            { key: 'symbol', label: t.resetPassword.ruleSymbol, ok: rules.symbol },
                            { key: 'match', label: t.resetPassword.ruleMatch, ok: rules.match },
                        ]"
                        :key="rule.key"
                        class="flex items-center gap-2"
                        :class="rule.ok ? 'text-green-600' : 'text-slate-400'"
                    >
                        <i
                            class="fa-solid text-[10px]"
                            :class="rule.ok ? 'fa-circle-check' : 'fa-circle'"
                        />
                        {{ rule.label }}
                    </li>
                </ul>
            </div>

            <button
                type="submit"
                :disabled="form.processing || !allValid"
                class="flex w-full items-center justify-center gap-2 rounded-md bg-primary py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[var(--primary-hover)] disabled:cursor-not-allowed disabled:opacity-60"
            >
                <i
                    v-if="form.processing"
                    class="fa-solid fa-spinner fa-spin"
                />

                {{ form.processing ? t.resetPassword.submitting : t.resetPassword.submit }}
            </button>
        </form>

        <p class="mt-6 border-t border-border pt-4 text-center text-sm">
            <Link
                :href="route('login')"
                class="font-medium text-primary hover:underline"
            >
                {{ t.resetPassword.backToLogin }}
            </Link>
        </p>
    </AuthLayout>
</template>
