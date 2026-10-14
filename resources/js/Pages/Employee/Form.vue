<script setup lang="ts">
import PageHeader from '@/Components/UI/PageHeader.vue'
import Modal from '@/Components/UI/Modal.vue'
import FlashMessage from '@/Components/UI/FlashMessage.vue'
import EmployeeLayout from '@/Layouts/EmployeeLayout.vue'
import FormDocument from '@/Components/Forms/FormDocument.vue'
import type { Localized, RenderedForm } from '@/Components/Forms/types'
import { formatDate, formatDateTime } from '@/Utils/date'
import { useLocale } from '@/Composables/useLocale'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import type { Flash } from '@/Config/inertia'

interface PendingItem {
    id: number
    period: number
    publish_at: string | null
    title: Localized
    draft_saved_at: string | null
}

interface SignedForm extends RenderedForm {
    id: number
    period: number
    signed_name: string
    signed_at: string | null
    locale: 'en' | 'id'
}

defineProps<{
    formType: {
        id: number
        code: string
        name: Localized
        description: { en: string | null, id: string | null }
        icon: string
    }
    pending: PendingItem[]
    signed: SignedForm[]
}>()

const { t, locale } = useLocale()

const page = usePage<{ flash: Flash }>()

const viewing = ref<SignedForm | null>(null)

function downloadPdf(id: number, language: 'en' | 'id') {
    window.open(
        route('employee.forms.pdf', { declaration: id, locale: language }),
        '_blank',
    )
}
</script>

<template>
    <Head :title="formType.name[locale]" />

    <EmployeeLayout>
        <PageHeader
            :title="formType.name[locale]"
            :description="formType.description[locale] ?? t.forms.description"
        />

        <FlashMessage
            type="success"
            :message="page.props.flash?.success"
        />

        <FlashMessage
            type="error"
            :message="page.props.flash?.error"
        />

        <!-- Waiting for the signature -->
        <section class="mb-8">
            <h2 class="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-slate-500">
                {{ t.forms.pendingTitle }}
                <span
                    v-if="pending.length"
                    class="rounded-full bg-primary px-2 py-0.5 text-xs text-white"
                >{{ pending.length }}</span>
            </h2>

            <div
                v-if="pending.length"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="item in pending"
                    :key="item.id"
                    class="flex flex-col rounded-lg border border-border bg-white p-5 shadow-sm transition hover:shadow-md"
                >
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-red-50 text-lg text-primary">
                            <i :class="formType.icon" />
                        </span>

                        <span
                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="item.draft_saved_at ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600'"
                        >
                            <i
                                class="fa-solid"
                                :class="item.draft_saved_at ? 'fa-floppy-disk' : 'fa-circle'"
                                :style="item.draft_saved_at ? undefined : { fontSize: '6px' }"
                            />
                            {{ item.draft_saved_at ? t.forms.statusDraft : t.forms.statusNotStarted }}
                        </span>
                    </div>

                    <h3 class="font-bold text-slate-800">{{ item.title[locale] }}</h3>

                    <p class="mt-1 text-sm text-slate-500">
                        {{ t.common.period }} {{ item.period }}
                        <template v-if="item.publish_at">&middot; {{ t.forms.publishedAt }} {{ formatDate(item.publish_at.slice(0, 10)) }}</template>
                    </p>

                    <p
                        v-if="item.draft_saved_at"
                        class="mt-2 text-xs text-amber-700"
                    >
                        {{ t.forms.draftSavedAt }} {{ formatDateTime(item.draft_saved_at) }}
                    </p>

                    <div class="mt-auto pt-5">
                        <Link
                            :href="route('employee.forms.read', { formType: formType.code, template: item.id })"
                            class="btn-primary-custom w-full justify-center"
                        >
                            <i class="fa-solid fa-book-open" />
                            {{ item.draft_saved_at ? t.forms.continueSigning : t.forms.openToRead }}
                        </Link>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="flex flex-col items-center gap-2 rounded-lg border border-dashed border-green-300 bg-green-50 px-6 py-8 text-center"
            >
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 text-xl text-green-600">
                    <i class="fa-solid fa-check" />
                </span>
                <p class="font-semibold text-green-800">{{ t.forms.allDone }}</p>
                <p class="text-sm text-green-700">{{ t.forms.noPending }}</p>
            </div>
        </section>

        <!-- Signed -->
        <section>
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">
                {{ t.forms.signedTitle }}
            </h2>

            <div
                v-if="signed.length"
                class="divide-y divide-slate-100 overflow-hidden rounded-lg border border-border bg-white shadow-sm"
            >
                <div
                    v-for="item in signed"
                    :key="item.id"
                    class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-50 text-green-600">
                            <i class="fa-solid fa-file-signature" />
                        </span>
                        <div>
                            <div class="font-semibold text-slate-800">
                                {{ item.title[locale] ?? '-' }}
                                <span class="ml-1 text-sm font-normal text-slate-400">&middot; {{ item.period }}</span>
                            </div>
                            <div class="text-sm text-slate-500">
                                {{ t.forms.signedName }} <span class="font-medium text-slate-700">{{ item.signed_name }}</span>
                                &middot; {{ formatDateTime(item.signed_at) }}
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2 sm:shrink-0">
                        <button
                            type="button"
                            class="btn btn-outline-secondary btn-sm"
                            @click="viewing = item"
                        >
                            <i class="fa-solid fa-eye" />
                            {{ t.common.view }}
                        </button>

                        <button
                            type="button"
                            class="btn btn-outline-primary-custom btn-sm"
                            :title="`PDF ${t.forms.indonesian}`"
                            @click="downloadPdf(item.id, 'id')"
                        >
                            <i class="fa-solid fa-file-pdf" />
                            ID
                        </button>

                        <button
                            type="button"
                            class="btn btn-outline-primary-custom btn-sm"
                            :title="`PDF ${t.forms.english}`"
                            @click="downloadPdf(item.id, 'en')"
                        >
                            <i class="fa-solid fa-file-pdf" />
                            EN
                        </button>
                    </div>
                </div>
            </div>

            <p
                v-else
                class="rounded-lg border border-dashed border-border bg-white px-5 py-6 text-center text-sm text-slate-500"
            >
                {{ t.forms.noSigned }}
            </p>
        </section>

        <Modal
            :show="viewing !== null"
            max-width="max-w-4xl"
            @close="viewing = null"
        >
            <template v-if="viewing">
                <div class="flex items-center justify-between border-b border-border px-6 py-4">
                    <h3 class="text-lg font-bold">
                        {{ viewing.title[locale] }}
                    </h3>

                    <button
                        type="button"
                        class="text-slate-400 hover:text-primary"
                        :aria-label="t.common.close"
                        @click="viewing = null"
                    >
                        <i class="fa-solid fa-xmark text-lg" />
                    </button>
                </div>

                <div class="overflow-y-auto">
                    <FormDocument
                        :page="viewing.page"
                        :content="viewing.content[locale]"
                        :header="viewing.header[locale]"
                        :footer="viewing.footer[locale]"
                    >
                        <div class="mt-6 w-fit rounded-md border border-primary px-4 py-3 text-sm text-primary">
                            <div class="text-xs">
                                {{ t.forms.electronicSignature }} &middot; {{ formatDateTime(viewing.signed_at) }}
                            </div>
                            <div class="mt-1 font-bold">
                                {{ viewing.signed_name }}
                            </div>
                        </div>
                    </FormDocument>
                </div>
            </template>
        </Modal>
    </EmployeeLayout>
</template>
