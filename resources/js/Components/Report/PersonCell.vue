<script setup lang="ts">
/**
 * The report's sticky first column: business unit, name, employee id and
 * contribution level of one person (COI and form tables alike).
 */
defineProps<{
    name: string
    employeeId?: string | null
    businessUnit?: string | null
    contributionLevel?: string | null
}>()
</script>

<template>
    <td class="py-4 sticky left-0 z-10 bg-white group-hover:bg-[#fafafa] shadow-[1px_0_0_0_#e2e8f0]">
        <!-- '-' is the server's "no unit" placeholder on COI rows -->
        <div
            v-if="businessUnit && businessUnit !== '-'"
            class="mt-0.5 truncate text-xs text-slate-400"
            :title="businessUnit"
        >
            {{ businessUnit }}
        </div>
        <div
            class="truncate font-medium text-slate-800"
            :title="name"
        >
            {{ name }}
        </div>
        <div class="mt-0.5 truncate text-xs text-slate-400">
            {{ employeeId || '-' }}
        </div>
        <div
            v-if="contributionLevel"
            class="mt-0.5 truncate text-xs text-slate-400"
            :title="contributionLevel"
        >
            {{ contributionLevel }}
        </div>
    </td>
</template>
