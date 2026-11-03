/**
 * Shared column-sort rule: clicking the sorted column flips its direction,
 * clicking another starts it ascending. Works on any object holding
 * `sort` + `direction` (a useForm filter, a reactive() one, ...).
 */
export interface SortState {
    sort: string | null | undefined
    direction: 'asc' | 'desc' | string | null | undefined
}

export function toggleSort(state: SortState, column: string): void {
    if (state.sort === column) {
        state.direction = state.direction === 'asc' ? 'desc' : 'asc'

        return
    }

    state.sort = column
    state.direction = 'asc'
}
