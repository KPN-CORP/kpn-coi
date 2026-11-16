<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import Modal from '@/Components/UI/Modal.vue'
import Pill, { type PillTone } from '@/Components/UI/Pill.vue'
import { route } from 'ziggy-js'
import { useLocale } from '@/Composables/useLocale'
import { csrfToken } from '@/Utils/http'

const { t } = useLocale()

const props = defineProps<{
    show: boolean
}>()

const emit = defineEmits<{
    close: []
    // At least one row was created or updated: the list behind the dialog
    // should refresh.
    imported: []
}>()

type RowStatus = 'created' | 'updated' | 'unchanged'

interface RowResult {
    row: number
    name: string
    email: string
    status: RowStatus
    changes: string[]
}

interface RowError {
    row: number
    name: string
    email: string
    error: string
}

const file = ref<File | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)

// The outcome of the last upload, shown in the dialog. Cleared when a new
// file is picked so a corrected file can go straight back up.
const results = ref<RowResult[] | null>(null)
const errors = ref<RowError[] | null>(null)
const message = ref('')

const summary = computed(() => ({
    created: results.value?.filter(row => row.status === 'created').length ?? 0,
    updated: results.value?.filter(row => row.status === 'updated').length ?? 0,
    unchanged: results.value?.filter(row => row.status === 'unchanged').length ?? 0,
}))

const STATUS_TONE: Record<RowStatus, PillTone> = {
    created: 'success',
    updated: 'warning',
    unchanged: 'neutral',
}

function reset() {
    file.value = null
    uploading.value = false
    results.value = null
    errors.value = null
    message.value = ''

    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

watch(
    () => props.show,
    (show) => {
        if (!show) {
            reset()
        }
    }
)

function handleFileChange(event: Event) {

    const target = event.target as HTMLInputElement

    results.value = null
    errors.value = null
    message.value = ''

    if (!target.files?.length) {
        file.value = null
        return
    }

    const selected = target.files[0]

    const extension = selected.name
        .split('.')
        .pop()
        ?.toLowerCase()

    if (!['xlsx', 'xls'].includes(extension ?? '')) {

        alert(t.value.uploadModal.onlyExcel)

        target.value = ''

        file.value = null

        return
    }

    file.value = selected
}

async function upload() {

    if (!file.value || uploading.value) {
        return
    }

    uploading.value = true
    results.value = null
    errors.value = null
    message.value = ''

    const body = new FormData()
    body.append('file', file.value)

    try {
        const response = await fetch(route('admin.credentials.import'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
            },
            body,
        })

        const data = await response.json().catch(() => ({}))

        if (response.ok) {
            results.value = data.results ?? []

            if (summary.value.created || summary.value.updated) {
                emit('imported')
            }
        } else {
            errors.value = data.errors ?? []
            message.value = data.errors
                ? t.value.uploadModal.failedRows.replace(':count', String(data.errors.length))
                : (data.message ?? t.value.credentials.importFailedTitle)
        }
    } catch {
        errors.value = []
        message.value = t.value.credentials.importFailedTitle
    } finally {
        uploading.value = false

        // Let the same file name be picked again after it was fixed.
        file.value = null

        if (fileInput.value) {
            fileInput.value.value = ''
        }
    }
}
</script>

