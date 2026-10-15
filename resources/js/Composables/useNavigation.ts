import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useLocale } from '@/Composables/useLocale'

export interface NavigationForm {
    id: number
    code: string
    name: { en: string, id: string }
    icon: string
    pending: number
}

export interface NavigationItem {
    section: string
    label: string
    icon: string
    route: string
    // Route parameters, e.g. the form type code of a per-type form page.
    params?: Record<string, string | number>
    badge?: number
    // Route-name pattern that also lights the item (child screens).
    activePattern?: string
    // Sub-items: the item becomes a dropdown instead of a link.
    children?: NavigationItem[]
}

export function useNavigation() {
    const page = usePage()

    const { t, locale } = useLocale()

    return computed(() => {
        const navigation = page.props.navigation
        const permissions = page.props.permissions ?? []

        const can = (permission: string) =>
            permissions.includes(permission)

        const items: NavigationItem[] = []

        // The COI declaration, shaped like the form type items below: a link to
        // the person's own history, or for a manager a dropdown that adds
        // their team's.
        const coi: NavigationItem = {
            section: t.value.nav.myAccount,
            label: t.value.nav.conflictOfInterest,
            icon: 'fa-solid fa-scale-balanced',
            route: 'employee.history',
        }

        items.push(navigation.is_manager
            ? {
                ...coi,
                children: [
                    { ...coi, label: t.value.nav.myHistory, icon: 'fa-solid fa-user' },
                    {
                        section: coi.section,
                        label: t.value.nav.teamHistory,
                        icon: 'fa-solid fa-users',
                        route: 'manager.team-history',
                    },
                ],
            }
            : coi)

        // One item per active form type (Master Settings -> Form Type). A
        // manager gets it as a dropdown: their own declaration, and the same
        // form for the people reporting to them.
        for (const form of (navigation.forms ?? []) as NavigationForm[]) {
            const own: NavigationItem = {
                section: t.value.nav.myAccount,
                label: form.name[locale.value],
                icon: form.icon,
                route: 'employee.forms.show',
                params: { formType: form.code },
                badge: form.pending,
            }

            items.push(navigation.is_manager
                ? {
                    ...own,
                    children: [
                        { ...own, label: t.value.nav.myHistory, icon: 'fa-solid fa-user' },
                        {
                            section: own.section,
                            label: t.value.nav.teamHistory,
                            icon: 'fa-solid fa-users',
                            route: 'manager.forms.team',
                            params: { formType: form.code },
                        },
                    ],
                }
                : own)
        }

        // One dashboard for COI and every form type (its Form filter).
        if (can('dashboard.view') || can('form_dashboard.view')) {
            items.push({
                section: t.value.nav.administration,
                label: t.value.nav.dashboard,
                icon: 'fa-solid fa-chart-line',
                route: 'admin.dashboard',
            })
        }

        // One report for COI and every form type (its Form filter).
        if (can('report.view') || can('form_report.view')) {
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

        if (can('form_master.view')) {
            items.push({
                section: t.value.nav.masterSettings,
                label: t.value.nav.templates,
                icon: 'fa-solid fa-file-pen',
                route: 'admin.master.templates.index',
                // Keep the item lit on the create/edit screens too.
                activePattern: 'admin.master.templates.*',
            })

            items.push({
                section: t.value.nav.masterSettings,
                label: t.value.nav.formTypes,
                icon: 'fa-solid fa-layer-group',
                route: 'admin.master.form-types.index',
            })
        }

        return items
    })
}
