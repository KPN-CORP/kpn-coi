<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{
    links: any[]
    perPage: number
    total?: number
    from?: number | null
    to?: number | null
    perPageOptions?: number[]
}>()

const emit = defineEmits<{
    (e: 'update:perPage', value: number): void
}>()

const options = computed(
    () => props.perPageOptions ?? [10, 20, 50, 100]
)

// Laravel link collection: [prev, ...pages, next]
const prev = computed(() => props.links[0])
const next = computed(() => props.links[props.links.length - 1])
const pages = computed(() => props.links.slice(1, -1))

const hasPages = computed(() => props.links.length > 3)

function onPerPageChange(event: Event) {
    emit(
        'update:perPage',
        Number((event.target as HTMLSelectElement).value)
    )
}
</script>

<template>
    <div
        class="mt-6 flex flex-col items-center justify-between gap-4 sm:flex-row"
    >
        <!-- Left: rows per page -->
        <div class="flex items-center gap-2 text-sm text-slate-600">
            <span>Rows per page</span>

            <select
                :value="perPage"
                class="rounded-md border border-border px-2 py-1.5 text-sm w-24"
                @change="onPerPageChange"
            >
                <option
                    v-for="opt in options"
                    :key="opt"
                    :value="opt"
                >
                    {{ opt }}
                </option>
            </select>

            <span
                v-if="total != null"
                class="ml-1 text-slate-400"
            >
                {{ from ?? 0 }}–{{ to ?? 0 }} of {{ total }}
            </span>
        </div>

        <!-- Right: pagination -->
        <nav
            v-if="hasPages"
            class="flex items-center gap-1"
        >
            <!-- Previous -->
            <Link
                :href="prev.url ?? ''"
                class="inline-flex h-9 min-w-9 items-center justify-center rounded-md border border-border px-3 text-sm text-slate-600 transition hover:bg-slate-50"
                :class="{
                    'pointer-events-none opacity-40': !prev.url,
                }"
                aria-label="Previous"
            >
                <i class="fa-solid fa-chevron-left text-xs" />
            </Link>

            <!-- Page numbers -->
            <Link
                v-for="link in pages"
                :key="link.label"
                :href="link.url ?? ''"
                class="inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-3 text-sm transition"
                :class="[
                    link.active
                        ? 'border-primary bg-primary text-white'
                        : 'border-border text-slate-600 hover:bg-slate-50',
                    !link.url && 'pointer-events-none opacity-60',
                ]"
                v-html="link.label"
            />

            <!-- Next -->
            <Link
                :href="next.url ?? ''"
                class="inline-flex h-9 min-w-9 items-center justify-center rounded-md border border-border px-3 text-sm text-slate-600 transition hover:bg-slate-50"
                :class="{
                    'pointer-events-none opacity-40': !next.url,
                }"
                aria-label="Next"
            >
                <i class="fa-solid fa-chevron-right text-xs" />
            </Link>
        </nav>
    </div>
</template>
