<script setup lang="ts">
import Modal from '@/Components/UI/Modal.vue'

interface Role {
    id: number
    name: string
}

defineProps<{
    show: boolean
    role: Role | null
}>()

const emit = defineEmits<{
    close: []
    confirm: [id: number]
}>()
</script>

<template>
    <Modal
        :show="show"
        maxWidth="max-w-md"
        @close="emit('close')"
    >
        <!-- Header -->

        <div
            class="flex items-center justify-between border-b border-border px-6 py-4"
        >
            <h2 class="text-lg font-bold">
                Delete Role
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
            <div class="mb-4 flex justify-center">
                <div
                    class="flex h-16 w-16 items-center justify-center rounded-full bg-red-100"
                >
                    <i
                        class="fa-solid fa-trash text-xl text-red-600"
                    />
                </div>
            </div>

            <p class="text-center text-sm text-slate-600">
                Are you sure you want to delete role
                <span class="font-semibold text-slate-900">
                    "{{ role?.name }}"
                </span>?
            </p>

            <p
                class="mt-2 text-center text-xs text-slate-500"
            >
                This action cannot be undone.
            </p>
        </div>

        <!-- Footer -->

        <div
            class="flex justify-end gap-2 border-t border-border px-6 py-4"
        >
            <button
                type="button"
                class="btn btn-outline-secondary"
                @click="emit('close')"
            >
                Cancel
            </button>

            <button
                v-if="role"
                type="button"
                class="btn btn-primary-custom"
                @click="emit('confirm', role.id)"
            >
                Delete
            </button>
        </div>
    </Modal>
</template>