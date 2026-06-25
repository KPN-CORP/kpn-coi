<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue'

const props = withDefaults(
    defineProps<{
        show: boolean
        maxWidth?: string
    }>(),
    {
        maxWidth: 'max-w-2xl',
    },
)

const emit = defineEmits<{
    close: []
}>()

function close() {
    emit('close')
}

function handleEscape(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        close()
    }
}

watch(
    () => props.show,
    (show) => {
        if (show) {
            document.body.style.overflow = 'hidden'
            document.addEventListener('keydown', handleEscape)
        } else {
            document.body.style.overflow = ''
            document.removeEventListener('keydown', handleEscape)
        }
    },
    { immediate: true },
)

onBeforeUnmount(() => {
    document.body.style.overflow = ''
    document.removeEventListener('keydown', handleEscape)
})
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200 ease-in-out"
            enter-from-class="opacity-50"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in-out"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="close"
            >
                <Transition
                        enter-active-class="transition-all duration-200 ease-in-out"
                        enter-from-class="translate-y-8 opacity-50"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition-all duration-200 ease-in-out"
                        leave-from-class="translate-y-0 opacity-100"
                        leave-to-class="translate-y-8 opacity-0"
                        appear
                    >
                    <div
                        v-if="show"
                        class="flex max-h-[90vh] w-full flex-col overflow-hidden rounded-xl bg-white shadow-2xl"
                        :class="maxWidth"
                    >
                        <slot />
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>