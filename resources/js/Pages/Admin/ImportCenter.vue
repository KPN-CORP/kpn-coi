<script setup lang="ts">
import PageHeader from '@/Components/UI/PageHeader.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import SearchableSelect from '@/Components/UI/SearchableSelect.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import { formatDateTime } from '@/Utils/date'
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import { useLocale } from '@/Composables/useLocale'
import { usePermission } from '@/Composables/usePermission'

type Localized = { en: string, id: string }

interface ImportFormType {
    code: string
    name: Localized
    periods: { value: number, label: Localized }[]
}

type ImportStatus = 'processing' | 'completed' | 'partial' | 'failed'

interface ImportLog {
    id: number
    form: Localized | null
    file_name: string
    file_size: number
    user_name: string | null
    created_at: string
    status: ImportStatus
    message: string | null
    total: number
    imported: number
    already_signed: number
    not_signed: number
    failed: number
    has_report: boolean
}

interface Paginated<T> {
    data: T[]
    links: any[]
    per_page: number
    total: number
    from: number | null
    to: number | null
}

const props = defineProps<{
    formTypes: ImportFormType[]
    history: Paginated<ImportLog>
    lastImport: ImportLog | null
}>()

const { t, locale } = useLocale()
const { can } = usePermission()

const formCode = ref<string>(props.formTypes[0]?.code ?? '')

const selectedType = computed(() => props.formTypes.find(type => type.code === formCode.value))

// Year and month are per row in the file; the page only picks the form.
// The signed documents take the language of the top-menu toggle.
const form = useForm<{ locale: 'en' | 'id', file: File | null }>({
    locale: locale.value,
    file: null,
})

const fileInput = ref<HTMLInputElement | null>(null)
const dragging = ref(false)

const formOptions = computed(() => props.formTypes.map(type => ({
    value: type.code,
    label: type.name[locale.value],
})))

// Months with an active template -- what Year/Month in the file can point at.
const periods = computed(() => (selectedType.value?.periods ?? []).map(period => period.label[locale.value]))

const canImport = computed(() => !!selectedType.value && periods.value.length > 0 && can('import.create'))

function pick(file: File | null | undefined) {
    if (!file) {
        return
    }

    if (!['xlsx', 'xls'].includes(file.name.split('.').pop()?.toLowerCase() ?? '')) {
        Swal.fire({ icon: 'warning', text: t.value.uploadModal.onlyExcel, confirmButtonColor: '#ab2f2b' })

        return
    }

    form.clearErrors()
    form.file = file
}

function onDrop(event: DragEvent) {
    dragging.value = false
    pick(event.dataTransfer?.files?.[0])
}

