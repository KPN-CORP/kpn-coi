<script setup lang="ts">
import FlashMessage from '@/Components/UI/FlashMessage.vue'
import EmployeeLayout from '@/Layouts/EmployeeLayout.vue'
import FormDocument from '@/Components/Forms/FormDocument.vue'
import SignatureModal from '@/Components/Forms/SignatureModal.vue'
import { useLocale } from '@/Composables/useLocale'
import { formatDateTime } from '@/Utils/date'
import type { RenderedForm } from '@/Components/Forms/types'
import type { Flash } from '@/Config/inertia'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

interface ReadDocument extends RenderedForm {
    id: number
    period: number
    publish_at: string | null
}

const props = defineProps<{
    formType: { code: string, name: { en: string, id: string } }
    document: ReadDocument
    draft: { agreed_at: string | null } | null
    fullName: string
    // The wording being read; the signature is refused if it changes.
    version: string
}>()

const { t, locale } = useLocale()
const page = usePage<{ flash: Flash }>()

// --- Reading progress ----------------------------------------------------------
// The agreement unlocks once the end of the document has been on screen. A
// saved draft means it was already read and agreed to.

const documentEl = ref<HTMLElement | null>(null)
const actionBar = ref<HTMLElement | null>(null)
const endMarker = ref<HTMLElement | null>(null)
const progress = ref(0)
const reachedEnd = ref(props.draft !== null)

function updateProgress() {
    if (!documentEl.value) {
        return
    }

    const box = documentEl.value.getBoundingClientRect()
    // What has been visible above the fixed action bar.
    const seen = window.innerHeight - (actionBar.value?.offsetHeight ?? 0) - box.top
    progress.value = Math.max(0, Math.min(1, seen / box.height))

    if (progress.value >= 0.99) {
        reachedEnd.value = true
    }
}

let observer: IntersectionObserver | null = null

// One layout read per animation frame, however fast the scroll events come.
let frame = 0

function onScroll() {
    cancelAnimationFrame(frame)
    frame = requestAnimationFrame(updateProgress)
}

onMounted(() => {
    updateProgress()
    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', updateProgress)

    // The fixed action bar covers the bottom of the screen; the end only
    // counts as read once it is visible above it.
    const barHeight = actionBar.value?.offsetHeight ?? 0

    observer = new IntersectionObserver((entries) => {
        if (entries.some(entry => entry.isIntersecting)) {
            reachedEnd.value = true
            progress.value = 1
        }
    }, { rootMargin: `0px 0px -${barHeight}px 0px` })

    if (endMarker.value) {
        observer.observe(endMarker.value)
    }
})

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll)
    cancelAnimationFrame(frame)
    window.removeEventListener('resize', updateProgress)
    observer?.disconnect()
})

const percent = computed(() => Math.round((reachedEnd.value ? 1 : progress.value) * 100))

function scrollToEnd() {
    endMarker.value?.scrollIntoView({ behavior: 'smooth', block: 'end' })
}

// --- Agree, draft, submit ------------------------------------------------------

const agreed = ref(props.draft !== null)
const savingDraft = ref(false)
const showSignature = ref(false)

const step = computed(() => (!reachedEnd.value ? 1 : !agreed.value ? 2 : 3))

const steps = computed(() => [
    { n: 1, label: t.value.forms.stepRead },
    { n: 2, label: t.value.forms.stepAgree },
    { n: 3, label: t.value.forms.stepSign },
])

function saveDraft() {
    if (!agreed.value || savingDraft.value) {
        return
    }

    savingDraft.value = true

    router.post(
        route('employee.forms.draft', props.document.id),
        { locale: locale.value, agree: true },
        {
            preserveScroll: true,
            onFinish: () => {
                savingDraft.value = false
            },
        },
    )
}
</script>

