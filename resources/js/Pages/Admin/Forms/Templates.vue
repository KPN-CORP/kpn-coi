<script setup lang="ts">
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import FlashMessage from '@/Components/UI/FlashMessage.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useLocale } from '@/Composables/useLocale'
import { usePermission } from '@/Composables/usePermission'
import { formatDateTime } from '@/Utils/date'
import FormTypeSelect from '@/Components/Forms/FormTypeSelect.vue'
import type { FormTypeOption, Localized, Paginated } from '@/Components/Forms/types'
import type { Flash } from '@/Config/inertia'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import debounce from 'lodash/debounce'
import Swal from 'sweetalert2'

interface TemplateRow {
    id: number
    form_type_id: number
    form_type: Localized | null
    period: number
    title_en: string
    title_id: string
    business_units: string[]
    publish_at: string | null
    is_active: boolean
    is_published: boolean
    signed_count: number
    locked: boolean
    can_manage: boolean
}

const props = defineProps<{
    templates: Paginated<TemplateRow>
    filters: { form_type?: string, period?: string, search?: string }
    formTypes: FormTypeOption[]
    periods: number[]
}>()

const { t, locale } = useLocale()
const { can } = usePermission()
const page = usePage<{ flash: Flash }>()

const formType = ref(props.filters.form_type ? Number(props.filters.form_type) : '')
const period = ref(props.filters.period ?? '')
const search = ref(props.filters.search ?? '')

function reload(overrides: Record<string, unknown> = {}) {
    router.get(
        route('admin.master.templates.index'),
        {
            form_type: formType.value || undefined,
            period: period.value || undefined,
            search: search.value || undefined,
            per_page: props.templates.per_page,
            ...overrides,
        },
        { preserveState: true, replace: true },
    )
}

const onSearch = debounce(() => reload({ page: 1 }), 400)

function statusOf(template: TemplateRow): { label: string, classes: string } {
    if (!template.is_active) {
        return { label: t.value.forms.inactive, classes: 'bg-slate-50 text-slate-600 border-slate-200' }
    }

    if (!template.is_published) {
        return { label: t.value.forms.scheduled, classes: 'bg-amber-50 text-amber-700 border-amber-200' }
    }

    return { label: t.value.forms.active, classes: 'bg-green-50 text-green-700 border-green-200' }
}

function toggle(template: TemplateRow) {
    router.post(route('admin.master.templates.toggle', template.id), {}, { preserveScroll: true })
}

function duplicate(template: TemplateRow) {
    router.post(route('admin.master.templates.duplicate', template.id))
}

async function destroy(template: TemplateRow) {
    const { isConfirmed } = await Swal.fire({
        icon: 'warning',
        title: t.value.forms.deleteTitle,
        text: t.value.forms.deleteText,
        showCancelButton: true,
        confirmButtonText: t.value.common.delete,
        cancelButtonText: t.value.common.cancel,
        confirmButtonColor: '#ab2f2b',
        reverseButtons: true,
    })

    if (isConfirmed) {
        router.delete(route('admin.master.templates.destroy', template.id), { preserveScroll: true })
    }
}
</script>

