import { computed } from 'vue'
import { useLocale } from '@/Composables/useLocale'

/**
 * The Employee Status filter (App\Support\EmployeeStatus on the server):
 * active by default, so people who have left only show when asked for.
 */
export const DEFAULT_EMPLOYEE_STATUS = 'active'

export function useEmployeeStatusOptions() {
    const { t } = useLocale()

    return computed(() => [
        { value: 'active', label: t.value.common.employeeActive },
        { value: 'inactive', label: t.value.common.employeeInactive },
        { value: 'all', label: t.value.common.allEmployeeStatus },
    ])
}
