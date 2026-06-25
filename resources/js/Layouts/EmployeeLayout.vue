<script setup lang="ts">
import AppLayout from './AppLayout.vue'
import { employeeNavigation } from '@/Config/navigation'

const user = {
    name: 'Metta Saputra',
    employee_id: 'KPN-01124040',
    initials: 'M',
}
</script>

<template>
    <AppLayout
        :navigation="employeeNavigation"
        :user="user"
    >
        <slot />
    </AppLayout>
</template>