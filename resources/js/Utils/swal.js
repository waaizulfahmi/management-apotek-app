import Swal from 'sweetalert2';

export const showSuccess = (title = 'Berhasil!', text = '', timer = 2000) => {
    let timerInterval;
    const initialSec = Math.max(1, Math.ceil((timer || 2000) / 1000));
    return Swal.fire({
        icon: 'success',
        title: title,
        html: timer ? `
            <div class="mb-2 text-dark">${text}</div>
            <div class="small text-muted font-monospace mt-3 pt-2 border-top" style="font-size: 0.82rem;">
                ⏱️ Menutup otomatis dalam <strong id="swal-success-timer" class="text-primary fs-6 fw-bold">${initialSec}</strong> detik...
            </div>
        ` : `<div class="text-dark">${text}</div>`,
        confirmButtonColor: '#3b6bff',
        timer: timer || undefined,
        timerProgressBar: !!timer,
        showConfirmButton: !timer,
        customClass: {
            popup: 'rounded-4 shadow-lg border-0',
        },
        didOpen: () => {
            const timerEl = Swal.getHtmlContainer()?.querySelector('#swal-success-timer');
            if (timerEl && timer) {
                timerInterval = setInterval(() => {
                    const left = Swal.getTimerLeft();
                    if (left !== null) {
                        const secLeft = Math.max(1, Math.ceil(left / 1000));
                        timerEl.textContent = secLeft;
                    }
                }, 100);
            }
        },
        willClose: () => {
            if (timerInterval) clearInterval(timerInterval);
        }
    });
};

export const showWarning = (title = 'Peringatan!', text = '', timer = 2000) => {
    let timerInterval;
    const initialSec = Math.max(1, Math.ceil((timer || 2000) / 1000));
    return Swal.fire({
        icon: 'warning',
        title: title,
        html: timer ? `
            <div class="mb-2 text-dark">${text}</div>
            <div class="small text-muted font-monospace mt-3 pt-2 border-top" style="font-size: 0.82rem;">
                ⏱️ Menutup otomatis dalam <strong id="swal-warning-timer" class="text-warning fs-6 fw-bold">${initialSec}</strong> detik...
            </div>
        ` : `<div class="text-dark">${text}</div>`,
        confirmButtonColor: '#f59e0b',
        timer: timer || undefined,
        timerProgressBar: !!timer,
        showConfirmButton: !timer,
        customClass: {
            popup: 'rounded-4 shadow-lg border-0',
        },
        didOpen: () => {
            const timerEl = Swal.getHtmlContainer()?.querySelector('#swal-warning-timer');
            if (timerEl && timer) {
                timerInterval = setInterval(() => {
                    const left = Swal.getTimerLeft();
                    if (left !== null) {
                        const secLeft = Math.max(1, Math.ceil(left / 1000));
                        timerEl.textContent = secLeft;
                    }
                }, 100);
            }
        },
        willClose: () => {
            if (timerInterval) clearInterval(timerInterval);
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
        return result.isConfirmed;
    });
};


