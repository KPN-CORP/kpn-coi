import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useNavigation() {
    const page = usePage()

    return computed(() => {
        const navigation = page.props.navigation
        const permissions = page.props.permissions ?? []

        const can = (permission: string) =>
            permissions.includes(permission)

        const items = []

        // Employee Menu

        items.push(
            {
                label: 'History',
                icon: 'fa-solid fa-clock-rotate-left',
                route: 'employee.history',
            },
            {
                label: 'New Declaration',
                icon: 'fa-solid fa-file-circle-plus',
                route: 'employee.language',
            },
        )

        // Manager Menu

        if (navigation.is_manager) {
            items.push({
                label: 'Team History',
                icon: 'fa-solid fa-users',
                route: 'manager.team-history',
            })
        }

        // Admin Menu

        if (can('dashboard.view')) {
            items.push({
                label: 'Dashboard',
                icon: 'fa-solid fa-chart-line',
                route: 'admin.dashboard',
            })
        }

        if (can('report.view')) {
            items.push({
                label: 'Reports',
                icon: 'fa-solid fa-file-lines',
                route: 'admin.report',
            })
        }

        if (can('credential.view')) {
            items.push({
                label: 'Credentials',
                icon: 'fa-solid fa-users-gear',
                route: 'admin.credentials',
            })
        }

        if (can('role.view')) {
            items.push({
                label: 'Role Management',
                icon: 'fa-solid fa-user-shield',
                route: 'admin.roles',
            })
        }

        return items
    })
}