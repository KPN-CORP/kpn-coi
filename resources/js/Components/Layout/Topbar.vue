<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()

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
        class="sticky top-0 z-40 flex h-[60px] items-center justify-end border-b border-border bg-white px-8"
    >
        <div class="flex items-center gap-3">
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