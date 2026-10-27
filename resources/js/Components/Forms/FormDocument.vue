<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type { PageLayout } from './types'

/**
 * A form laid out on its paper: size, margins, header and footer, the way the
 * PDF prints it (resources/views/pdf/form_declaration.blade.php). On screen it
 * is one continuous page -- the PDF repeats the header and footer on each.
 *
 * Every HTML string given here is either server-rendered (sanitised, values
 * escaped) or, in the template editor's preview, the admin's own input.
 */
const props = defineProps<{
    page: PageLayout
    content: string
    header?: string
    footer?: string
}>()

const hasHtml = (html?: string) =>
    Boolean(html && (html.replace(/<[^>]*>/g, '').trim() || html.includes('<img')))

// Same distances from the paper edge as the PDF view.
const headerGap = computed(() => Math.min(10, props.page.margin_top / 3))
const footerGap = computed(() => Math.min(8, props.page.margin_bottom / 3))

// Shrink the page to fit narrow screens (phones, the preview modal) instead
// of scrolling sideways. CSS zoom keeps the layout box in step with the
// scaled page, unlike transform: scale.
const MM_TO_PX = 96 / 25.4

const frame = ref<HTMLElement | null>(null)
const zoom = ref(1)
// Below this zoom the text gets too small to read (phones): drop the fixed
// page and let the text reflow at its normal size instead.
const MIN_ZOOM = 0.7
const reflow = computed(() => zoom.value < MIN_ZOOM)
let observer: ResizeObserver | null = null

function fit() {
    if (!frame.value) {
        return
    }

    // A fixed allowance for the frame's padding rather than the live value:
    // reading mode drops that padding, and measuring it would make the page
    // flip between the two modes at some widths.
    const available = frame.value.clientWidth - 48

    zoom.value = Math.min(1, available / (props.page.width * MM_TO_PX))
}

// --- Page count for {{page_count}} --------------------------------------------
// The PDF counts real pages; on screen the body is one continuous page, so
// estimate how many sheets it fills from its height against the space
// between the top and bottom margins.
const body = ref<HTMLElement | null>(null)
const pageCount = ref(1)

function countPages() {
    if (!body.value || reflow.value) {
        return
    }

    const usable = (props.page.height - props.page.margin_top - props.page.margin_bottom) * MM_TO_PX

    // offsetHeight is in the page's own (unzoomed) pixels, like `usable`.
    pageCount.value = usable > 0 ? Math.max(1, Math.ceil(body.value.offsetHeight / usable)) : 1
}

onMounted(() => {
    fit()
    observer = new ResizeObserver(() => {
        fit()
        countPages()
    })

    if (frame.value) {
        observer.observe(frame.value)
    }

    if (body.value) {
        observer.observe(body.value)
    }

    countPages()
})

onBeforeUnmount(() => observer?.disconnect())

watch(() => [props.content, props.page.height, props.page.margin_top, props.page.margin_bottom], () => nextTick(countPages))

watch(() => [props.page.width, props.page.orientation], () => nextTick(fit))

const paperStyle = computed(() => reflow.value
    ? {
        '--form-page-count': `'${pageCount.value}'`,
        fontFamily: props.page.font_css,
        fontSize: `${props.page.font_size}pt`,
        lineHeight: String(props.page.line_height),
        padding: '1.25rem 1rem',
    }
    : {
        // Read by .form-page-count in coi.css.
        '--form-page-count': `'${pageCount.value}'`,
        zoom: String(zoom.value),
        fontFamily: props.page.font_css,
        fontSize: `${props.page.font_size}pt`,
        lineHeight: String(props.page.line_height),
        width: `${props.page.width}mm`,
        minHeight: `${props.page.height}mm`,
        padding: `${props.page.margin_top}mm ${props.page.margin_right}mm ${props.page.margin_bottom}mm ${props.page.margin_left}mm`,
    })
</script>

<template>
    <div
        ref="frame"
        class="overflow-x-auto rounded-md bg-slate-100"
        :class="reflow ? 'p-0' : 'p-3 sm:p-6'"
    >
        <div
            class="form-font-inherit relative mx-auto bg-white text-black shadow-md"
            :class="{ 'form-reflow': reflow }"
            :style="paperStyle"
        >
            <div
                v-if="hasHtml(header)"
                class="form-content"
                :class="reflow ? 'mb-4 border-b border-slate-100 pb-3' : 'absolute'"
                :style="reflow ? undefined : { top: `${headerGap}mm`, left: `${page.margin_left}mm`, right: `${page.margin_right}mm` }"
                v-html="header"
            />

            <div
                ref="body"
                class="form-content"
                v-html="content"
            />

            <slot />

            <div
                v-if="hasHtml(footer)"
                class="form-content"
                :class="reflow ? 'mt-6 border-t border-slate-100 pt-3' : 'absolute'"
                :style="reflow ? { fontSize: '0.8em' } : { bottom: `${footerGap}mm`, left: `${page.margin_left}mm`, right: `${page.margin_right}mm`, fontSize: '0.8em' }"
                v-html="footer"
            />
        </div>
    </div>
</template>
