<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'
import { useLocale } from '@/Composables/useLocale'

const { t } = useLocale()

interface EmployeeOption {
    employee_id: string
    name: string
    email: string | null
    business_unit: string | null
    designation: string | null
}

const props = defineProps<{
    show: boolean
    user?: { id: number; name: string; email: string } | null
    employeeOptions?: EmployeeOption[]
}>()

const emit = defineEmits<{
    close: []
}>()

const search = ref('')
const searching = ref(false)
const selected = ref<EmployeeOption | null>(null)

const form = useForm({
    employee_id: '',
})

// The employee table has thousands of rows, so the list is fetched per keyword
// rather than shipped with the page. `only` keeps the reload to that one prop.
let debounce: ReturnType<typeof setTimeout> | undefined

watch(search, (keyword) => {
    clearTimeout(debounce)

    if (keyword.trim().length < 2) {
        searching.value = false

        return
    }

    searching.value = true

    debounce = setTimeout(() => {
        router.reload({
            only: ['employeeOptions'],
            data: { employee_search: keyword },
            preserveState: true,
            preserveScroll: true,
            onFinish: () => (searching.value = false),
        })
    }, 300)
})

watch(
    () => props.show,
    (show) => {
        if (!show) {
            return
        }

        search.value = ''
        selected.value = null
        form.reset()
        form.clearErrors()
    },
)

const options = computed(() => props.employeeOptions ?? [])

const showEmptyState = computed(
    () => search.value.trim().length >= 2
        && !searching.value
        && options.value.length === 0,
)

function choose(option: EmployeeOption) {
    selected.value = option
    form.employee_id = option.employee_id
    form.clearErrors()
}

function submit() {
    if (!props.user || !form.employee_id) {
        return
    }

    form.post(
        route('admin.credentials.convert', props.user.id),
        {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        },
    )
}
</script>

<template>
    <Modal
        :show="show"
        maxWidth="max-w-2xl"
        @close="emit('close')"
    >
        <!-- Header -->

        <div class="flex items-center justify-between border-b border-border bg-slate-50 px-6 py-4">
            <div>
                <h2 class="text-lg font-bold text-slate-900">
                    {{ t.credentials.convertTitle }}
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    {{ t.credentials.convertSubtitle }}
                </p>
            </div>

            <button
                type="button"
                class="rounded-md p-2 transition hover:bg-slate-200"
                @click="emit('close')"
            >
                <i class="fa-solid fa-xmark" />
            </button>
        </div>

        <!-- Body -->

        <div class="max-h-[65vh] overflow-y-auto p-6">
            <!-- Who is being converted -->

            <div class="mb-4 rounded-md border border-border bg-slate-50 px-4 py-3">
                <div class="text-xs uppercase tracking-wide text-slate-400">
                    {{ t.credentials.convertNonEmployee }}
                </div>

                <div class="mt-1 text-sm font-semibold text-slate-800">
                    {{ user?.name }}
                </div>

                <div class="text-xs text-slate-500">
                    {{ user?.email }}
                </div>
            </div>

            <!-- Search -->

            <label class="mb-1 block text-sm font-medium">
                {{ t.credentials.convertSearchLabel }}
            </label>

            <div class="relative">
                <input
                    v-model="search"
                    type="text"
                    class="w-full rounded-md border border-border py-2 pl-9 pr-3 text-sm"
                    :placeholder="t.credentials.convertSearchPlaceholder"
                >

                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" />
            </div>

            <p
                v-if="form.errors.employee_id"
                class="mt-2 text-xs text-red-500"
            >
                {{ form.errors.employee_id }}
            </p>

            <!-- Results -->

            <div class="mt-3 space-y-2">
                <p
                    v-if="search.trim().length < 2"
                    class="py-6 text-center text-sm text-slate-400"
                >
                    {{ t.credentials.convertSearchHint }}
                </p>

                <p
                    v-else-if="searching"
                    class="py-6 text-center text-sm text-slate-400"
                >
                    <i class="fa-solid fa-spinner fa-spin" />
                    {{ t.common.loading }}
                </p>

                <p
                    v-else-if="showEmptyState"
                    class="py-6 text-center text-sm text-slate-400"
                >
                    {{ t.credentials.convertNoResults }}
                </p>

                <button
                    v-for="option in options"
                    v-else
                    :key="option.employee_id"
                    type="button"
                    class="flex w-full items-start justify-between gap-3 rounded-md border p-3 text-left transition"
                    :class="selected?.employee_id === option.employee_id
                        ? 'border-primary bg-red-50'
                        : 'border-border hover:bg-slate-50'"
                    @click="choose(option)"
                >
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-slate-800">
                            {{ option.name }}
                        </div>

                        <div class="truncate text-xs text-slate-500">
                            {{ option.employee_id }}
                            <span v-if="option.email"> · {{ option.email }}</span>
                        </div>

                        <div
                            v-if="option.business_unit || option.designation"
                            class="truncate text-xs text-slate-400"
                        >
                            {{ [option.designation, option.business_unit].filter(Boolean).join(' · ') }}
                        </div>
                    </div>

                    <i
                        v-if="selected?.employee_id === option.employee_id"
                        class="fa-solid fa-circle-check mt-1 text-primary"
                    />
                </button>
            </div>

            <!-- What will happen -->

            <div
                v-if="selected"
                class="mt-4 rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-xs text-sky-800"
            >
                <i class="fa-solid fa-circle-info mr-1" />
                {{ t.credentials.convertNotice }}
            </div>
        </div>

        <!-- Footer -->

        <div class="flex justify-end gap-3 border-t border-border bg-slate-50 px-6 py-4">
            <button
                type="button"
                class="btn-outline-custom"
                @click="emit('close')"
            >
                {{ t.common.cancel }}
            </button>

            <button
                type="button"
                class="btn-primary-custom"
                :disabled="!selected || form.processing"
                @click="submit"
            >
                <i
                    v-if="form.processing"
                    class="fa-solid fa-spinner fa-spin"
                />

                {{ t.credentials.convertConfirm }}
            </button>
        </div>
    </Modal>
</template>
