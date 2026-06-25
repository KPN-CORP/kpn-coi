<script setup lang="ts">
import { ref } from 'vue'
import Modal from '@/Components/UI/Modal.vue'

defineProps<{
    show: boolean
}>()

const emit = defineEmits<{
    close: []
    upload: [file: File | null]
}>()

const file = ref<File | null>(null)

function handleFileChange(event: Event) {
    const target = event.target as HTMLInputElement

    if (target.files?.length) {
        file.value = target.files[0]
    }
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
                Upload Users
            </h2>

            <button
                type="button"
                class="rounded-md p-2 hover:bg-slate-100"
                @click="emit('close')"
            >
                <i class="fa-solid fa-xmark" />
            </button>
        </div>

        <!-- Body -->

        <div class="p-6">
            <div
                class="mb-6 rounded-md border border-blue-200 bg-blue-50 p-4"
            >
                <div class="font-medium">
                    Excel Template
                </div>

                <div class="mt-1 text-sm text-slate-600">
                    Download the template before uploading.
                </div>

                <button
                    type="button"
                    class="mt-3 rounded-md border border-blue-500 px-4 py-2 text-sm text-blue-600"
                >
                    Download Template
                </button>
            </div>

            <div>
                <label
                    class="mb-2 block text-sm font-medium"
                >
                    Upload File
                </label>

                <input
                    type="file"
                    accept=".xlsx,.xls,.csv"
                    class="w-full rounded-md border border-border p-2"
                    @change="handleFileChange"
                >
            </div>

            <div
                v-if="file"
                class="mt-3 text-sm text-slate-600"
            >
                Selected:
                <strong>{{ file.name }}</strong>
            </div>
        </div>

        <!-- Footer -->

        <div
            class="flex justify-end gap-2 border-t border-border px-6 py-4"
        >
            <button
                type="button"
                class="rounded-md border border-slate-300 px-4 py-2"
                @click="emit('close')"
            >
                Cancel
            </button>

            <button
                type="button"
                class="rounded-md bg-primary px-4 py-2 text-white"
                @click="emit('upload', file)"
            >
                Upload
            </button>
        </div>
    </Modal>
</template>