<template>
    <Modal
        :show="show"
        maxWidth="max-w-3xl"
        :title="t.uploadModal.title"
        @close="emit('close')"
    >
        <!-- Body -->

        <div class="max-h-[70vh] space-y-6 overflow-y-auto p-6">

            <!-- Template -->

            <div
                class="rounded-lg border border-blue-200 bg-blue-50 p-4"
            >
                <div class="font-semibold text-slate-800">
                    {{ t.uploadModal.excelTemplate }}
                </div>

                <p class="mt-1 text-sm text-slate-600">
                    {{ t.uploadModal.templateHint }}
                </p>

                <p class="mt-1 text-sm text-slate-600">
                    {{ t.uploadModal.updateHint }}
                </p>

                <a
                    :href="route('admin.credentials.template')"
                    class="mt-4 inline-flex items-center rounded-md border border-blue-500 px-4 py-2 text-sm font-medium text-blue-600 transition hover:bg-blue-100"
                >
                    <i class="fa-solid fa-download mr-2" />

                    {{ t.uploadModal.downloadTemplate }}
                </a>
            </div>

            <!-- Upload -->

            <div>
                <label class="mb-2 block text-sm font-medium">
                    {{ t.uploadModal.excelFile }}
                </label>

                <input
                    ref="fileInput"
                    type="file"
                    accept=".xlsx,.xls"
                    class="w-full rounded-md border border-border p-2"
                    :disabled="uploading"
                    @change="handleFileChange"
                >

                <p class="mt-2 text-xs text-slate-500">
                    {{ t.uploadModal.supportedFormat }}
                </p>

                <div
                    v-if="file"
                    class="mt-4 rounded-md border border-green-200 bg-green-50 p-3 text-sm"
                >
                    <i class="fa-solid fa-file-excel mr-2 text-green-600" />

                    <strong>{{ file.name }}</strong>
                </div>
            </div>

            <!-- Failed: nothing was imported; fix the rows and upload again. -->

            <div
                v-if="errors"
                class="rounded-lg border border-red-200 bg-red-50 p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="font-semibold text-red-700">
                        <i class="fa-solid fa-circle-exclamation mr-2" />
                        {{ t.credentials.importFailedTitle }}
                    </div>

                    <a
                        v-if="errors.length"
                        :href="route('admin.credentials.import.error')"
                        class="text-sm font-medium text-red-700 underline"
                    >
                        <i class="fa-solid fa-download mr-1" />
                        {{ t.credentials.downloadErrorReport }}
                    </a>
                </div>

                <p class="mt-1 text-sm text-red-700">
                    {{ message }}
                </p>

                <p class="mt-1 text-sm text-red-700">
                    {{ t.uploadModal.reuploadHint }}
                </p>

                <div
                    v-if="errors.length"
                    class="mt-3 overflow-x-auto rounded-md border border-red-200 bg-white"
                >
                    <table class="w-full text-left text-sm">
                        <thead class="bg-red-50 text-xs uppercase text-red-700">
                            <tr>
                                <th class="px-3 py-2">{{ t.uploadModal.columnRow }}</th>
                                <th class="px-3 py-2">{{ t.uploadModal.columnUser }}</th>
                                <th class="px-3 py-2">{{ t.uploadModal.columnError }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in errors"
                                :key="item.row"
                                class="border-t border-red-100"
                            >
                                <td class="px-3 py-2 align-top">{{ item.row }}</td>
                                <td class="px-3 py-2 align-top">
                                    <div class="font-medium">{{ item.name || '-' }}</div>
                                    <div class="text-xs text-slate-500">{{ item.email }}</div>
                                </td>
                                <td class="px-3 py-2 align-top text-red-700">{{ item.error }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Completed: per-row outcome. -->

            <div
                v-if="results"
                class="rounded-lg border border-green-200 bg-green-50 p-4"
            >
                <div class="font-semibold text-green-700">
                    <i class="fa-solid fa-circle-check mr-2" />
                    {{ t.credentials.importCompletedTitle }}
                </div>

                <div class="mt-2 flex flex-wrap gap-2">
                    <Pill tone="success">{{ t.uploadModal.statusCreated }}: {{ summary.created }}</Pill>
                    <Pill tone="warning">{{ t.uploadModal.statusUpdated }}: {{ summary.updated }}</Pill>
                    <Pill tone="neutral">{{ t.uploadModal.statusUnchanged }}: {{ summary.unchanged }}</Pill>
                </div>

                <div
                    v-if="results.length"
                    class="mt-3 overflow-x-auto rounded-md border border-green-200 bg-white"
                >
                    <table class="w-full text-left text-sm">
                        <thead class="bg-green-50 text-xs uppercase text-green-700">
                            <tr>
                                <th class="px-3 py-2">{{ t.uploadModal.columnRow }}</th>
                                <th class="px-3 py-2">{{ t.uploadModal.columnUser }}</th>
                                <th class="px-3 py-2">{{ t.uploadModal.columnStatus }}</th>
                                <th class="px-3 py-2">{{ t.uploadModal.columnChanges }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in results"
                                :key="item.row"
                                class="border-t border-green-100"
                            >
                                <td class="px-3 py-2 align-top">{{ item.row }}</td>
                                <td class="px-3 py-2 align-top">
                                    <div class="font-medium">{{ item.name }}</div>
                                    <div class="text-xs text-slate-500">{{ item.email }}</div>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <Pill :tone="STATUS_TONE[item.status]">
                                        {{
                                            item.status === 'created'
                                                ? t.uploadModal.statusCreated
                                                : item.status === 'updated'
                                                    ? t.uploadModal.statusUpdated
                                                    : t.uploadModal.statusUnchanged
                                        }}
                                    </Pill>
                                </td>
                                <td class="px-3 py-2 align-top text-slate-600">
                                    <template v-if="item.status === 'updated'">
                                        {{ item.changes.join(', ') }}
                                    </template>
                                    <template v-else-if="item.status === 'unchanged'">
                                        {{ t.uploadModal.noChanges }}
                                    </template>
                                    <template v-else>
                                        {{ t.uploadModal.credentialsSent }}
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Footer -->

        <div
            class="flex justify-end gap-2 border-t border-border px-6 py-4"
        >
            <button
                type="button"
                class="rounded-md border border-slate-300 px-4 py-2 transition hover:bg-slate-50"
                :disabled="uploading"
                @click="emit('close')"
            >
                {{ results ? t.common.close : t.common.cancel }}
            </button>

            <button
                type="button"
                class="rounded-md bg-primary px-4 py-2 text-white transition disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!file || uploading"
                @click="upload"
            >
                <i
                    v-if="uploading"
                    class="fa-solid fa-spinner fa-spin mr-2"
                />

                {{
                    uploading
                        ? t.common.uploading
                        : t.common.upload
                }}
            </button>
        </div>
    </Modal>
</template>
