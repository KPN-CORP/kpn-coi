import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function usePermission() {
    const page = usePage()

    const permissions = computed(
        () => page.props.permissions ?? [],
    )

    function can(
        permission: string,
    ) {
        return permissions.value.includes(
            permission,
        )
    }

    return {
        can,
        permissions,
    }
}