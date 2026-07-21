<script setup lang="ts">
import { ref, watch } from 'vue'
import Modal from '@/Components/UI/Modal.vue'
import { route } from 'ziggy-js'
import { useLocale } from '@/Composables/useLocale'

const { t } = useLocale()

const props = defineProps<{
    show: boolean
}>()

const emit = defineEmits<{
    close: []
    upload: [file: File]
}>()

const file = ref<File | null>(null)
const uploading = ref(false)

watch(
    () => props.show,
    (show) => {
        if (!show) {
            file.value = null
            uploading.value = false
        }
    }
)

function handleFileChange(event: Event) {

    const target = event.target as HTMLInputElement

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

function upload() {

    if (!file.value) {
        return
    }

    uploading.value = true

    emit(
        'upload',
        file.value,
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

        <div
            class="flex items-center justify-between border-b border-border px-6 py-4"
        >
            <h2 class="text-lg font-bold">
                {{ t.uploadModal.title }}
            </h2>

            <button
                type="button"
                class="rounded-md p-2 transition hover:bg-slate-100"
                @click="emit('close')"
            >
                <i class="fa-solid fa-xmark" />
            </button>
        </div>

        <!-- Body -->

        <div class="space-y-6 p-6">

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
                    type="file"
                    accept=".xlsx,.xls"
                    class="w-full rounded-md border border-border p-2"
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
                {{ t.common.cancel }}
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