<template>
    <Head :title="document.title[locale]" />

    <EmployeeLayout>
        <!-- Reading progress, under the top bar -->
        <div class="sticky top-[var(--topbar-height)] z-20 -mx-4 -mt-4 mb-4 h-1 bg-slate-200 sm:-mx-6 sm:-mt-6">
            <div
                class="h-full bg-primary transition-[width] duration-150"
                :style="{ width: `${percent}%` }"
                role="progressbar"
                :aria-valuenow="percent"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="t.forms.readingProgress"
            />
        </div>

        <Link
            :href="route('employee.forms.show', { formType: formType.code })"
            class="mb-3 inline-flex items-center gap-2 text-sm text-slate-500 hover:text-primary"
        >
            <i class="fa-solid fa-arrow-left" />
            {{ formType.name[locale] }}
        </Link>

        <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 sm:text-2xl">{{ document.title[locale] }}</h1>
                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
                    <span><i class="fa-regular fa-calendar mr-1" />{{ t.common.period }} {{ document.period }}</span>
                    <span
                        v-if="draft"
                        class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700"
                    >
                        <i class="fa-solid fa-floppy-disk" />
                        {{ t.forms.draftSavedAt }} {{ formatDateTime(draft.agreed_at) }}
                    </span>
                </p>
            </div>

            <!-- Steps -->
            <ol class="flex items-center gap-2 text-xs font-medium sm:text-sm">
                <li
                    v-for="(item, index) in steps"
                    :key="item.n"
                    class="flex items-center gap-2"
                >
                    <span
                        class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold"
                        :class="step > item.n ? 'bg-green-500 text-white' : step === item.n ? 'bg-primary text-white' : 'bg-slate-200 text-slate-500'"
                    >
                        <i
                            v-if="step > item.n"
                            class="fa-solid fa-check"
                        />
                        <template v-else>{{ item.n }}</template>
                    </span>
                    <span :class="step === item.n ? 'text-slate-800' : 'text-slate-500'">{{ item.label }}</span>
                    <span
                        v-if="index < steps.length - 1"
                        class="h-px w-4 bg-slate-300 sm:w-8"
                    />
                </li>
            </ol>
        </div>

        <FlashMessage
            type="success"
            :message="page.props.flash?.success"
        />

        <FlashMessage
            type="error"
            :message="page.props.flash?.error"
        />

        <div
            v-if="!reachedEnd"
            class="mb-4 flex flex-wrap items-center gap-2 rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800"
        >
            <i class="fa-solid fa-book-open-reader" />
            <span>{{ t.forms.readHint }}</span>
        </div>

        <div
            ref="documentEl"
            class="pb-40"
        >
            <FormDocument
                :page="document.page"
                :content="document.content[locale]"
                :header="document.header[locale]"
                :footer="document.footer[locale]"
            />

            <div
                ref="endMarker"
                class="mt-4 flex items-center justify-center gap-2 text-sm text-slate-400"
            >
                <span class="h-px w-12 bg-slate-300" />
                {{ t.forms.endOfDocument }}
                <span class="h-px w-12 bg-slate-300" />
            </div>
        </div>

        <!-- Actions, always in reach -->
        <div
            ref="actionBar"
            class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-white/95 shadow-[0_-4px_12px_rgba(0,0,0,0.06)] backdrop-blur lg:left-[260px]"
        >
            <div class="mx-auto flex max-w-5xl flex-col gap-3 px-4 py-3 sm:px-6 md:flex-row md:items-center md:justify-between">
                <label
                    class="flex items-start gap-3"
                    :class="reachedEnd ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'"
                >
                    <input
                        v-model="agreed"
                        type="checkbox"
                        class="mt-0.5 h-5 w-5 shrink-0 rounded border-slate-300 text-primary focus:ring-primary"
                        :disabled="!reachedEnd"
                    >
                    <span class="text-sm">
                        <span class="font-semibold text-slate-800">{{ t.forms.agree }}</span>
                        <button
                            v-if="!reachedEnd"
                            type="button"
                            class="block text-xs text-sky-700 underline"
                            @click="scrollToEnd"
                        >
                            {{ t.forms.readToUnlock }}
                        </button>
                    </span>
                </label>

                <div class="flex shrink-0 gap-2">
                    <button
                        type="button"
                        class="btn btn-outline-secondary flex-1 md:flex-none"
                        :disabled="!agreed || savingDraft"
                        @click="saveDraft"
                    >
                        <i
                            class="fa-solid"
                            :class="savingDraft ? 'fa-circle-notch fa-spin' : 'fa-floppy-disk'"
                        />
                        {{ t.forms.saveDraft }}
                    </button>

                    <button
                        type="button"
                        class="btn-primary-custom flex-1 justify-center md:flex-none"
                        :disabled="!agreed"
                        @click="showSignature = true"
                    >
                        <i class="fa-solid fa-paper-plane" />
                        {{ t.common.submit }}
                    </button>
                </div>
            </div>
        </div>

        <SignatureModal
            :show="showSignature"
            :template-id="document.id"
            :document-title="document.title[locale]"
            :full-name="fullName"
            :version="version"
            @close="showSignature = false"
        />
    </EmployeeLayout>
</template>
