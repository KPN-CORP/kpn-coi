<script setup lang="ts">
import AppLayout from './AppLayout.vue'
import { adminNavigation } from '@/Config/navigation'

const user = {
    name: 'Compliance Admin',
    employee_id: 'ADM-00001',
    initials: 'CA',
}
</script>

<template>
    <AppLayout
        :navigation="adminNavigation"
        :user="user"
    >
        <slot />
    </AppLayout>
</template>