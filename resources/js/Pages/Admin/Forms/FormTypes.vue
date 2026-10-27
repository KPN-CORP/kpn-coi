<script setup lang="ts">
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import Modal from '@/Components/UI/Modal.vue'
import FlashMessage from '@/Components/UI/FlashMessage.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useLocale } from '@/Composables/useLocale'
import { usePermission } from '@/Composables/usePermission'
import type { Flash } from '@/Config/inertia'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Swal from 'sweetalert2'

interface FormTypeRow {
    id: number
    code: string
    name_en: string
    name_id: string
    description_en: string | null
    description_id: string | null
    icon: string
    is_active: boolean
    sort_order: number
    templates_count: number
    declarations_count: number
}

const props = defineProps<{
    formTypes: FormTypeRow[]
    icons: string[]
    // Conflict of Interest's place in the employee menu, among the form types.
    coiSortOrder: number
}>()

const { t, locale } = useLocale()
const { can } = usePermission()
const page = usePage<{ flash: Flash }>()

const editing = ref<FormTypeRow | null>(null)
const showModal = ref(false)

const BLANK = {
    name_en: '',
    name_id: '',
    description_en: '',
    description_id: '',
    icon: 'fa-solid fa-file-signature',
    is_active: true,
}

const form = useForm({ ...BLANK })


// Conflict of Interest is not a form type, but shares their menu order: it is
// listed here (read-only apart from its position) so the menu can be arranged
// in one place. The list is in menu order -- equal orders (from before the
// renumbering) keep COI first, then by name, as useNavigation does.
type ListRow = { coi: true, sort_order: number } | (FormTypeRow & { coi?: false })

const rows = computed<ListRow[]>(() => [
    { coi: true as const, sort_order: props.coiSortOrder },
    ...props.formTypes,
].sort((a, b) => a.sort_order - b.sort_order
    || Number(!a.coi) - Number(!b.coi)
    || (a.coi || b.coi ? 0 : a.name_en.localeCompare(b.name_en))))

const rowKey = (row: ListRow) => (row.coi ? 'coi' : String(row.id))

const reordering = ref(false)

// Swap a row with its neighbour and save the whole list; the server numbers
// it 1..n, so positions can never collide.
function move(index: number, step: -1 | 1) {
    const target = index + step

    if (reordering.value || target < 0 || target >= rows.value.length) {
        return
    }

    const order = rows.value.map(rowKey)
    ;[order[index], order[target]] = [order[target], order[index]]

    reordering.value = true

    router.put(route('admin.master.form-types.order'), { order }, {
        preserveScroll: true,
        onFinish: () => {
            reordering.value = false
        },
    })
}

function openCreate() {
    editing.value = null
    // Not form.reset(): after a successful save Inertia makes the submitted
    // values the new defaults, so "New" would open with the last type saved.
    Object.assign(form, BLANK)
    form.clearErrors()
    showModal.value = true
}

function openEdit(type: FormTypeRow) {
    editing.value = type
    form.clearErrors()
    form.name_en = type.name_en
    form.name_id = type.name_id
    form.description_en = type.description_en ?? ''
    form.description_id = type.description_id ?? ''
    form.icon = type.icon
    form.is_active = type.is_active
    showModal.value = true
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false
        },
    }

    if (editing.value) {
        form.put(route('admin.master.form-types.update', editing.value.id), options)
    } else {
        form.post(route('admin.master.form-types.store'), options)
    }
}

async function destroy(type: FormTypeRow) {
    const { isConfirmed } = await Swal.fire({
        icon: 'warning',
        title: t.value.forms.deleteFormTypeTitle,
        text: t.value.forms.deleteFormTypeText,
        showCancelButton: true,
        confirmButtonText: t.value.common.delete,
        cancelButtonText: t.value.common.cancel,
        confirmButtonColor: '#ab2f2b',
        reverseButtons: true,
    })

    if (isConfirmed) {
        router.delete(route('admin.master.form-types.destroy', type.id), { preserveScroll: true })
    }
}
</script>

