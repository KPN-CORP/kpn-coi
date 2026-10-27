import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useLocale } from '@/Composables/useLocale'

export interface NavigationForm {
    id: number
    code: string
    name: { en: string, id: string }
    icon: string
    pending: number
    // Menu position, shared with Conflict of Interest's (navigation.coi_sort_order).
    sort_order: number
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

        const forms = (navigation.forms ?? []) as NavigationForm[]

        // First item: everything still to fill in or sign, in one list.
        const items: NavigationItem[] = [{
            section: t.value.nav.myAccount,
            label: t.value.nav.myCommitment,
            icon: 'fa-solid fa-list-check',
            route: 'employee.commitments',
            badge: forms.reduce((total, form) => total + form.pending, 0),
        }]

        // The COI declaration, shaped like the form type items below: a link to
        // the person's own history, or for a manager a dropdown that adds
        // their team's.
        const coi: NavigationItem = {
            section: t.value.nav.myAccount,
            label: t.value.nav.conflictOfInterest,
            icon: 'fa-solid fa-scale-balanced',
            route: 'employee.history',
        }

        // COI and the form types are ordered together by their menu order
        // (Master Settings -> Form Type); on a tie COI comes first, as before.
        const ordered: { order: number, item: NavigationItem }[] = []

        ordered.push({ order: Number(navigation.coi_sort_order ?? 0), item: navigation.is_manager
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
            : coi })

        // One item per active form type (Master Settings -> Form Type). A
        // manager gets it as a dropdown: their own declaration, and the same
        // form for the people reporting to them.
        for (const form of forms) {
            const own: NavigationItem = {
                section: t.value.nav.myAccount,
                label: form.name[locale.value],
                icon: form.icon,
                route: 'employee.forms.show',
                params: { formType: form.code },
                badge: form.pending,
            }

            ordered.push({ order: Number(form.sort_order ?? 0), item: navigation.is_manager
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
                : own })
        }

        // Array.prototype.sort is stable, so equal orders keep COI first and
        // form types in their server order (sort_order, then name).
        ordered
            .sort((a, b) => a.order - b.order)
            .forEach(entry => items.push(entry.item))

        // One dashboard for COI and every form type (its Form filter).
        // A form type's dashboard and report are granted per type.
        const anyFormType = permissions.some((permission: string) => permission.startsWith('form_type.'))

        if (can('dashboard.view') || anyFormType) {
            items.push({
                section: t.value.nav.administration,
                label: t.value.nav.dashboard,
                icon: 'fa-solid fa-chart-line',
                route: 'admin.dashboard',
            })
        }

        // One report for COI and every form type (its Form filter).
        if (can('report.view') || anyFormType) {
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

        if (can('import.view')) {
            items.push({
                section: t.value.nav.administration,
                label: t.value.nav.importCenter,
                icon: 'fa-solid fa-file-import',
                route: 'admin.import-center.index',
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

        // Also limited to the accounts in FORM_MASTER_ADMIN_EMAILS.
        if (can('form_master.view') && navigation.form_master) {
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
