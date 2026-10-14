<script setup lang="ts">
import Modal from '@/Components/UI/Modal.vue'
import { useLocale } from '@/Composables/useLocale'
import { useForm } from '@inertiajs/vue3'
import { computed, nextTick, ref, watch } from 'vue'

/**
 * The last step of signing: the signer types their full name as written in
 * their records. Same rule as the server (FormDeclarationService::
 * normalizeName) and the COI submit: stray spaces are forgiven, letter case is
 * not. Pasting is blocked so the name is actually typed.
 */
const props = defineProps<{
    show: boolean
    templateId: number
    documentTitle: string
    fullName: string
}>()

const emit = defineEmits<{
    close: []
}>()

const { t, locale } = useLocale()

const form = useForm({
    locale: locale.value as string,
    signed_name: '',
    agree: true,
})

const input = ref<HTMLInputElement | null>(null)

const normalizeName = (value: string) => value.replace(/\s+/g, ' ').trim()

const matches = computed(() => normalizeName(form.signed_name) === normalizeName(props.fullName))

// Matches when letter case is ignored: worth a specific hint.
const caseOnly = computed(() => !matches.value
    && normalizeName(form.signed_name).toLowerCase() === normalizeName(props.fullName).toLowerCase())

watch(() => props.show, async (show) => {
    if (show) {
        form.reset()
        form.clearErrors()
        await nextTick()
        input.value?.focus()
    }
})

const block = (event: Event) => event.preventDefault()

function sign() {
    if (!matches.value || form.processing) {
        return
    }

    // Stamp the language the signer is reading in at the moment of signing.
    form.locale = locale.value
    form.post(route('employee.forms.sign', props.templateId), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Modal
        :show="show"
        max-width="max-w-lg"
        @close="form.processing || emit('close')"
    >
        <form
            class="flex flex-col"
            @submit.prevent="sign"
        >
            <div class="flex items-start gap-3 border-b border-border px-6 py-5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-primary">
                    <i class="fa-solid fa-signature" />
                </span>
                <div class="min-w-0">
                    <h3 class="text-lg font-bold text-slate-800">{{ t.forms.signTitle }}</h3>
                    <p class="truncate text-sm text-slate-500">{{ documentTitle }}</p>
                </div>
            </div>

            <div class="space-y-4 px-6 py-5">
                <p class="text-sm text-slate-600">{{ t.forms.signIntro }}</p>

                <div class="rounded-md bg-slate-50 px-4 py-3">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ t.forms.typeExactly }}</div>
                    <div class="mt-1 select-none text-base font-semibold text-slate-800">{{ fullName }}</div>
                </div>

                <div>
                    <label
                        for="form-signature"
                        class="text-sm font-medium text-slate-700"
                    >
                        {{ t.forms.typeNameLabel }}
                    </label>

                    <div class="relative mt-1.5">
                        <input
                            id="form-signature"
                            ref="input"
                            v-model="form.signed_name"
                            type="text"
                            autocomplete="off"
                            spellcheck="false"
                            class="w-full rounded-md border py-2.5 pl-3 pr-10 text-base font-medium"
                            :class="form.signed_name && !matches ? 'border-red-300 focus:border-red-400 focus:ring-red-200' : matches ? 'border-green-400 focus:border-green-500 focus:ring-green-200' : 'border-border'"
                            :aria-invalid="Boolean(form.signed_name) && !matches"
                            aria-describedby="form-signature-help"
                            @paste="block"
                            @drop="block"
                            @contextmenu="block"
                        >
                        <i
                            v-if="form.signed_name"
                            class="fa-solid absolute right-3 top-1/2 -translate-y-1/2"
                            :class="matches ? 'fa-circle-check text-green-500' : 'fa-circle-xmark text-red-400'"
                        />
                    </div>

                    <p
                        id="form-signature-help"
                        class="mt-1.5 text-xs"
                        :class="form.errors.signed_name || (form.signed_name && !matches) ? 'text-red-600' : 'text-slate-500'"
                    >
                        <template v-if="form.errors.signed_name">{{ form.errors.signed_name }}</template>
                        <template v-else-if="caseOnly">{{ t.forms.caseHint }}</template>
                        <template v-else-if="form.signed_name && !matches">{{ t.forms.nameMismatch }}</template>
                        <template v-else-if="matches">{{ t.forms.nameMatches }}</template>
                        <template v-else>{{ t.forms.noPasteHint }}</template>
                    </p>
                </div>

                <p class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    <i class="fa-solid fa-triangle-exclamation mt-0.5" />
                    {{ t.forms.confirmText }}
                </p>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-border px-6 py-4 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    :disabled="form.processing"
                    @click="emit('close')"
                >
                    {{ t.common.cancel }}
                </button>

                <button
                    type="submit"
                    class="btn-primary-custom justify-center"
                    :disabled="!matches || form.processing"
                >
                    <i
                        class="fa-solid"
                        :class="form.processing ? 'fa-circle-notch fa-spin' : 'fa-file-signature'"
                    />
                    {{ form.processing ? t.forms.signing : t.forms.signNow }}
                </button>
            </div>
        </form>
    </Modal>
</template>