<template>
    <Head :title="t.forms.formTypesTitle" />

    <AdminLayout>
        <PageHeader
            :title="t.forms.formTypesTitle"
            :description="t.forms.formTypesDescription"
        >
            <template #actions>
                <button
                    v-if="can('form_master.create')"
                    type="button"
                    class="btn-primary-custom"
                    @click="openCreate"
                >
                    <i class="fa-solid fa-plus" />
                    {{ t.forms.newFormType }}
                </button>
            </template>
        </PageHeader>

        <FlashMessage
            type="success"
            :message="page.props.flash?.success"
        />

        <FlashMessage
            type="error"
            :message="page.props.flash?.error"
        />

        <Card>
            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th class="py-3 text-left">{{ t.forms.columnTypeName }}</th>
                            <th class="py-3">{{ t.forms.code }}</th>
                            <th class="py-3">{{ t.forms.sortOrder }}</th>
                            <th class="py-3">{{ t.common.status }}</th>
                            <th class="py-3">{{ t.forms.columnTemplates }}</th>
                            <th class="py-3">{{ t.forms.columnSignatures }}</th>
                            <th class="py-3">{{ t.common.action }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        <template
                            v-for="(type, index) in rows"
                            :key="rowKey(type)"
                        >
                        <!-- Conflict of Interest: built in, only its order can change -->
                        <tr
                            v-if="type.coi"
                            class="border-b border-slate-100 bg-slate-50/60 text-center"
                        >
                            <td class="py-4 text-left">
                                <div class="flex items-center gap-3">
                                    <i class="fa-solid fa-scale-balanced w-5 text-center text-primary" />
                                    <div>
                                        <div class="flex items-center gap-2 font-medium text-slate-800">
                                            {{ t.nav.conflictOfInterest }}
                                            <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-500">
                                                {{ t.forms.builtIn }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            {{ t.forms.coiBuiltInHint }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 font-mono text-xs text-slate-400">coi</td>
                            <td class="py-4">
                                <div class="flex items-center justify-center gap-1">
                                    <span class="w-6 font-semibold">{{ index + 1 }}</span>
                                    <template v-if="can('form_master.update')">
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary btn-sm px-2"
                                            :disabled="index === 0 || reordering"
                                            :aria-label="t.forms.moveUp"
                                            :title="t.forms.moveUp"
                                            @click="move(index, -1)"
                                        >
                                            <i class="fa-solid fa-arrow-up" />
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary btn-sm px-2"
                                            :disabled="index === rows.length - 1 || reordering"
                                            :aria-label="t.forms.moveDown"
                                            :title="t.forms.moveDown"
                                            @click="move(index, 1)"
                                        >
                                            <i class="fa-solid fa-arrow-down" />
                                        </button>
                                    </template>
                                </div>
                            </td>
                            <td class="py-4">
                                <span class="inline-flex rounded-full border border-green-200 bg-green-50 px-2.5 py-0.5 text-xs font-semibold text-green-700">
                                    {{ t.forms.active }}
                                </span>
                            </td>
                            <td class="py-4 text-slate-400">-</td>
                            <td class="py-4 text-slate-400">-</td>
                            <td class="py-4 text-slate-400">-</td>
                        </tr>

                        <tr
                            v-else
                            class="border-b border-slate-100 text-center"
                        >
                            <td class="py-4 text-left">
                                <div class="flex items-center gap-3">
                                    <i
                                        :class="type.icon"
                                        class="w-5 text-center text-primary"
                                    />
                                    <div>
                                        <div class="font-medium text-slate-800">
                                            {{ locale === 'id' ? type.name_id : type.name_en }}
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            {{ locale === 'id' ? type.name_en : type.name_id }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 font-mono text-xs text-slate-600">{{ type.code }}</td>
                            <td class="py-4">
                                <div class="flex items-center justify-center gap-1">
                                    <span class="w-6 font-semibold">{{ index + 1 }}</span>
                                    <template v-if="can('form_master.update')">
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary btn-sm px-2"
                                            :disabled="index === 0 || reordering"
                                            :aria-label="t.forms.moveUp"
                                            :title="t.forms.moveUp"
                                            @click="move(index, -1)"
                                        >
                                            <i class="fa-solid fa-arrow-up" />
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary btn-sm px-2"
                                            :disabled="index === rows.length - 1 || reordering"
                                            :aria-label="t.forms.moveDown"
                                            :title="t.forms.moveDown"
                                            @click="move(index, 1)"
                                        >
                                            <i class="fa-solid fa-arrow-down" />
                                        </button>
                                    </template>
                                </div>
                            </td>
                            <td class="py-4">
                                <span
                                    class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                                    :class="type.is_active
                                        ? 'bg-green-50 text-green-700 border-green-200'
                                        : 'bg-slate-50 text-slate-600 border-slate-200'"
                                >
                                    {{ type.is_active ? t.forms.active : t.forms.inactive }}
                                </span>
                            </td>
                            <td class="py-4">
                                <Link
                                    :href="route('admin.master.templates.index', { form_type: type.id })"
                                    class="text-primary hover:underline"
                                >
                                    {{ type.templates_count }}
                                </Link>
                            </td>
                            <td class="py-4">{{ type.declarations_count }}</td>
                            <td class="py-4">
                                <div class="flex justify-center gap-2">
                                    <button
                                        v-if="can('form_master.update')"
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm"
                                        @click="openEdit(type)"
                                    >
                                        <i class="fa-solid fa-pen" />
                                        {{ t.common.edit }}
                                    </button>

                                    <button
                                        v-if="can('form_master.delete') && type.templates_count === 0"
                                        type="button"
                                        class="btn btn-outline-danger btn-sm"
                                        :aria-label="t.common.delete"
                                        @click="destroy(type)"
                                    >
                                        <i class="fa-solid fa-trash" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        </template>

                        <tr v-if="!formTypes.length">
                            <td
                                colspan="7"
                                class="py-8 text-center text-slate-500"
                            >
                                {{ t.forms.noFormTypes }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal
            :show="showModal"
            max-width="max-w-2xl"
            @close="showModal = false"
        >
            <form
                class="flex max-h-[90vh] flex-col"
                @submit.prevent="save"
            >
                <div class="border-b border-border px-6 py-4">
                    <h3 class="text-lg font-bold">
                        {{ editing ? t.forms.editFormType : t.forms.newFormType }}
                    </h3>
                </div>

                <div class="grid gap-4 overflow-y-auto px-6 py-5 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-700">{{ t.forms.nameEn }}</label>
                        <input
                            v-model="form.name_en"
                            type="text"
                            maxlength="255"
                            class="rounded-md border border-border px-3 py-2 text-sm"
                        >
                        <p
                            v-if="form.errors.name_en"
                            class="text-xs text-red-600"
                        >
                            {{ form.errors.name_en }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-700">{{ t.forms.nameId }}</label>
                        <input
                            v-model="form.name_id"
                            type="text"
                            maxlength="255"
                            class="rounded-md border border-border px-3 py-2 text-sm"
                        >
                        <p
                            v-if="form.errors.name_id"
                            class="text-xs text-red-600"
                        >
                            {{ form.errors.name_id }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-700">{{ t.forms.descriptionEn }}</label>
                        <textarea
                            v-model="form.description_en"
                            rows="2"
                            maxlength="1000"
                            class="rounded-md border border-border px-3 py-2 text-sm"
                        />
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-700">{{ t.forms.descriptionId }}</label>
                        <textarea
                            v-model="form.description_id"
                            rows="2"
                            maxlength="1000"
                            class="rounded-md border border-border px-3 py-2 text-sm"
                        />
                    </div>

                    <div class="flex flex-col gap-2 sm:col-span-2">
                        <span class="text-sm font-medium text-slate-700">{{ t.forms.icon }}</span>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="icon in icons"
                                :key="icon"
                                type="button"
                                class="flex h-10 w-10 items-center justify-center rounded-md border text-lg"
                                :class="form.icon === icon
                                    ? 'border-primary bg-red-50 text-primary'
                                    : 'border-border text-slate-500 hover:text-primary'"
                                :aria-label="icon"
                                :aria-pressed="form.icon === icon"
                                @click="form.icon = icon"
                            >
                                <i :class="icon" />
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="editing"
                        class="flex flex-col gap-2"
                    >
                        <span class="text-sm font-medium text-slate-700">{{ t.forms.code }}</span>
                        <span class="rounded-md bg-slate-50 px-3 py-2 font-mono text-sm text-slate-600">{{ editing.code }}</span>
                        <p class="text-xs text-slate-500">{{ t.forms.codeHint }}</p>
                    </div>

                    <label class="flex items-start gap-3 sm:col-span-2">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="mt-1"
                        >
                        <span>
                            <span class="text-sm font-medium text-slate-700">{{ t.forms.active }}</span>
                            <span class="block text-xs text-slate-500">{{ t.forms.formTypeActiveHint }}</span>
                        </span>
                    </label>
                </div>

                <div class="flex justify-end gap-3 border-t border-border px-6 py-4">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        @click="showModal = false"
                    >
                        {{ t.common.cancel }}
                    </button>

                    <button
                        type="submit"
                        class="btn-primary-custom"
                        :disabled="form.processing"
                    >
                        <i
                            class="fa-solid"
                            :class="form.processing ? 'fa-circle-notch fa-spin' : 'fa-floppy-disk'"
                        />
                        {{ form.processing ? t.common.saving : t.common.save }}
                    </button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
