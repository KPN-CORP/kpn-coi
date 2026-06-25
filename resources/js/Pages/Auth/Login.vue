<script setup lang="ts">
import AuthLayout from '@/Layouts/AuthLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'

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
        <h1
            class="mb-6 text-center text-2xl font-bold"
        >
            COI Declaration System
        </h1>

        <form
            class="space-y-4"
            @submit.prevent="submit"
        >
            <div>
                <label
                    class="mb-1 block text-sm font-medium"
                >
                    Email
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
                    Password
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
                Sign In
            </button>
        </form>
    </AuthLayout>
</template>