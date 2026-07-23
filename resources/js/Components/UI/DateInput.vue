<script setup lang="ts">
import { computed, ref, watch } from 'vue'

/**
 * A date field that always reads dd-mm-yyyy.
 *
 * A native `<input type="date">` renders in the browser/OS locale -- en-US
 * machines show 07/24/2026 -- and that display cannot be styled or overridden.
 * So the visible control is a masked text input, and the native one is kept
 * alongside purely to open the calendar the users are used to.
 *
 * `modelValue` is always ISO yyyy-mm-dd, exactly what the native input emitted
 * before, so what gets submitted and stored is unchanged. Only the rendering of
 * the value differs. A partial or impossible entry (31-02-2026) emits '' rather
 * than a half-formed date, so it fails the same "required" checks as an empty
 * field instead of reaching the server malformed.
 */
const props = withDefaults(
    defineProps<{
        modelValue?: string | null
        min?: string
        max?: string
        disabled?: boolean
        invalid?: boolean
        placeholder?: string
    }>(),
    {
        modelValue: '',
        min: '1900-01-01',
        max: '9999-12-31',
        placeholder: 'dd-mm-yyyy',
    },
)

const emit = defineEmits<{
    'update:modelValue': [string]
    change: []
}>()

const display = ref('')
const nativeInput = ref<HTMLInputElement | null>(null)

/** yyyy-mm-dd -> dd-mm-yyyy */
function toDisplay(iso?: string | null): string {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso ?? '')

    return match
        ? `${match[3]}-${match[2]}-${match[1]}`
        : ''
}

/**
 * dd-mm-yyyy -> yyyy-mm-dd, or '' when the entry is incomplete, not a real
 * calendar date, or outside min/max. ISO strings compare correctly as text,
 * which is why the bounds check is a plain string comparison.
 */
function toIso(text: string): string {
    const match = /^(\d{2})-(\d{2})-(\d{4})$/.exec(text)

    if (!match) {
        return ''
    }

    const [, day, month, year] = match
    const date = new Date(Number(year), Number(month) - 1, Number(day))

    // Rejects 31-02: the Date constructor rolls overflow forward instead of
    // failing, so the parts have to be compared back.
    const real = date.getFullYear() === Number(year)
        && date.getMonth() === Number(month) - 1
        && date.getDate() === Number(day)

    if (!real) {
        return ''
    }

    const iso = `${year}-${month}-${day}`

    if (iso < props.min || iso > props.max) {
        return ''
    }

    return iso
}

// Keep the box in step with the value when the parent changes it (prefilled
// edits, a reset, the calendar) -- but never while the user is mid-entry.
watch(
    () => props.modelValue,
    (value) => {
        if (toIso(display.value) !== (value ?? '')) {
            display.value = toDisplay(value)
        }
    },
    { immediate: true },
)

function onType(event: Event) {
    const input = event.target as HTMLInputElement

    const digits = input.value
        .replace(/\D/g, '')
        .slice(0, 8)

    // Re-insert the separators as they type, so the field reads dd-mm-yyyy
    // without the user having to enter the dashes.
    const parts = [
        digits.slice(0, 2),
        digits.slice(2, 4),
        digits.slice(4, 8),
    ].filter(part => part !== '')

    display.value = parts.join('-')
    input.value = display.value

    emit('update:modelValue', toIso(display.value))
    emit('change')
}

function onPick(event: Event) {
    const value = (event.target as HTMLInputElement).value

    display.value = toDisplay(value)

    emit('update:modelValue', value)
    emit('change')
}

// showPicker() needs a user gesture; it is missing on older browsers, where
// the button simply focuses the native input instead.
function openCalendar() {
    if (props.disabled || !nativeInput.value) {
        return
    }

    try {
        nativeInput.value.showPicker()
    } catch {
        nativeInput.value.focus()
    }
}

const inputClass = computed(() => [
    'w-full rounded-md border py-2 pl-3 pr-9 text-sm',
    props.disabled ? 'bg-slate-100' : '',
    props.invalid ? 'border-red-500 bg-red-50' : 'border-border',
])
</script>

<template>
    <div class="relative">
        <input
            :value="display"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            maxlength="10"
            :placeholder="placeholder"
            :disabled="disabled"
            :class="inputClass"
            @input="onType"
        >

        <button
            type="button"
            class="absolute inset-y-0 right-0 flex w-9 items-center justify-center text-slate-400 transition-colors hover:text-primary disabled:cursor-not-allowed disabled:text-slate-300"
            :disabled="disabled"
            tabindex="-1"
            aria-label="Open calendar"
            @click="openCalendar"
        >
            <i class="fa-regular fa-calendar text-sm" />
        </button>

        <!--
            The real date control, kept for its calendar popup only. It has to
            stay rendered for showPicker() to work, so it is clipped rather than
            hidden, and taken out of the tab order -- the text box above is what
            is actually typed into.
        -->
        <input
            ref="nativeInput"
            type="date"
            class="pointer-events-none absolute bottom-0 right-2 h-0 w-0 opacity-0"
            tabindex="-1"
            aria-hidden="true"
            :value="modelValue ?? ''"
            :min="min"
            :max="max"
            :disabled="disabled"
            @input="onPick"
        >
    </div>
</template>
