<script setup lang="ts">
import Modal from '@/Components/UI/Modal.vue'
import { useLocale } from '@/Composables/useLocale'

const { t } = useLocale()

defineProps<{
    show: boolean
    userName?: string
}>()

const emit = defineEmits<{
    close: []
    delete: []
}>()
</script>

<template>
    <Modal
        :show="show"
        maxWidth="max-w-md"
        @close="emit('close')"
    >
        <div class="p-6">
            <h3 class="text-lg font-semibold">
                {{ t.credentials.deleteUserTitle }}
            </h3>

            <p class="mt-2 text-sm text-slate-600">
                {{ t.credentials.deleteUserConfirm }}
                <strong>{{ userName }}</strong>?
            </p>

            <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-md border border-border px-4 py-2"
                    @click="emit('close')"
                >
                    {{ t.common.cancel }}
                </button>

                <button
                    type="button"
                    class="rounded-md bg-red-600 px-4 py-2 text-white"
                    @click="emit('delete')"
                >
                    {{ t.common.delete }}
                </button>
            </div>
        </div>
    </Modal>
</template>