function clearFile() {
    form.file = null

    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

function formatSize(bytes: number): string {
    return bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

function escapeHtml(text: string): string {
    const element = document.createElement('span')
    element.textContent = text

    return element.innerHTML
}

const STATUS_STYLES: Record<ImportStatus, { banner: string, badge: string, icon: string }> = {
    completed: { banner: 'border-green-200 bg-green-50 text-green-800', badge: 'bg-green-100 text-green-700', icon: 'fa-circle-check' },
    partial: { banner: 'border-amber-200 bg-amber-50 text-amber-800', badge: 'bg-amber-100 text-amber-700', icon: 'fa-triangle-exclamation' },
    failed: { banner: 'border-red-200 bg-red-50 text-red-700', badge: 'bg-red-100 text-red-700', icon: 'fa-circle-xmark' },
    processing: { banner: 'border-slate-200 bg-slate-50 text-slate-700', badge: 'bg-slate-100 text-slate-600', icon: 'fa-spinner' },
}

function changePerPage(perPage: number) {
    router.get(route('admin.import-center.index'), { per_page: perPage }, { preserveScroll: true, preserveState: true })
}

async function submit() {
    if (!selectedType.value || !form.file) {
        return
    }

    const confirmed = await Swal.fire({
        icon: 'question',
        title: t.value.importCenter.confirmTitle,
        html: `<b>${escapeHtml(form.file.name)}</b><br>→ ${escapeHtml(selectedType.value.name[locale.value])}`,
        showCancelButton: true,
        confirmButtonText: t.value.importCenter.importButton,
        cancelButtonText: t.value.common.cancel,
        confirmButtonColor: '#ab2f2b',
    })

    if (!confirmed.isConfirmed) {
        return
    }

    form.locale = locale.value

    form.post(route('admin.import-center.forms.import', selectedType.value.code), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: clearFile,
    })
}
</script>

<template>
    <AdminLayout>
        <PageHeader
            :title="t.importCenter.title"
            :description="t.importCenter.description"
        />

        <div class="space-y-4">
            <!-- Outcome of the last upload -->
            <div
                v-if="lastImport"
                class="rounded-lg border p-4"
                :class="STATUS_STYLES[lastImport.status].banner"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm font-semibold">
                        <i
                            class="fa-solid mr-2"
                            :class="STATUS_STYLES[lastImport.status].icon"
                        />
                        {{ t.importCenter.status[lastImport.status] }} · {{ lastImport.form?.[locale] }}
                        <div
                            v-if="lastImport.message"
                            class="mt-1 font-normal"
                        >
                            {{ lastImport.message }}
                        </div>
                    </div>

                    <a
                        v-if="lastImport.has_report"
                        :href="route('admin.import-center.history.report', lastImport.id)"
                        class="inline-flex shrink-0 items-center justify-center rounded-md border border-current px-3 py-2 text-sm font-medium transition hover:bg-white"
                    >
                        <i class="fa-solid fa-download mr-2" />
                        {{ t.importCenter.downloadResult }}
                    </a>
                </div>

                <div
                    v-if="lastImport.total"
                    class="mt-3 grid grid-cols-2 gap-2 text-center sm:grid-cols-4"
                >
                    <div class="rounded-md bg-white p-2">
                        <div class="text-xl font-bold text-green-700">{{ lastImport.imported }}</div>
                        <div class="text-xs text-slate-500">{{ t.importCenter.resultImported }}</div>
                    </div>

                    <div class="rounded-md bg-white p-2">
                        <div class="text-xl font-bold text-slate-700">{{ lastImport.already_signed }}</div>
                        <div class="text-xs text-slate-500">{{ t.importCenter.resultAlreadySigned }}</div>
                    </div>

                    <div class="rounded-md bg-white p-2">
                        <div class="text-xl font-bold text-slate-700">{{ lastImport.not_signed }}</div>
                        <div class="text-xs text-slate-500">{{ t.importCenter.resultNotSigned }}</div>
                    </div>

                    <div class="rounded-md bg-white p-2">
                        <div
                            class="text-xl font-bold"
                            :class="lastImport.failed ? 'text-red-600' : 'text-slate-700'"
                        >
                            {{ lastImport.failed }}
                        </div>
                        <div class="text-xs text-slate-500">{{ t.importCenter.resultFailed }}</div>
                    </div>
                </div>
            </div>

            <section class="rounded-lg border border-border bg-white">
                <p
                    v-if="!formTypes.length"
                    class="p-6 text-sm text-slate-500"
                >
                    {{ t.importCenter.noForms }}
                </p>

                <ol
                    v-else
                    class="divide-y divide-border"
                >
                    <!-- 1. Form -->
                    <li class="flex gap-4 p-5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-white">1</span>

                        <div class="min-w-0 flex-1">
                            <div class="mb-2 font-semibold">{{ t.importCenter.chooseForm }}</div>

                            <SearchableSelect
                                v-model="formCode"
                                :options="formOptions"
                                :aria-label="t.importCenter.chooseForm"
                                class="sm:max-w-sm"
                            />

                            <div
                                v-if="selectedType"
                                class="mt-2 flex flex-wrap items-center gap-1.5 text-xs"
                            >
                                <template v-if="periods.length">
                                    <span class="text-slate-500">{{ t.importCenter.activeTemplates }}</span>
                                    <span
                                        v-for="period in periods"
                                        :key="period"
                                        class="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-700"
                                    >
                                        {{ period }}
                                    </span>
                                </template>

                                <span
                                    v-else
                                    class="text-amber-700"
                                >
                                    <i class="fa-solid fa-triangle-exclamation mr-1" />
                                    {{ t.importCenter.noPeriods }}
                                </span>
                            </div>
                        </div>
                    </li>

                    <!-- 2. Template -->
                    <li class="flex gap-4 p-5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-white">2</span>

                        <div class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="font-semibold">{{ t.importCenter.fillTemplate }}</div>
                                <div class="mt-0.5 text-xs text-slate-500">{{ t.importCenter.templateColumns }}</div>
                            </div>

                            <a
                                v-if="selectedType"
                                :href="route('admin.import-center.forms.template', selectedType.code)"
                                class="inline-flex shrink-0 items-center justify-center rounded-md border border-primary px-3 py-2 text-sm font-medium text-primary transition hover:bg-red-50"
                            >
                                <i class="fa-solid fa-download mr-2" />
                                {{ t.uploadModal.downloadTemplate }}
                            </a>
                        </div>
                    </li>

                    <!-- 3. Upload -->
                    <li
                        v-if="can('import.create')"
                        class="flex gap-4 p-5"
                    >
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-white">3</span>

                        <div class="min-w-0 flex-1">
                            <div class="mb-2 font-semibold">{{ t.importCenter.uploadFile }}</div>

                            <input
                                ref="fileInput"
                                type="file"
                                accept=".xlsx,.xls"
                                class="hidden"
                                @change="pick(($event.target as HTMLInputElement).files?.[0])"
                            >

                            <div
                                v-if="form.file"
                                class="flex items-center gap-3 rounded-md border border-green-200 bg-green-50 p-3"
                            >
                                <i class="fa-solid fa-file-excel text-2xl text-green-600" />

                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium">{{ form.file.name }}</div>
                                    <div class="text-xs text-slate-500">{{ formatSize(form.file.size) }}</div>
                                </div>

                                <button
                                    type="button"
                                    class="rounded-md p-2 text-slate-500 transition hover:bg-white hover:text-slate-700"
                                    :disabled="form.processing"
                                    :aria-label="t.common.cancel"
                                    @click="clearFile"
                                >
                                    <i class="fa-solid fa-xmark" />
                                </button>
                            </div>

                            <button
                                v-else
                                type="button"
                                class="flex w-full flex-col items-center justify-center gap-1 rounded-md border-2 border-dashed px-4 py-8 text-center transition"
                                :class="dragging ? 'border-primary bg-red-50' : 'border-slate-300 hover:border-primary hover:bg-slate-50'"
                                @click="fileInput?.click()"
                                @dragover.prevent="dragging = true"
                                @dragleave.prevent="dragging = false"
                                @drop.prevent="onDrop"
                            >
                                <i class="fa-solid fa-cloud-arrow-up text-3xl text-slate-400" />
                                <span class="text-sm font-medium text-slate-700">{{ t.importCenter.dropHint }}</span>
                                <span class="text-xs text-slate-400">.xlsx, .xls</span>
                            </button>

                            <p
                                v-if="form.errors.file"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.file }}
                            </p>

                            <div class="mt-4 flex justify-end">
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md bg-primary px-5 py-2 text-sm font-medium text-white transition disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!form.file || !canImport || form.processing"
                                    @click="submit"
                                >
                                    <i
                                        :class="form.processing ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-file-import'"
                                        class="mr-2"
                                    />
                                    {{ form.processing ? t.importCenter.importing : t.importCenter.importButton }}
                                </button>
                            </div>
                        </div>
                    </li>
                </ol>
            </section>

            <!-- History -->
            <section class="rounded-lg border border-border bg-white">
                <h2 class="border-b border-border px-5 py-4 font-semibold">
                    {{ t.importCenter.history }}
                </h2>

                <p
                    v-if="!history.data.length"
                    class="px-5 py-8 text-center text-sm text-slate-500"
                >
                    {{ t.importCenter.noHistory }}
                </p>

                <div
                    v-else
                    class="overflow-x-auto"
                >
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-xs uppercase text-slate-500">
                                <th class="whitespace-nowrap px-5 py-3">{{ t.importCenter.columnDate }}</th>
                                <th class="px-3 py-3">{{ t.importCenter.columnFile }}</th>
                                <th class="whitespace-nowrap px-3 py-3">{{ t.importCenter.columnBy }}</th>
                                <th class="px-3 py-3">{{ t.importCenter.columnResult }}</th>
                                <th class="px-5 py-3 text-right">{{ t.common.actions }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="log in history.data"
                                :key="log.id"
                                class="align-top"
                            >
                                <td class="whitespace-nowrap px-5 py-3">
                                    <div>{{ formatDateTime(log.created_at) }}</div>
                                    <div class="text-xs text-slate-500">{{ log.form?.[locale] ?? '-' }}</div>
                                </td>

                                <td class="px-3 py-3">
                                    <a
                                        :href="route('admin.import-center.history.file', log.id)"
                                        class="inline-flex max-w-[14rem] items-center gap-1.5 font-medium text-primary hover:underline"
                                        :title="t.importCenter.downloadFile"
                                    >
                                        <i class="fa-solid fa-file-excel shrink-0 text-green-600" />
                                        <span class="truncate">{{ log.file_name }}</span>
                                    </a>
                                    <div class="text-xs text-slate-500">{{ formatSize(log.file_size) }}</div>
                                </td>

                                <td class="whitespace-nowrap px-3 py-3">{{ log.user_name ?? '-' }}</td>

                                <td class="px-3 py-3">
                                    <span
                                        class="inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="STATUS_STYLES[log.status].badge"
                                    >
                                        {{ t.importCenter.status[log.status] }}
                                    </span>

                                    <div
                                        v-if="log.total"
                                        class="mt-1 text-xs text-slate-500"
                                    >
                                        <span class="text-green-700">{{ log.imported }} {{ t.importCenter.resultImported.toLowerCase() }}</span>
                                        · {{ log.already_signed + log.not_signed }} {{ t.importCenter.resultSkipped }}
                                        <template v-if="log.failed">
                                            · <span class="text-red-600">{{ log.failed }} {{ t.importCenter.resultFailed.toLowerCase() }}</span>
                                        </template>
                                    </div>

                                    <div
                                        v-else-if="log.message"
                                        class="mt-1 text-xs text-slate-500"
                                    >
                                        {{ log.message }}
                                    </div>
                                </td>

                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    <a
                                        v-if="log.has_report"
                                        :href="route('admin.import-center.history.report', log.id)"
                                        class="inline-flex items-center rounded-md border border-border px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                                    >
                                        <i class="fa-solid fa-list-check mr-1.5" />
                                        {{ t.importCenter.resultReport }}
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="history.total > 10"
                    class="border-t border-border px-5 py-3"
                >
                    <Pagination
                        :links="history.links"
                        :per-page="history.per_page"
                        :total="history.total"
                        :from="history.from"
                        :to="history.to"
                        @update:per-page="changePerPage"
                    />
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
