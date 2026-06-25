<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import { Link } from '@inertiajs/vue3'
import EmployeeLayout from '@/Layouts/EmployeeLayout.vue'

const navigation = [
    {
        label: 'History',
        href: route('employee.history'),
        icon: 'fa-solid fa-clock-rotate-left',
        active: true,
    },
]

const user = {
    name: 'Metta Saputra',
    employee_id: 'KPN-01124040',
    initials: 'M',
}
</script>

<template>
    <EmployeeLayout>
        <PageHeader
            title="Create Declaration"
            description="Please choose your preferred language for the form."
        >
            <template #actions>
                <Link
                    :href="route('employee.history')"
                    class="rounded-md border border-border bg-white px-4 py-2 text-sm font-medium text-text hover:bg-slate-50"
                >
                    Cancel
                </Link>
            </template>
        </PageHeader>

        <div class="grid gap-6 md:grid-cols-2">
            <Link
                :href="route('employee.declarations.create', { locale: 'id' })"
                class="group"
            >
                <Card class="lang-card transition-all duration-100 ease-out group-hover:border-primary group-hover:-translate-y-1">
                    <div
                        class="rounded-md border border-transparent p-6 text-center"
                    >
                        <h3
                            class="mb-2 text-lg font-bold text-slate-900"
                        >
                            Bahasa Indonesia
                        </h3>

                        <p
                            class="text-sm text-slate-500"
                        >
                            Isi formulir menggunakan Bahasa Indonesia.
                        </p>
                    </div>
                </Card>
            </Link>

            <Link
                :href="route('employee.declarations.create', { locale: 'en' })"
                class="group"
            >
                <Card class="lang-card transition-all duration-100 ease-out group-hover:border-primary group-hover:-translate-y-1">
                    <div
                        class="rounded-md border border-transparent p-6 text-center"
                    >
                        <h3
                            class="mb-2 text-lg font-bold text-slate-900"
                        >
                            English
                        </h3>

                        <p
                            class="text-sm text-slate-500"
                        >
                            Fill out the form using English language.
                        </p>
                    </div>
                </Card>
            </Link>
        </div>
    </EmployeeLayout>
</template>