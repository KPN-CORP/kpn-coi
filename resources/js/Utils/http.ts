/** Laravel's CSRF token from the page's <meta>, for fetch() POSTs. */
export function csrfToken(): string {
    return document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? ''
}
