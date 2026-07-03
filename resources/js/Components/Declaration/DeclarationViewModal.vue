<script setup lang="ts">
import Card from '@/Components/UI/Card.vue'
import Modal from '@/Components/UI/Modal.vue'

defineProps({
    show: {
        type: Boolean,
        required: true,
    },

    declaration: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits<{
    close: []
}>()
</script>

<template>
    <Modal
        :show="show"
        maxWidth="max-w-5xl"
        @close="emit('close')"
    >
        <!-- Header -->

        <div
            class="flex items-center justify-between border-b border-border px-6 py-4"
        >
            <h2 class="text-lg font-bold">
                Submitted Declaration - 
                {{
                    declaration.type === 'employee'
                        ? 'Employee'
                        : 'Non Employee'
                }}
            </h2>

            <button
                type="button"
                class="rounded-md p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                @click="emit('close')"
            >
                <i class="fa-solid fa-xmark text-lg" />
            </button>
        </div>

        <!-- Scrollable Body -->

        <div class="flex-1 overflow-y-auto p-6">
            <!-- Employee Information -->

            <Card class="mb-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label
                            class="mb-1 block text-xs font-semibold"
                        >
                            Full Name
                        </label>

                        <input
                            :value="declaration.employee.name"
                            disabled
                            class="w-full rounded-md border border-border bg-slate-100 px-3 py-2"
                        >
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-xs font-semibold"
                        >
                            Employee ID / Citizenship ID
                        </label>

                        <input
                            :value="declaration.employee.employee_id ?? declaration.employee.citizenship_id"
                            disabled
                            class="w-full rounded-md border border-border bg-slate-100 px-3 py-2"
                        >
                    </div>
                </div>
            </Card>

            <!-- Questions -->

            <div
                v-for="question in declaration.questions"
                :key="question.key"
                class="mb-4 rounded-md border border-border bg-white p-4"
            >
                <div class="mb-3 font-semibold">
                    {{ question.title }}
                </div>

                <span
                    v-if="question.answer"
                    class="rounded-md border border-red-200 bg-red-50 px-2 py-1 text-xs font-semibold text-red-700"
                >
                    Yes
                </span>

                <span
                    v-else
                    class="rounded-md border border-green-200 bg-green-50 px-2 py-1 text-xs font-semibold text-green-700"
                >
                    No
                </span>

                <!-- Details -->

                <div
                    v-if="question.details?.length"
                    class="mt-4 overflow-x-auto"
                >
                    <table
                        class="min-w-full border border-border"
                    >
                        <thead>
                            <tr>
                                <th
                                    v-for="(value, key) in question.details[0]"
                                    :key="key"
                                    class="border border-border bg-slate-100 p-2 text-left text-xs font-semibold uppercase"
                                >
                                    {{
                                        String(key)
                                            .replaceAll('_', ' ')
                                    }}
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="(detail, index) in question.details"
                                :key="index"
                            >
                                <td
                                    v-for="(value, key) in detail"
                                    :key="key"
                                    class="border border-border p-2 text-sm"
                                >
                                    {{ value }}
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
                class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                @click="emit('close')"
            >
                Close
            </button>
        </div>
    </Modal>
</template>