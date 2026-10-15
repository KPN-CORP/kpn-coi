import { onBeforeUnmount } from 'vue'
import debounce from 'lodash/debounce'

/**
 * Shared pieces of the list/report screens' filter bars.
 */

/**
 * Drop empty filters ('' / null / undefined) so the URL only carries the
 * filters actually in use.
 */
export function cleanQuery(query: Record<string, unknown>): Record<string, unknown> {
    return Object.fromEntries(
        Object.entries(query).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    )
}

/**
 * A debounced callback (search-as-you-type) that is cancelled when the page
 * unmounts -- otherwise a search typed just before following a link would
 * still fire and navigate the user back to this page.
 */
export function useDebounced(callback: () => void, wait = 400) {
    const debounced = debounce(callback, wait)

    onBeforeUnmount(() => debounced.cancel())

    return debounced
}
