<script setup lang="ts">
import { locales } from '@/Config/locales'

const model = defineModel<boolean>()

const props = defineProps<{
    locale: 'en' | 'id'
}>()

const locale = locales[props.locale]
</script>

<template>
    <div
        class="rounded-md bg-slate-100 p-5"
    >
        <label
            class="flex items-start gap-3"
        >
            <input
                v-model="model"
                type="checkbox"
                class="mt-1"
            >

            <span
                class="text-sm font-semibold text-red-700"
            >
                {{ locale.declaration.consent }}
            </span>
        </label>
    </div>
</template>