<template>
    <Head :title="t.forms.templatesTitle" />

    <AdminLayout>
        <PageHeader
            :title="t.forms.templatesTitle"
            :description="t.forms.templatesDescription"
        >
            <template #actions>
                <Link
                    v-if="can('form_master.create')"
                    :href="route('admin.master.templates.create', { form_type: formType || undefined })"
                    class="btn-primary-custom"
                >
                    <i class="fa-solid fa-plus" />
                    {{ t.forms.createTemplate }}
                </Link>
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

        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="sm:w-56">
                <FormTypeSelect
                    v-model="formType"
                    :options="formTypes"
                    allow-all
                    @change="reload({ page: 1 })"
                />
            </div>

            <select
                v-model="period"
                class="rounded-md border border-border px-3 py-2 text-sm"
                @change="reload({ page: 1 })"
            >
                <option value="">
                    {{ t.forms.allPeriods }}
                </option>

                <option
                    v-for="item in periods"
                    :key="item"
                    :value="String(item)"
                >
                    {{ item }}
                </option>
            </select>

            <input
                v-model="search"
                type="search"
                class="w-full rounded-md border border-border px-3 py-2 text-sm sm:max-w-xs"
                :placeholder="t.forms.searchTemplates"
                @input="onSearch"
            >
        </div>

        <Card>
            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th class="py-3">{{ t.common.period }}</th>
                            <th class="py-3 text-left">{{ t.forms.columnTitle }}</th>
                            <th class="py-3">{{ t.forms.formType }}</th>
                            <th class="py-3">{{ t.forms.columnBusinessUnit }}</th>
                            <th class="py-3">{{ t.forms.columnPublishAt }}</th>
                            <th class="py-3">{{ t.forms.columnStatus }}</th>
                            <th class="py-3">{{ t.forms.columnSigned }}</th>
                            <th class="py-3">{{ t.common.action }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="template in templates.data"
                            :key="template.id"
                            class="border-b border-slate-100 text-center"
                        >
                            <td class="py-4 font-semibold">{{ template.period }}</td>

                            <td class="py-4 text-left">
                                <div class="font-medium text-slate-800">
                                    {{ locale === 'id' ? template.title_id : template.title_en }}
                                </div>
                                <div class="text-xs text-slate-400">
                                    {{ locale === 'id' ? template.title_en : template.title_id }}
                                </div>
                            </td>

                            <td class="py-4">
                                <span class="whitespace-nowrap rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                    {{ template.form_type?.[locale] ?? '-' }}
                                </span>
                            </td>

                            <td class="py-4">
                                <div class="flex flex-wrap justify-center gap-1">
                                    <span
                                        v-for="unit in template.business_units"
                                        :key="unit"
                                        class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-700"
                                    >
                                        {{ unit }}
                                    </span>

                                    <span
                                        v-if="!template.business_units.length"
                                        class="rounded bg-red-50 px-2 py-0.5 text-xs font-medium text-primary"
                                    >
                                        {{ t.forms.allBusinessUnits }}
                                    </span>
                                </div>
                            </td>

                            <td class="py-4 whitespace-nowrap">{{ formatDateTime(template.publish_at) }}</td>

                            <td class="py-4">
                                <span
                                    class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                                    :class="statusOf(template).classes"
                                >
                                    {{ statusOf(template).label }}
                                </span>
                            </td>

                            <td class="py-4">
                                <span class="inline-flex items-center gap-1.5">
                                    {{ template.signed_count }}
                                    <i
                                        v-if="template.locked"
                                        class="fa-solid fa-lock text-xs text-slate-400"
                                        :title="t.forms.locked"
                                    />
                                </span>
                            </td>

                            <td class="py-4">
                                <div class="flex flex-wrap justify-center gap-2">
                                    <Link
                                        :href="route('admin.master.templates.edit', template.id)"
                                        class="btn btn-outline-secondary btn-sm"
                                    >
                                        <i
                                            class="fa-solid"
                                            :class="template.locked || !template.can_manage ? 'fa-eye' : 'fa-pen'"
                                        />
                                        {{ template.locked || !template.can_manage ? t.common.view : t.common.edit }}
                                    </Link>

                                    <template v-if="template.can_manage">
                                        <button
                                            v-if="can('form_master.update')"
                                            type="button"
                                            class="btn btn-sm"
                                            :class="template.is_active ? 'btn-outline-secondary' : 'btn-outline-primary-custom'"
                                            @click="toggle(template)"
                                        >
                                            <i
                                                class="fa-solid"
                                                :class="template.is_active ? 'fa-toggle-off' : 'fa-toggle-on'"
                                            />
                                            {{ template.is_active ? t.forms.deactivate : t.forms.activate }}
                                        </button>

                                        <button
                                            v-if="can('form_master.create')"
                                            type="button"
                                            class="btn btn-outline-secondary btn-sm"
                                            @click="duplicate(template)"
                                        >
                                            <i class="fa-solid fa-copy" />
                                            {{ t.forms.duplicate }}
                                        </button>

                                        <button
                                            v-if="can('form_master.delete') && !template.locked"
                                            type="button"
                                            class="btn btn-outline-danger btn-sm"
                                            @click="destroy(template)"
                                        >
                                            <i class="fa-solid fa-trash" />
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!templates.data.length">
                            <td
                                colspan="8"
                                class="py-8 text-center text-slate-500"
                            >
                                {{ t.forms.noTemplates }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination
                :links="templates.links"
                :per-page="templates.per_page"
                :total="templates.total"
                :from="templates.from"
                :to="templates.to"
                @update:per-page="value => reload({ per_page: value, page: 1 })"
            />
        </Card>
    </AdminLayout>
</template>
