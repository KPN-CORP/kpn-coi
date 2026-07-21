import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useLocale } from '@/Composables/useLocale'

export function useNavigation() {
    const page = usePage()

    const { t } = useLocale()

    return computed(() => {
        const navigation = page.props.navigation
        const permissions = page.props.permissions ?? []

        const can = (permission: string) =>
            permissions.includes(permission)

        const items = []

        items.push({
            section: t.value.nav.myAccount,
            label: t.value.nav.history,
            icon: 'fa-solid fa-clock-rotate-left',
            route: 'employee.history',
        })

        if (navigation.is_manager) {
            items.push({
                section: t.value.nav.myAccount,
                label: t.value.nav.teamHistory,
                icon: 'fa-solid fa-users',
                route: 'manager.team-history',
            })
        }

        if (can('dashboard.view')) {
            items.push({
                section: t.value.nav.administration,
                label: t.value.nav.dashboard,
                icon: 'fa-solid fa-chart-line',
                route: 'admin.dashboard',
            })
        }

        if (can('report.view')) {
            items.push({
                section: t.value.nav.administration,
                label: t.value.nav.reports,
                icon: 'fa-solid fa-file-lines',
                route: 'admin.report',
            })
        }

        if (can('credential.view')) {
            items.push({
                section: t.value.nav.administration,
                label: t.value.nav.credentials,
                icon: 'fa-solid fa-users-gear',
                route: 'admin.credentials',
            })
        }

        if (can('role.view')) {
            items.push({
                section: t.value.nav.administration,
                label: t.value.nav.roleManagement,
                icon: 'fa-solid fa-user-shield',
                route: 'admin.roles',
            })
        }

        return items
    })
}
