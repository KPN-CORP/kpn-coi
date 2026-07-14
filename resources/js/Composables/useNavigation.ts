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

        items.push({
            section: 'My Account',
            label: 'History',
            icon: 'fa-solid fa-clock-rotate-left',
            route: 'employee.history',
        })

        if (navigation.is_manager) {
            items.push({
                section: 'My Account',
                label: 'Team History',
                icon: 'fa-solid fa-users',
                route: 'manager.team-history',
            })
        }

        if (can('dashboard.view')) {
            items.push({
                section: 'Administration',
                label: 'Dashboard',
                icon: 'fa-solid fa-chart-line',
                route: 'admin.dashboard',
            })
        }

        if (can('report.view')) {
            items.push({
                section: 'Administration',
                label: 'Reports',
                icon: 'fa-solid fa-file-lines',
                route: 'admin.report',
            })
        }

        if (can('credential.view')) {
            items.push({
                section: 'Administration',
                label: 'Credentials',
                icon: 'fa-solid fa-users-gear',
                route: 'admin.credentials',
            })
        }

        if (can('role.view')) {
            items.push({
                section: 'Administration',
                label: 'Role Management',
                icon: 'fa-solid fa-user-shield',
                route: 'admin.roles',
            })
        }

        return items
    })
}