import BaseSwal from 'sweetalert2'

/**
 * SweetAlert with the app's look preset (brand-red confirm button), so a
 * pop-up never falls back to SweetAlert's own blue. Import this instead of
 * 'sweetalert2'; options passed to fire() still override it.
 */
const Swal = BaseSwal.mixin({
    confirmButtonColor: '#ab2f2b',
})

export default Swal
