import Swal from 'sweetalert2';

export const showSuccess = (title = 'Berhasil!', text = '') => {
    return Swal.fire({
        icon: 'success',
        title: title,
        text: text,
        confirmButtonColor: '#3b6bff',
        customClass: {
            popup: 'rounded-4 shadow-lg border-0',
        }
    });
};

export const showWarning = (title = 'Peringatan!', text = '') => {
    return Swal.fire({
        icon: 'warning',
        title: title,
        text: text,
        confirmButtonColor: '#f59e0b',
        customClass: {
            popup: 'rounded-4 shadow-lg border-0',
        }
    });
};

export const showError = (title = 'Terjadi Kesalahan!', text = '') => {
    return Swal.fire({
        icon: 'error',
        title: title,
        text: text,
        confirmButtonColor: '#ef4444',
        customClass: {
            popup: 'rounded-4 shadow-lg border-0',
        }
    });
};

export const showConfirm = (title = 'Apakah Anda yakin?', text = '', onConfirm) => {
    return Swal.fire({
        icon: 'question',
        title: title,
        text: text,
        showCancelButton: true,
        confirmButtonText: 'Ya, Lanjutkan!',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#3b6bff',
        cancelButtonColor: '#64748b',
        customClass: {
            popup: 'rounded-4 shadow-lg border-0',
        }
    }).then((result) => {
        if (result.isConfirmed && typeof onConfirm === 'function') {
            onConfirm();
        }
    });
};
