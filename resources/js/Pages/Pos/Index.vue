<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import RupiahInput from '@/Components/RupiahInput.vue';
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { usePage, useForm, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { showSuccess, showWarning, showError, showConfirm } from '@/Utils/swal';

const props = defineProps({
    medicines: Array,
    pagination: Object,
    categories: Array,
    customers: Array,
    prescriptions: Array,
    masterShifts: Array,
});

// DOM Element Refs
const searchInputRef = ref(null);
const categorySelectRef = ref(null);
const customerSelectRef = ref(null);
const discountInputRef = ref(null);
const taxInputRef = ref(null);
const paymentMethodRef = ref(null);
const paidInputRef = ref(null);
const inlineQtyInputRef = ref([]);

// Navigation & Focus States
const searchIndex = ref(0);
const cartIndex = ref(-1);
const activeZone = ref('search'); // 'search' | 'cart' | 'customer' | 'payment'
const showShortcutModal = ref(false);
const showCancelConfirmModal = ref(false);
const editingQtyIndex = ref(-1);
const editingQtyValue = ref(1);

const searchQuery = ref('');
const selectedCategory = ref('');
const currentPage = ref(props.pagination?.current_page || 1);
const paginationMeta = ref(props.pagination || {
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    from: 0,
    to: 0,
});
const loadedMedicines = ref(props.medicines || []);
const isSearching = ref(false);

watch(() => props.medicines, (newMeds) => {
    loadedMedicines.value = newMeds || [];
    searchIndex.value = 0;
});

watch(() => props.pagination, (newMeta) => {
    if (newMeta) paginationMeta.value = newMeta;
});

let searchTimeout = null;
const performSearch = (page = 1, silent = false) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    if (!silent) isSearching.value = true;
    currentPage.value = page;
    const delay = silent ? 0 : 200;
    searchTimeout = setTimeout(async () => {
        try {
            const res = await axios.get(route('pos.search'), {
                params: {
                    search: searchQuery.value,
                    category: selectedCategory.value,
                    page: page,
                    per_page: 10,
                }
            });
            loadedMedicines.value = res.data.data || [];
            paginationMeta.value = res.data;
            searchIndex.value = 0;
        } catch (e) {
            console.error('Failed to search medicines', e);
        } finally {
            if (!silent) isSearching.value = false;
        }
    }, delay);
};

const goToPage = (page) => {
    if (page < 1 || page > paginationMeta.value.last_page || page === currentPage.value) return;
    performSearch(page);
};

watch([searchQuery, selectedCategory], () => {
    searchIndex.value = 0;
    performSearch(1);
});

const filteredMedicines = computed(() => {
    return loadedMedicines.value;
});
const cart = ref([]);
const selectedCustomer = ref('');
const discount = ref(0);
const taxPercent = ref(0);
const paymentMethod = ref('cash');
const paidAmount = ref(0);
const isProcessing = ref(false);
const receiptData = ref(null);

const showConfirmModal = ref(false);

const getInitialMasterShift = () => {
    if (!props.masterShifts || props.masterShifts.length === 0) return null;
    const now = new Date();
    const curStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0') + ':00';

    const matched = props.masterShifts.find(s => {
        const start = s.start_time;
        const end = s.end_time;
        if (start <= end) {
            return curStr >= start && curStr <= end;
        } else {
            return curStr >= start || curStr <= end;
        }
    });

    return matched || props.masterShifts[0];
};

const initialMasterShift = getInitialMasterShift();

const activeShift = ref(null);
const showOpenShiftModal = ref(false);
const openShiftForm = useForm({
    master_shift_id: initialMasterShift ? initialMasterShift.id : '',
    shift_name: initialMasterShift ? initialMasterShift.name : 'Shift Pagi',
    opening_cash: 500000,
    outlet_id: '',
});

const selectedMasterShiftInPos = computed(() => {
    if (!props.masterShifts || !openShiftForm.master_shift_id) return null;
    return props.masterShifts.find(s => s.id == openShiftForm.master_shift_id) || null;
});

const isPosShiftTimeMismatch = computed(() => {
    const ms = selectedMasterShiftInPos.value;
    if (!ms || !ms.start_time || !ms.end_time) return false;

    const now = new Date();
    const curStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0') + ':00';

    const start = ms.start_time;
    const end = ms.end_time;

    if (start <= end) {
        return curStr < start || curStr > end;
    } else {
        return curStr < start && curStr > end;
    }
});

const submitOpenShift = () => {
    const ms = selectedMasterShiftInPos.value;
    if (ms) {
        openShiftForm.shift_name = ms.name;
    }

    openShiftForm.post(route('shifts.open'), {
        onSuccess: () => {
            showOpenShiftModal.value = false;
            showSuccess('Shift Berhasil Dibuka!', `Shift ${openShiftForm.shift_name} telah aktif.`);
            fetchActiveShift();
            focusSearchInput();
        },
        onError: (errors) => {
            const firstErr = Object.values(errors)[0] || 'Gagal membuka shift.';
            showError('Gagal Membuka Shift', firstErr);
        }
    });
};

const fetchActiveShift = async () => {
    try {
        const res = await axios.get(route('shifts.active'));
        activeShift.value = res.data.active_shift;
    } catch (e) {
        console.error('Failed to fetch active shift', e);
    }
};

const findMasterShift = (activeS) => {
    if (!activeS) return null;
    if (activeS.master_shift) return activeS.master_shift;

    const sName = (activeS.shift_name || '').toLowerCase().trim();
    if (!props.masterShifts || props.masterShifts.length === 0) return null;

    if (activeS.master_shift_id) {
        const found = props.masterShifts.find(s => s.id == activeS.master_shift_id);
        if (found) return found;
    }

    return props.masterShifts.find(s => {
        const mName = s.name.toLowerCase().trim();
        return mName === sName || mName.includes(sName) || sName.includes(mName);
    }) || null;
};

const isShiftOverdue = computed(() => {
    if (!activeShift.value) return false;

    const ms = findMasterShift(activeShift.value);
    if (!ms || !ms.start_time || !ms.end_time) return false;

    const now = new Date();
    const curStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0') + ':00';

    const start = ms.start_time;
    const end = ms.end_time;

    if (start <= end) {
        return curStr < start || curStr > end;
    } else {
        return curStr < start && curStr > end;
    }
});

const shiftOverdueDetails = computed(() => {
    if (!activeShift.value) return null;
    const ms = findMasterShift(activeShift.value);

    const now = new Date();
    const curTimeStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');

    return {
        shiftName: activeShift.value.shift_name,
        masterName: ms ? ms.name : activeShift.value.shift_name,
        startTime: ms ? ms.start_time.substring(0, 5) : '-',
        endTime: ms ? ms.end_time.substring(0, 5) : '-',
        currentTime: curTimeStr,
    };
});

const selectedPrescription = ref(null);
const showPrescriptionModal = ref(false);

const selectPrescription = (rx) => {
    selectedPrescription.value = rx;
    if (rx.customer_id) selectedCustomer.value = rx.customer_id;
    
    if (rx.items && rx.items.length > 0) {
        cart.value = [];
        rx.items.forEach(item => {
            const med = props.medicines.find(m => m.kode === item.medicine_id);
            cart.value.push({
                kode: item.medicine_id,
                nama: item.medicine_name || (med ? med.nama : item.medicine_id),
                harga: item.unit_price ? Number(item.unit_price) : (med ? Number(med.harga) : 0),
                quantity: item.quantity || 1,
                maxStok: med ? med.stok : (item.stock || 999),
            });
        });
    }

    showSuccess('Resep Dokter Dimuat!', `Resep ${rx.prescription_number} berhasil dihubungkan.`);
    showPrescriptionModal.value = false;
    focusSearchInput();
};

const clearPrescription = () => {
    selectedPrescription.value = null;
    showWarning('Resep Dilepas', 'Transaksi dialihkan ke Penjualan Bebas.');
};

// =========================================================
// KEYBOARD SHORTCUTS & FOCUS MANAGEMENT HELPER METHODS
// =========================================================

const focusSearchInput = () => {
    activeZone.value = 'search';
    nextTick(() => {
        if (searchInputRef.value) {
            searchInputRef.value.focus();
            searchInputRef.value.select?.();
        }
    });
};

const focusCart = () => {
    if (cart.value.length > 0) {
        activeZone.value = 'cart';
        if (cartIndex.value < 0 || cartIndex.value >= cart.value.length) {
            cartIndex.value = 0;
        }
        if (document.activeElement && typeof document.activeElement.blur === 'function') {
            document.activeElement.blur();
        }
    } else {
        showWarning('Keranjang Kosong', 'Tambahkan produk ke keranjang terlebih dahulu (F1).');
        focusSearchInput();
    }
};

const focusPayment = () => {
    activeZone.value = 'payment';
    nextTick(() => {
        if (paidInputRef.value) {
            paidInputRef.value.focus();
            paidInputRef.value.select?.();
        }
    });
};

const scrollToSearchItem = (idx) => {
    const listEl = document.querySelector('.pos-medicine-list');
    const items = listEl?.querySelectorAll('.pos-item-row');
    if (items && items[idx]) {
        items[idx].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
};

const handleSearchEnter = () => {
    if (!loadedMedicines.value || loadedMedicines.value.length === 0) return;

    const trimmedQuery = searchQuery.value.trim().toLowerCase();
    
    // Check exact barcode or code match first (for barcode scanner)
    const exactMatch = loadedMedicines.value.find(m => 
        (m.kode && m.kode.toLowerCase() === trimmedQuery) ||
        (m.barcode && m.barcode.toLowerCase() === trimmedQuery)
    );

    let targetMedicine = null;
    if (exactMatch) {
        targetMedicine = exactMatch;
    } else if (searchIndex.value >= 0 && searchIndex.value < loadedMedicines.value.length) {
        targetMedicine = loadedMedicines.value[searchIndex.value];
    } else if (loadedMedicines.value.length === 1) {
        targetMedicine = loadedMedicines.value[0];
    }

    if (targetMedicine) {
        addToCart(targetMedicine);
        searchQuery.value = '';
        searchIndex.value = 0;
    }
};

const startEditCartQty = (idx) => {
    editingQtyIndex.value = idx;
    editingQtyValue.value = cart.value[idx].quantity;
    activeZone.value = 'cart';
    nextTick(() => {
        if (inlineQtyInputRef.value && inlineQtyInputRef.value[0]) {
            inlineQtyInputRef.value[0].focus();
            inlineQtyInputRef.value[0].select();
        }
    });
};

const saveCartQty = (idx) => {
    if (editingQtyIndex.value === -1) return;
    const val = Number(editingQtyValue.value);
    if (isNaN(val) || val <= 0) {
        removeCartItem(idx);
    } else {
        const item = cart.value[idx];
        if (val > item.maxStok) {
            showWarning('Stok Tidak Cukup!', `Stok ${item.nama} hanya tersisa ${item.maxStok} unit.`);
            item.quantity = item.maxStok;
        } else {
            item.quantity = val;
        }
    }
    editingQtyIndex.value = -1;
    activeZone.value = 'cart';
};

const cancelEditCartQty = () => {
    editingQtyIndex.value = -1;
    activeZone.value = 'cart';
};

const removeCartItem = (idx) => {
    cart.value.splice(idx, 1);
    if (cart.value.length === 0) {
        cartIndex.value = -1;
        focusSearchInput();
    } else {
        cartIndex.value = Math.min(idx, cart.value.length - 1);
        activeZone.value = 'cart';
    }
};

const promptCancelTransaction = () => {
    if (cart.value.length === 0) return;
    showCancelConfirmModal.value = true;
};

const cancelTransactionConfirmed = () => {
    showCancelConfirmModal.value = false;
    cart.value = [];
    selectedCustomer.value = '';
    selectedPrescription.value = null;
    discount.value = 0;
    taxPercent.value = 0;
    paidAmount.value = 0;
    showSuccess('Transaksi Dibatalkan', 'Seluruh item keranjang telah dibersihkan.');
    focusSearchInput();
};

const resetTransaction = () => {
    cart.value = [];
    selectedCustomer.value = '';
    selectedPrescription.value = null;
    discount.value = 0;
    taxPercent.value = 0;
    paidAmount.value = 0;
    searchQuery.value = '';
    searchIndex.value = 0;
    cartIndex.value = -1;
    showConfirmModal.value = false;
    showCancelConfirmModal.value = false;
    showShortcutModal.value = false;
    focusSearchInput();
};

const closeReceiptModal = () => {
    const modalEl = document.getElementById('receiptModal');
    if (modalEl) {
        const bsModal = bootstrap.Modal.getInstance(modalEl);
        if (bsModal) bsModal.hide();
    }
};

const isEditingText = (e) => {
    const activeEl = document.activeElement;
    if (!activeEl) return false;
    const tag = activeEl.tagName.toUpperCase();
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
        if (activeEl === searchInputRef.value && activeZone.value === 'search') return false;
        return true;
    }
    return false;
};

const handleGlobalKeydown = (e) => {
    // 1. Receipt Modal Key Handling
    const receiptModalEl = document.getElementById('receiptModal');
    const isReceiptModalOpen = receiptModalEl && receiptModalEl.classList.contains('show');
    if (isReceiptModalOpen) {
        if (e.key === 'Enter') {
            e.preventDefault();
            printReceipt();
        } else if (e.key === 'Escape' || e.key.toLowerCase() === 'n' || (e.ctrlKey && e.key.toLowerCase() === 'n')) {
            e.preventDefault();
            closeReceiptModal();
            resetTransaction();
        }
        return;
    }

    // 2. Shortcut Modal Handling
    if (showShortcutModal.value) {
        if (e.key === 'Escape') {
            e.preventDefault();
            showShortcutModal.value = false;
            focusSearchInput();
        }
        return;
    }

    // 3. Cancel Confirmation Modal Handling
    if (showCancelConfirmModal.value) {
        if (e.key === 'Escape') {
            e.preventDefault();
            showCancelConfirmModal.value = false;
            focusSearchInput();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            cancelTransactionConfirmed();
        }
        return;
    }

    // 4. Final Transaction Confirmation Modal Handling
    if (showConfirmModal.value) {
        if (e.key === 'Escape') {
            e.preventDefault();
            showConfirmModal.value = false;
            focusSearchInput();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            executeFinalCheckout();
        }
        return;
    }

    // 5. Open Shift or Prescription Modal
    if (showOpenShiftModal.value || showPrescriptionModal.value) {
        if (e.key === 'Escape') {
            e.preventDefault();
            showOpenShiftModal.value = false;
            showPrescriptionModal.value = false;
            focusSearchInput();
        }
        return;
    }

    // 6. Global Ctrl Shortcuts
    if (e.ctrlKey && e.shiftKey && e.key.toUpperCase() === 'X') {
        e.preventDefault();
        promptCancelTransaction();
        return;
    }

    if (e.ctrlKey && e.key.toLowerCase() === 'n') {
        e.preventDefault();
        resetTransaction();
        return;
    }

    if ((e.ctrlKey && e.key === '/') || (e.key === '?' && !isEditingText(e))) {
        e.preventDefault();
        showShortcutModal.value = !showShortcutModal.value;
        return;
    }

    // 7. Function Keys: F1 - F10 & Quick Cash Shortcuts
    if (e.key === 'F7' || (e.altKey && (e.key.toLowerCase() === 'p' || e.key.toLowerCase() === 'u'))) {
        e.preventDefault();
        paidAmount.value = grandTotal.value;
        focusPayment();
        return;
    }

    if (e.altKey && e.key === '2') {
        e.preventDefault();
        paidAmount.value = 20000;
        focusPayment();
        return;
    }

    if (e.altKey && e.key === '5') {
        e.preventDefault();
        paidAmount.value = 50000;
        focusPayment();
        return;
    }

    if (e.altKey && e.key === '1') {
        e.preventDefault();
        paidAmount.value = 100000;
        focusPayment();
        return;
    }

    if (e.key === 'F1') {
        e.preventDefault();
        focusSearchInput();
        return;
    }

    if (e.key === 'F2') {
        e.preventDefault();
        focusCart();
        return;
    }

    if (e.key === 'F3') {
        e.preventDefault();
        activeZone.value = 'customer';
        customerSelectRef.value?.focus();
        return;
    }

    if (e.key === 'F4') {
        e.preventDefault();
        activeZone.value = 'payment';
        paymentMethodRef.value?.focus();
        return;
    }

    if (e.key === 'F6') {
        e.preventDefault();
        activeZone.value = 'payment';
        discountInputRef.value?.focus();
        discountInputRef.value?.select();
        return;
    }

    if (e.key === 'F8' || e.key === 'F9') {
        e.preventDefault();
        promptCheckoutConfirmation();
        return;
    }

    if (e.key === 'F10') {
        e.preventDefault();
        if (showConfirmModal.value) {
            executeFinalCheckout();
        } else {
            promptCheckoutConfirmation();
        }
        return;
    }

    if (e.key === 'Escape') {
        e.preventDefault();
        if (searchQuery.value) {
            searchQuery.value = '';
        }
        focusSearchInput();
        return;
    }

    const isInput = isEditingText(e);

    // Number keys 1-4 for quick payment method selection when focus is in payment zone
    if (!isInput && ['1', '2', '3', '4'].includes(e.key) && (activeZone.value === 'payment' || document.activeElement === paymentMethodRef.value)) {
        if (e.key === '1') paymentMethod.value = 'cash';
        if (e.key === '2') paymentMethod.value = 'qris';
        if (e.key === '3') paymentMethod.value = 'debit';
        if (e.key === '4') paymentMethod.value = 'transfer';
        return;
    }

    // 8. Navigation in Search vs Cart
    if (activeZone.value === 'cart') {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (cart.value.length > 0) {
                cartIndex.value = Math.min(cartIndex.value + 1, cart.value.length - 1);
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (cart.value.length > 0) {
                cartIndex.value = Math.max(cartIndex.value - 1, 0);
            }
        } else if (e.key === 'Delete') {
            e.preventDefault();
            if (cartIndex.value >= 0 && cartIndex.value < cart.value.length) {
                removeCartItem(cartIndex.value);
            }
        } else if (e.key === '+' || e.key === '=' || (e.ctrlKey && e.key === '=')) {
            e.preventDefault();
            if (cartIndex.value >= 0 && cartIndex.value < cart.value.length) {
                updateQty(cart.value[cartIndex.value], 1);
            }
        } else if (e.key === '-' || (e.ctrlKey && e.key === '-')) {
            e.preventDefault();
            if (cartIndex.value >= 0 && cartIndex.value < cart.value.length) {
                updateQty(cart.value[cartIndex.value], -1);
            }
        } else if (e.key === 'Enter' && editingQtyIndex.value === -1) {
            e.preventDefault();
            if (cartIndex.value >= 0 && cartIndex.value < cart.value.length) {
                startEditCartQty(cartIndex.value);
            }
        }
    } else if (activeZone.value === 'search' || document.activeElement === searchInputRef.value) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (loadedMedicines.value.length > 0) {
                if (searchIndex.value < loadedMedicines.value.length - 1) {
                    searchIndex.value++;
                } else {
                    searchIndex.value = 0;
                }
                scrollToSearchItem(searchIndex.value);
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (loadedMedicines.value.length > 0) {
                if (searchIndex.value > 0) {
                    searchIndex.value--;
                } else {
                    searchIndex.value = loadedMedicines.value.length - 1;
                }
                scrollToSearchItem(searchIndex.value);
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            handleSearchEnter();
        }
    }
};

let stockPollInterval = null;

onMounted(() => {
    fetchActiveShift();

    // Focus product search input automatically on open
    focusSearchInput();

    // Global Keydown Listener
    window.addEventListener('keydown', handleGlobalKeydown);

    // Auto-refresh stock numbers silently in background every 6 seconds
    stockPollInterval = setInterval(() => {
        performSearch(currentPage.value, true);
    }, 6000);

    // Auto-refresh when tab window comes back to focus
    window.addEventListener('focus', () => {
        performSearch(currentPage.value, true);
    });
});

onUnmounted(() => {
    if (stockPollInterval) clearInterval(stockPollInterval);
    window.removeEventListener('keydown', handleGlobalKeydown);
});

const addToCart = (medicine) => {
    if (medicine.stok <= 0) {
        showWarning('Stok Kosong!', `Stok ${medicine.nama} kosong.`);
        return;
    }
    const existing = cart.value.find(item => item.kode === medicine.kode);
    if (existing) {
        if (existing.quantity + 1 > medicine.stok) {
            showWarning('Stok Tidak Cukup!', `Tersisa ${medicine.stok} unit.`);
            return;
        }
        existing.quantity += 1;
    } else {
        cart.value.push({
            kode: medicine.kode,
            nama: medicine.nama,
            units: medicine.units || [],
            unit_id: medicine.units?.find(u => u.is_selling_unit)?.unit_id || null,
            unit_name: medicine.units?.find(u => u.is_selling_unit)?.unit_name || medicine.jenis_obat,
            harga: Number(medicine.units?.find(u => u.is_selling_unit)?.selling_price || medicine.harga),
            quantity: 1,
            maxStok: medicine.stok,
        });
    }

    const addedIdx = cart.value.findIndex(i => i.kode === medicine.kode);
    if (addedIdx !== -1) {
        cartIndex.value = addedIdx;
    }

    // Refocus search input for instant continuous scanning/typing
    focusSearchInput();
};

const changeCartUnit = (item, newUnitId) => {
    const u = item.units.find(x => x.unit_id == newUnitId);
    if (u) {
        item.unit_id = u.unit_id;
        item.unit_name = u.unit_name;
        item.harga = Number(u.selling_price);
    }
};

const updateQty = (item, delta) => {
    const newQty = item.quantity + delta;
    if (newQty <= 0) {
        cart.value = cart.value.filter(i => i.kode !== item.kode);
        if (cart.value.length === 0) {
            cartIndex.value = -1;
            focusSearchInput();
        } else {
            cartIndex.value = Math.min(cartIndex.value, cart.value.length - 1);
            activeZone.value = 'cart';
        }
    } else if (newQty > item.maxStok) {
        showWarning('Stok Tidak Cukup!', `Stok ${item.nama} hanya tersisa ${item.maxStok} unit.`);
    } else {
        item.quantity = newQty;
        activeZone.value = 'cart';
    }
};

const selectedCustomerDetails = computed(() => {
    if (!selectedCustomer.value) return null;
    return props.customers.find(c => c.id === selectedCustomer.value);
});

const subtotal = computed(() => {
    return cart.value.reduce((acc, item) => acc + (item.harga * item.quantity), 0);
});

const taxAmount = computed(() => {
    return (subtotal.value - discount.value) * (taxPercent.value / 100);
});

const grandTotal = computed(() => {
    const total = subtotal.value - discount.value + taxAmount.value;
    return total > 0 ? total : 0;
});

const changeAmount = computed(() => {
    return paidAmount.value - grandTotal.value;
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const promptCheckoutConfirmation = async () => {
    if (!activeShift.value) {
        showWarning('Shift Belum Dibuka!', 'Harap buka Shift Kasir terlebih dahulu.');
        showOpenShiftModal.value = true;
        return;
    }

    if (cart.value.length === 0) {
        showWarning('Keranjang Kosong', 'Harap pilih minimal 1 produk obat (F1).');
        focusSearchInput();
        return;
    }

    if (paidAmount.value < grandTotal.value && paymentMethod.value === 'cash') {
        showWarning('Uang Pembayaran Kurang', `Nominal bayar (${formatCurrency(paidAmount.value)}) kurang dari total belanja (${formatCurrency(grandTotal.value)}).`);
        focusPayment();
        return;
    }

    if (selectedCustomerDetails.value && selectedCustomerDetails.value.allergies) {
        const allergies = selectedCustomerDetails.value.allergies.toLowerCase();
        const allergicItems = cart.value.filter(item => allergies.includes(item.nama.toLowerCase()));
        if (allergicItems.length > 0) {
            showError('PERINGATAN ALERGI!', `Pasien memiliki riwayat alergi terhadap obat dalam transaksi ini.`);
            return;
        }
    }

    if (isShiftOverdue.value && shiftOverdueDetails.value) {
        const confirmOverdue = await showConfirm(
            'PERINGATAN SHIFT MELEWATI JAM OPERASIONAL!',
            `Shift ${shiftOverdueDetails.value.shiftName} (${shiftOverdueDetails.value.startTime} - ${shiftOverdueDetails.value.endTime}) saat ini telah melebih batas jam operasional. Jam Sekarang: ${shiftOverdueDetails.value.currentTime}.\n\nApakah Anda yakin tetap ingin melanjutkan transaksi pada shift ini?`
        );
        if (!confirmOverdue) return;
    }

    showConfirmModal.value = true;
};

const executeCheckout = async () => {
    showConfirmModal.value = false;
    isProcessing.value = true;

    try {
        const payloadItems = cart.value.map(item => ({
            kode: item.kode,
            nama: item.nama,
            quantity: item.quantity,
            unit_id: item.unit_id || null,
            unit_price: item.harga,
            harga: item.harga,
        }));

        const response = await axios.post(route('pos.checkout'), {
            items: payloadItems,
            customer_id: selectedCustomer.value || null,
            prescription_id: selectedPrescription.value ? selectedPrescription.value.id : null,
            discount: discount.value,
            tax: taxAmount.value,
            payment_method: paymentMethod.value,
            paid_amount: paidAmount.value,
        });

        if (response.data.success || response.data.status === 'success') {
            const resData = response.data.data || response.data.receipt;

            receiptData.value = {
                invoice_number: resData.invoice_number,
                items: cart.value.map(i => ({ ...i })),
                subtotal: subtotal.value,
                discount: discount.value,
                tax: taxAmount.value,
                grand_total: resData.grand_total,
                paid_amount: resData.paid_amount,
                change_amount: resData.change_amount,
                payment_method: paymentMethod.value,
                date: new Date().toLocaleString('id-ID'),
            };

            showSuccess('Transaksi Berhasil!', `Struk ${resData.invoice_number} telah berhasil diterbitkan.`, 1500);
            
            // Clear cart & state
            cart.value = [];
            selectedCustomer.value = '';
            selectedPrescription.value = null;
            discount.value = 0;
            taxPercent.value = 0;
            paidAmount.value = 0;
            searchQuery.value = '';
            searchIndex.value = 0;
            cartIndex.value = -1;

            // Realtime Stock Reload
            performSearch(currentPage.value, true);

            setTimeout(() => {
                const modalEl = document.getElementById('receiptModal');
                if (modalEl) {
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            }, 1500);
        }
    } catch (err) {
        showError('Transaksi Gagal', err.response?.data?.message || 'Terjadi kesalahan sistem.');
    } finally {
        isProcessing.value = false;
    }
};

const executeFinalCheckout = executeCheckout;

const printReceipt = () => {
    const printContents = document.getElementById('printableReceipt').innerHTML;
    const printWindow = window.open('', '_blank', 'height=600,width=400');
    
    printWindow.document.write('<html><head><title>Struk Pembayaran</title>');
    printWindow.document.write('<style>');
    printWindow.document.write(`
        body { font-family: 'Courier New', Courier, monospace; font-size: 12px; margin: 0; padding: 10px; width: 58mm; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .d-flex { display: flex; }
        .justify-content-between { justify-content: space-between; }
        .border-dashed { border-top: 1px dashed #000; }
    `);
    printWindow.document.write('</style></head><body>');
    printWindow.document.write(printContents);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="row">
                    <div class="col-md-7">
                        <div class="card border-0 shadow-sm p-3 mb-3" style="border-radius: 12px;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <h4 class="fw-bold mb-0 text-dark"><i class="bx bx-store me-2 text-primary"></i>Kasir / POS</h4>
                                    <Link 
                                        v-if="activeShift" 
                                        :href="route('shifts.show', activeShift.id)" 
                                        :class="isShiftOverdue ? 'badge bg-danger text-white font-monospace text-decoration-none p-2 animate-pulse shadow-xs' : 'badge bg-success font-monospace text-decoration-none p-2'"
                                        :title="isShiftOverdue ? 'Peringatan: Shift ini sudah melebih jam operasional! Klik untuk melihat detail shift' : 'Shift Aktif'"
                                    >
                                        <i :class="isShiftOverdue ? 'bx bx-time-five me-1' : 'bx bx-check-circle me-1'"></i> 
                                        Shift: {{ activeShift.shift_name }} {{ isShiftOverdue ? '(Melewati Jam!)' : '' }}
                                    </Link>
                                    <span v-else class="badge bg-warning text-dark font-monospace p-2" @click="showOpenShiftModal = true" style="cursor: pointer;">
                                        <i class="bx bx-error me-1"></i> Shift Belum Dibuka (Buka)
                                    </span>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-dark fw-bold rounded-pill shadow-xs" @click="showShortcutModal = true">
                                    <i class="bx bx-kbd me-1 text-primary"></i> ? Shortcut <span class="badge bg-secondary text-white ms-1 font-monospace">Ctrl+/</span>
                                </button>
                            </div>

                            <!-- Compact Running Text Shift Overdue Alert Bar -->
                            <div v-if="isShiftOverdue && shiftOverdueDetails" class="alert alert-danger py-1.5 px-3 mb-3 rounded-3 d-flex align-items-center justify-content-between shadow-xs overflow-hidden" style="border: 1px solid rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.12); height: 38px;">
                                <div class="d-flex align-items-center gap-2 flex-grow-1 overflow-hidden me-2" style="min-width: 0;">
                                    <span class="badge bg-danger text-white flex-shrink-0 font-monospace fw-bold px-2 py-1" style="font-size: 0.72rem;">
                                        <i class="bx bx-time-five me-1"></i> OVERDUE
                                    </span>
                                    <marquee behavior="scroll" direction="left" scrollamount="5" class="small fw-bold text-danger mb-0 flex-grow-1" style="font-size: 0.85rem;">
                                        ⚠️ PERINGATAN SHIFT KASIR MELEWATI JAM OPERASIONAL: Shift aktif "{{ shiftOverdueDetails.shiftName }}" (Jam Operasional: {{ shiftOverdueDetails.startTime }} - {{ shiftOverdueDetails.endTime }}) telah melebih batas waktu operasional (Jam Sekarang: {{ shiftOverdueDetails.currentTime }}). Disarankan untuk segera menutup shift ini dan membuka shift baru!
                                    </marquee>
                                </div>
                                <Link :href="route('shifts.show', activeShift.id)" class="btn btn-sm btn-danger fw-bold rounded-pill flex-shrink-0 px-3 py-0 fs-7 shadow-xs" style="line-height: 24px;">
                                    <i class="bx bx-log-out me-1"></i> Tutup Shift
                                </Link>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-md-8 position-relative">
                                    <input 
                                        ref="searchInputRef"
                                        type="text" 
                                        class="form-control ps-4 pe-5 rounded-3 shadow-xs pos-input-field" 
                                        v-model="searchQuery" 
                                        placeholder="[F1] Scan Barcode / Cari Nama, Kode Obat... (↑↓ Navigate | Enter: Pilih)"
                                        style="height: 42px;"
                                        @focus="activeZone = 'search'"
                                    >
                                    <span class="position-absolute top-50 end-0 translate-middle-y me-3 pointer-events-none">
                                        <i v-if="isSearching" class="bx bx-loader-alt bx-spin text-primary fs-5"></i>
                                        <i v-else class="bx bx-barcode-reader text-primary fs-4"></i>
                                    </span>
                                </div>

                                <div class="col-md-4">
                                    <select ref="categorySelectRef" class="form-select rounded-3 shadow-xs" v-model="selectedCategory" style="height: 42px;">
                                        <option value="">Semua Kategori</option>
                                        <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="card border border-secondary border-opacity-25 shadow-xs overflow-hidden mb-2" style="border-radius: 10px; min-height: 450px;">
                                
                                <div v-if="isSearching" class="p-2">
                                    <div v-for="n in 6" :key="n" class="d-flex align-items-center justify-content-between p-3 mb-2 rounded border bg-light animate-pulse">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-3" style="width: 44px; height: 44px; background: #cbd5e1;"></div>
                                            <div>
                                                <div class="rounded mb-2" style="width: 180px; height: 14px; background: #cbd5e1;"></div>
                                                <div class="rounded" style="width: 110px; height: 10px; background: #e2e8f0;"></div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded" style="width: 80px; height: 16px; background: #cbd5e1;"></div>
                                            <div class="rounded-pill" style="width: 85px; height: 32px; background: #94a3b8;"></div>
                                        </div>
                                    </div>
                                </div>

                                <div v-else-if="loadedMedicines.length > 0" class="list-group list-group-flush pos-medicine-list" style="max-height: 480px; overflow-y: auto;">
                                    <div 
                                        v-for="(med, idx) in loadedMedicines" 
                                        :key="med.kode" 
                                        class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-3 border-bottom pos-item-row"
                                        :class="{ 'active-search-item bg-primary bg-opacity-10 border-start border-primary border-4 shadow-xs': searchIndex === idx && activeZone === 'search' }"
                                        @click="addToCart(med)"
                                        @mouseenter="searchIndex = idx"
                                        style="cursor: pointer; transition: all 0.12s ease-in-out;"
                                    >
                                        <div class="d-flex align-items-center gap-3" style="min-width: 0;">
                                            <img 
                                                :src="`/Assets/Obat/${med.gambar}`" 
                                                @error="(e) => e.target.src = '/Assets/img/default-medicine.png'" 
                                                alt="obat" 
                                                class="rounded shadow-xs border flex-shrink-0" 
                                                style="width: 44px; height: 44px; object-fit: cover;"
                                            >
                                            <div class="text-truncate">
                                                <h6 class="fw-bold mb-1 text-truncate" style="font-size: 0.92rem;">
                                                    {{ med.nama }}
                                                    <span v-if="searchIndex === idx && activeZone === 'search'" class="badge bg-primary text-white ms-2 font-monospace" style="font-size: 0.68rem;">
                                                        <i class="bx bx-corner-down-left me-1"></i>Enter: Tambah
                                                    </span>
                                                </h6>
                                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                                    <span class="badge bg-secondary text-white font-monospace" style="font-size: 0.75rem;">
                                                        {{ med.kode }}
                                                    </span>
                                                    <span v-if="med.kategori" class="badge bg-primary text-white" style="font-size: 0.72rem;">
                                                        {{ med.kategori }}
                                                    </span>
                                                    <span :class="med.stok > 0 ? 'badge bg-info text-dark font-monospace fw-bold' : 'badge bg-danger text-white font-monospace fw-bold'" style="font-size: 0.75rem;">
                                                        Stok: {{ med.stok }} {{ med.jenis_obat || 'Unit' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center gap-3 ms-2 flex-shrink-0">
                                            <div class="text-end">
                                                <div class="fw-bold text-success fs-6">{{ formatCurrency(med.harga) }}</div>
                                                <small class="text-muted" style="font-size: 0.75rem;">/ {{ med.jenis_obat || 'Unit' }}</small>
                                            </div>
                                            <button 
                                                type="button" 
                                                class="btn btn-sm px-3 rounded-pill fw-semibold shadow-xs"
                                                :class="searchIndex === idx && activeZone === 'search' ? 'btn-primary shadow' : (med.stok > 0 ? 'btn-outline-primary' : 'btn-outline-secondary')"
                                                @click.stop="addToCart(med)"
                                            >
                                                <i class="bx bx-plus me-1"></i> Tambah
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="text-center py-5 text-muted">
                                    <i class="bx bx-search-alt fs-1 mb-2 text-secondary"></i>
                                    <h6 class="fw-semibold">Obat Tidak Ditemukan</h6>
                                    <p class="small text-muted mb-0">Coba kata kunci lain atau ubah filter kategori.</p>
                                </div>
                            </div>

                            <div v-if="paginationMeta && paginationMeta.last_page > 1" class="d-flex align-items-center justify-content-between flex-wrap gap-2 px-2 pt-2">
                                <div class="small text-muted fw-semibold">
                                    Halaman <strong>{{ paginationMeta.current_page }}</strong> dari <strong>{{ paginationMeta.last_page }}</strong> (Total <strong>{{ paginationMeta.total }}</strong> Obat)
                                </div>
                                <nav>
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item" :class="{ disabled: currentPage === 1 }">
                                            <button class="page-link" @click="goToPage(1)" title="Halaman Pertama">««</button>
                                        </li>
                                        <li class="page-item" :class="{ disabled: currentPage === 1 }">
                                            <button class="page-link" @click="goToPage(currentPage - 1)">‹ Prev</button>
                                        </li>
                                        <li class="page-item active">
                                            <span class="page-link px-3 font-monospace fw-bold">
                                                {{ currentPage }}
                                            </span>
                                        </li>
                                        <li class="page-item" :class="{ disabled: currentPage === paginationMeta.last_page }">
                                            <button class="page-link" @click="goToPage(currentPage + 1)">Next ›</button>
                                        </li>
                                        <li class="page-item" :class="{ disabled: currentPage === paginationMeta.last_page }">
                                            <button class="page-link" @click="goToPage(paginationMeta.last_page)" title="Halaman Terakhir">»»</button>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div 
                            class="card border-0 shadow-sm p-3 pos-cart-card" 
                            :class="{ 'border-2 border-warning': activeZone === 'cart' }"
                            style="border-radius: 12px; background: #fff;"
                        >
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold mb-0">
                                    <i class="bx bx-shopping-bag me-2 text-success"></i>Keranjang Transaksi
                                </h5>
                                <span class="badge bg-secondary font-monospace" title="Tekan F2 untuk fokus keranjang">
                                    F2 Keranjang
                                </span>
                            </div>

                            <!-- Prescription Selector & Linked Badge -->
                            <div class="mb-3">
                                <div v-if="selectedPrescription" class="p-2 px-3 rounded-3 border border-success bg-success bg-opacity-10 d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <span class="badge bg-success text-uppercase me-2"><i class="bx bx-notepad me-1"></i>Resep Dokter</span>
                                        <strong class="text-dark font-monospace">{{ selectedPrescription.prescription_number }}</strong>
                                        <div class="small text-muted mb-0">Pasien: <strong>{{ selectedPrescription.patient_name || 'Umum' }}</strong> | Dokter: {{ selectedPrescription.doctor_name || '-' }}</div>
                                    </div>
                                    <button type="button" @click="clearPrescription" class="btn btn-sm btn-outline-danger py-0 px-2" title="Lepas Resep">
                                        <i class="bx bx-x"></i> Lepas
                                    </button>
                                </div>
                                <div v-else class="d-flex justify-content-between align-items-center p-2 rounded-3 border bg-light">
                                    <span class="small text-muted fw-semibold"><i class="bx bx-notepad text-primary me-1"></i>Transaksi Resep Dokter?</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" @click="showPrescriptionModal = true">
                                        <i class="bx bx-plus me-1"></i> Pilih Resep
                                    </button>
                                </div>
                            </div>

                            <!-- Customer Selection -->
                            <div class="mb-3">
                                <label class="form-label small text-muted d-flex justify-content-between align-items-center">
                                    <span>Pelanggan / Member Apotek</span>
                                    <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.7rem;">[F3] Member</span>
                                </label>
                                <select ref="customerSelectRef" class="form-select form-select-sm mb-2" v-model="selectedCustomer" @focus="activeZone = 'customer'">
                                    <option value="">-- Umum / Non-Member --</option>
                                    <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.membership_level ? c.membership_level.toUpperCase() : 'REGULAR' }} - {{ c.points }} Pts)</option>
                                </select>

                                <!-- Member Loyalty Summary & Drug Allergy Badge -->
                                <div v-if="selectedCustomerDetails" class="p-3 rounded-3 border border-primary border-opacity-25 bg-primary bg-opacity-10 text-dark">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div>
                                            <span class="badge bg-primary text-uppercase me-1">{{ selectedCustomerDetails.membership_level || 'MEMBER' }}</span>
                                            <strong class="text-dark">{{ selectedCustomerDetails.name }}</strong>
                                        </div>
                                        <span class="badge bg-success font-monospace">{{ selectedCustomerDetails.points }} Pts</span>
                                    </div>

                                    <!-- Drug Allergy & Safety Status Badge -->
                                    <div v-if="selectedCustomerDetails.allergies" class="mt-2 p-2.5 rounded-3 bg-danger bg-opacity-10 border border-danger border-opacity-50 text-danger small shadow-xs">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <div class="fw-bold text-danger d-flex align-items-center gap-1">
                                                <i class="bx bx-shield-x fs-5 text-danger animate-pulse"></i>
                                                <span>PERINGATAN ALERGI OBAT!</span>
                                            </div>
                                            <span class="badge bg-danger text-white font-monospace">SAFETY ALERT</span>
                                        </div>
                                        <div class="p-2 rounded bg-white bg-opacity-75 border border-danger border-opacity-25 text-dark fw-bold small">
                                            <i class="bx bx-error me-1 text-danger"></i>
                                            Sensitif Terhadap: <span class="text-danger fs-6">{{ selectedCustomerDetails.allergies }}</span>
                                        </div>
                                    </div>
                                    <div v-else class="mt-2 p-2 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25 text-success small d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-1.5 fw-semibold text-success">
                                            <i class="bx bx-shield-quarter fs-5 me-1 text-success"></i>
                                            <span>Riwayat Alergi:</span>
                                            <span class="badge bg-success text-white px-2 py-1 ms-1">
                                                <i class="bx bx-check-circle me-1"></i> Tidak Ada (Aman)
                                            </span>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center small text-muted mt-2 pt-1 border-top border-secondary border-opacity-25">
                                        <span>Estimasi Poin Masuk: <strong class="text-success">+{{ Math.floor(grandTotal / 10000) }} Pts</strong></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Cart Items List with Keyboard Navigation & Shortcuts -->
                            <div class="cart-list mb-3" style="max-height: 260px; overflow-y: auto;">
                                <div 
                                    v-for="(item, idx) in cart" 
                                    :key="item.kode" 
                                    class="p-2 border-bottom pos-cart-item"
                                    :class="{ 'bg-warning bg-opacity-10 border-start border-warning border-4 rounded-2 shadow-xs active-cart-item': cartIndex === idx && activeZone === 'cart' }"
                                    @click="cartIndex = idx; activeZone = 'cart';"
                                >
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div style="flex: 1; padding-right: 8px;">
                                            <h6 class="fw-bold mb-1" style="font-size: 0.85rem;">
                                                {{ item.nama }}
                                                <span v-if="cartIndex === idx && activeZone === 'cart'" class="badge bg-warning text-dark ms-1 font-monospace" style="font-size: 0.68rem;">Terpilih</span>
                                            </h6>
                                            <div class="d-flex align-items-center gap-1">
                                                <select 
                                                    v-if="item.units && item.units.length > 0" 
                                                    class="form-select form-select-sm border-secondary-subtle py-0 px-2 rounded-2" 
                                                    style="font-size: 0.75rem; width: auto;" 
                                                    v-model="item.unit_id"
                                                    @change="changeCartUnit(item, $event.target.value)"
                                                >
                                                    <option v-for="u in item.units" :key="u.unit_id" :value="u.unit_id">
                                                        {{ u.unit_name }} ({{ formatCurrency(u.selling_price) }})
                                                    </option>
                                                </select>
                                                <span v-else class="badge bg-light text-dark border small">{{ item.unit_name || 'Unit' }}</span>
                                                <small class="text-muted ms-1">@ {{ formatCurrency(item.harga) }}</small>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-danger px-2 py-0" @click.stop="updateQty(item, -1)" title="Kurangi ( - )">-</button>
                                            
                                            <!-- Inline Quantity Edit Field -->
                                            <div v-if="editingQtyIndex === idx" style="width: 65px;">
                                                <input 
                                                    ref="inlineQtyInputRef"
                                                    type="number" 
                                                    min="1" 
                                                    :max="item.maxStok" 
                                                    class="form-control form-control-sm text-center font-monospace fw-bold p-1 border-primary" 
                                                    v-model.number="editingQtyValue"
                                                    @keydown.enter.prevent="saveCartQty(idx)"
                                                    @keydown.esc.prevent="cancelEditCartQty"
                                                    @blur="saveCartQty(idx)"
                                                />
                                            </div>
                                            <span 
                                                v-else 
                                                class="fw-bold font-monospace px-2 py-1 rounded bg-light border pointer" 
                                                style="min-width: 32px; text-align: center; cursor: pointer;"
                                                @click.stop="startEditCartQty(idx)"
                                                title="Klik / Tekan Enter untuk ketik angka Qty"
                                            >
                                                {{ item.quantity }}
                                            </span>

                                            <button type="button" class="btn btn-sm btn-outline-primary px-2 py-0" @click.stop="updateQty(item, 1)" title="Tambah ( + )">+</button>
                                            <span class="fw-bold text-dark ms-2" style="font-size: 0.9rem;">{{ formatCurrency(item.harga * item.quantity) }}</span>
                                            <button type="button" class="btn btn-sm text-muted p-0 ms-1" @click.stop="removeCartItem(idx)" title="Hapus (Delete)">
                                                <i class="bx bx-trash text-danger"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Quick Keyboard Shortcut Bar when Item Focused -->
                                    <div v-if="cartIndex === idx && activeZone === 'cart'" class="mt-1 d-flex gap-1 align-items-center font-monospace" style="font-size: 0.68rem;">
                                        <span class="badge bg-secondary">+ / - : Qty</span>
                                        <span class="badge bg-secondary">Enter : Ketik Qty</span>
                                        <span class="badge bg-danger">Del : Hapus Item</span>
                                    </div>
                                </div>

                                <div v-if="cart.length === 0" class="text-center py-4 text-muted small">
                                    <i class="bx bx-shopping-bag fs-2 d-block mb-1 text-secondary"></i>
                                    Belum ada obat dipilih. (Scan/Ketik untuk tambah)
                                </div>
                            </div>

                            <!-- Summary & Calculation -->
                            <div class="bg-light p-3 rounded mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Subtotal</span>
                                    <span class="fw-bold">{{ formatCurrency(subtotal) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="d-flex align-items-center gap-1">
                                        Diskon (Rp) 
                                        <span class="badge bg-secondary text-white font-monospace" style="font-size: 0.68rem;">[F6]</span>
                                    </span>
                                    <div style="width: 120px;">
                                        <RupiahInput ref="discountInputRef" v-model="discount" className="form-control-sm text-end" placeholder="0" @focus="activeZone = 'payment'" />
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>PPN (%)</span>
                                    <input ref="taxInputRef" type="number" class="form-control form-control-sm text-end" style="width: 70px;" v-model.number="taxPercent" @focus="activeZone = 'payment'">
                                </div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between fs-5 fw-bold text-primary">
                                    <span>Grand Total</span>
                                    <span>{{ formatCurrency(grandTotal) }}</span>
                                </div>
                            </div>

                            <!-- Payment Section (Shortcut Buttons & Keypad navigation) -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark mb-1 d-flex justify-content-between align-items-center">
                                    <span>Metode Pembayaran</span>
                                    <span class="badge bg-secondary font-monospace" style="font-size: 0.68rem;">[F4] Metode</span>
                                </label>
                                <select ref="paymentMethodRef" class="form-select form-select-md mb-3 fw-bold rounded-3 shadow-xs" v-model="paymentMethod" @focus="activeZone = 'payment'">
                                    <option value="cash">[1] 💵 Cash / Tunai</option>
                                    <option value="qris">[2] 📱 QRIS / E-Wallet</option>
                                    <option value="debit">[3] 💳 Kartu Debit</option>
                                    <option value="transfer">[4] 🏦 Bank Transfer</option>
                                </select>

                                <label class="form-label small fw-bold text-dark mb-1 d-flex justify-content-between">
                                    <span>Nominal Bayar (Rp) <span class="text-danger">*</span></span>
                                    <span class="text-primary font-monospace fw-semibold">[F8/F9] Input Uang Tunai</span>
                                </label>
                                
                                <div class="position-relative mb-2">
                                    <RupiahInput 
                                        ref="paidInputRef"
                                        v-model="paidAmount" 
                                        className="form-control-lg fw-extrabold text-end text-success shadow-xs rounded-3 border-2" 
                                        placeholder="0"
                                        style="font-size: 1.85rem; height: 58px; font-weight: 800; letter-spacing: 0.5px;" 
                                        @focus="activeZone = 'payment'"
                                        @keydown.enter.prevent="promptCheckoutConfirmation"
                                    />
                                </div>

                                <!-- Quick Cash Shortcut Buttons -->
                                <div class="d-flex gap-1 flex-wrap mb-3">
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-outline-success fw-bold flex-fill rounded-pill d-flex align-items-center justify-content-center gap-1"
                                        @click="paidAmount = grandTotal; focusPayment();"
                                        title="Isi otomatis sesuai Total Belanja (F7 / Alt+P)"
                                    >
                                        <span>⚡ Uang Pas</span>
                                        <span class="badge bg-success text-white font-monospace" style="font-size: 0.65rem;">F7</span>
                                    </button>
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-outline-secondary fw-semibold flex-fill rounded-pill d-flex align-items-center justify-content-center gap-1"
                                        @click="paidAmount = 20000; focusPayment();"
                                        title="Input Nominal 20.000 (Alt+2)"
                                    >
                                        <span>20k</span>
                                        <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.65rem;">Alt+2</span>
                                    </button>
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-outline-secondary fw-semibold flex-fill rounded-pill d-flex align-items-center justify-content-center gap-1"
                                        @click="paidAmount = 50000; focusPayment();"
                                        title="Input Nominal 50.000 (Alt+5)"
                                    >
                                        <span>50k</span>
                                        <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.65rem;">Alt+5</span>
                                    </button>
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-outline-secondary fw-semibold flex-fill rounded-pill d-flex align-items-center justify-content-center gap-1"
                                        @click="paidAmount = 100000; focusPayment();"
                                        title="Input Nominal 100.000 (Alt+1)"
                                    >
                                        <span>100k</span>
                                        <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.65rem;">Alt+1</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Big Kembalian Display Box -->
                            <div 
                                class="p-3 rounded-3 mb-3 d-flex justify-content-between align-items-center border shadow-xs"
                                :class="changeAmount >= 0 ? 'bg-success bg-opacity-10 border-success border-opacity-25 text-success' : 'bg-danger bg-opacity-10 border-danger border-opacity-25 text-danger'"
                            >
                                <span class="fw-bold fs-6">{{ changeAmount >= 0 ? 'Kembalian:' : 'Kurang Bayar:' }}</span>
                                <span class="fw-extrabold font-monospace" style="font-size: 1.5rem; font-weight: 800;">
                                    {{ formatCurrency(Math.abs(changeAmount)) }}
                                </span>
                            </div>

                            <button 
                                type="button"
                                class="btn btn-success btn-lg w-100 shadow-sm fw-bold rounded-3 py-3 fs-5 d-flex align-items-center justify-content-center gap-2" 
                                :disabled="isProcessing || cart.length === 0" 
                                @click="promptCheckoutConfirmation"
                            >
                                <i class="bx bx-check-circle fs-4"></i>
                                <span>Bayar & Final Transaksi</span>
                                <span class="badge bg-black bg-opacity-25 text-white font-monospace ms-1">F10</span>
                            </button>

                            <div class="text-center mt-2">
                                <button type="button" class="btn btn-link btn-sm text-danger text-decoration-none" @click="promptCancelTransaction" :disabled="cart.length === 0">
                                    <i class="bx bx-x-circle me-1"></i> Batalkan Transaksi <span class="badge bg-light text-dark border font-monospace">Ctrl+Shift+X</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===== MODAL RECEIPT / STRUK PENJUALAN ===== -->
        <div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-sm">
                <div class="modal-content" v-if="receiptData">
                    <div class="modal-body p-3 font-monospace" id="printableReceipt">
                        <div class="text-center mb-2">
                            <img v-if="$page.props.app_settings?.pharmacy_logo" :src="$page.props.app_settings.pharmacy_logo" alt="Logo Apotek" style="max-height: 40px; object-fit: contain;" class="mb-1 d-block mx-auto">
                            <h5 class="fw-bold mb-0">{{ $page.props.app_settings?.pharmacy_name || 'APOTEK MEDIKA SORA' }}</h5>
                            <small class="d-block">{{ $page.props.app_settings?.pharmacy_address || 'Jl. Raya Farmasi No. 10' }}</small>
                            <small class="d-block">Telp: {{ $page.props.app_settings?.pharmacy_phone || '021-5551234' }}</small>
                            <small v-if="$page.props.app_settings?.pharmacist_name" class="d-block text-muted" style="font-size: 0.68rem;">Apoteker: {{ $page.props.app_settings.pharmacist_name }}</small>
                        </div>
                        <hr class="my-1 border-dashed">
                        <div class="small mb-2">
                            <div>No: {{ receiptData.invoice_number }}</div>
                            <div>Tgl: {{ receiptData.date }}</div>
                            <div>Metode: {{ receiptData.payment_method.toUpperCase() }}</div>
                        </div>
                        <hr class="my-1 border-dashed">
                        <div v-for="i in receiptData.items" :key="i.kode" class="small d-flex justify-content-between">
                            <span>{{ i.nama }} x{{ i.quantity }}</span>
                            <span>{{ formatCurrency(i.harga * i.quantity) }}</span>
                        </div>
                        <hr class="my-1 border-dashed">
                        <div class="small d-flex justify-content-between fw-bold">
                            <span>TOTAL</span>
                            <span>{{ formatCurrency(receiptData.grand_total) }}</span>
                        </div>
                        <div class="small d-flex justify-content-between">
                            <span>BAYAR</span>
                            <span>{{ formatCurrency(receiptData.paid_amount) }}</span>
                        </div>
                        <div class="small d-flex justify-content-between">
                            <span>KEMBALI</span>
                            <span>{{ formatCurrency(receiptData.change_amount) }}</span>
                        </div>
                        <hr class="my-1 border-dashed">
                        <div class="text-center small mt-2">
                            <p class="mb-0">Terima kasih atas kunjungan Anda!</p>
                            <small>Semoga Lekas Sembuh</small>
                        </div>
                    </div>
                    <div class="modal-footer bg-light flex-wrap justify-content-between gap-1 p-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" @click="resetTransaction">
                            Tutup <span class="badge bg-black bg-opacity-25 font-monospace ms-1">Esc</span>
                        </button>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-success btn-sm" @click="closeReceiptModal(); resetTransaction();">
                                <i class="bx bx-plus-circle me-1"></i> Baru <span class="badge bg-black bg-opacity-25 font-monospace ms-1">N</span>
                            </button>
                            <button type="button" class="btn btn-primary btn-sm fw-bold" @click="printReceipt">
                                <i class="bx bx-printer me-1"></i> Print <span class="badge bg-black bg-opacity-25 font-monospace ms-1">Enter</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== MODAL KONFIRMASI FINAL PENJUALAN (F10) ===== -->
        <div v-if="showConfirmModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.65);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-lg">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
                    <div class="modal-header bg-success text-white py-3">
                        <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                            <i class="bx bx-check-shield fs-4"></i>Konfirmasi Finalisasi Transaksi POS
                        </h5>
                        <button type="button" class="btn-close btn-close-white" @click="showConfirmModal = false; focusSearchInput();"></button>
                    </div>
                    <div class="modal-body text-dark p-4">
                        <div class="alert alert-info border-0 d-flex align-items-center mb-3 rounded-3">
                            <i class="bx bx-info-circle fs-4 me-2 flex-shrink-0"></i>
                            <div>Apakah rincian transaksi obat, jumlah unit, dan pembayaran sudah benar? Tekan <strong class="text-dark">[Enter]</strong> untuk memfinalisasi.</div>
                        </div>

                        <!-- Summary Header Boxes -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <div class="p-2.5 rounded-3 bg-light border text-center">
                                    <small class="text-muted d-block">Metode Bayar</small>
                                    <strong class="text-uppercase text-primary fs-6">{{ paymentMethod }}</strong>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-2.5 rounded-3 bg-light border text-center">
                                    <small class="text-muted d-block">Total Belanja</small>
                                    <strong class="text-dark fs-6">{{ formatCurrency(grandTotal) }}</strong>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-2.5 rounded-3 bg-light border text-center">
                                    <small class="text-muted d-block">Uang Dibayar</small>
                                    <strong class="text-success fs-6">{{ formatCurrency(paidAmount) }}</strong>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-2.5 rounded-3 bg-light border text-center">
                                    <small class="text-muted d-block">Kembalian</small>
                                    <strong class="text-dark fs-6">{{ formatCurrency(changeAmount) }}</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Table Review Items -->
                        <h6 class="fw-bold text-dark mb-2"><i class="bx bx-list-check text-primary me-1"></i>Rincian Obat Dibeli ({{ cart.length }} Item)</h6>
                        <div class="table-responsive mb-2">
                            <table class="table table-sm table-bordered align-middle">
                                <thead class="bg-light text-dark">
                                    <tr>
                                        <th style="width: 35px;" class="text-center">No</th>
                                        <th>Nama Obat</th>
                                        <th class="text-center" style="width: 70px;">Qty</th>
                                        <th class="text-end" style="width: 120px;">Harga Satuan</th>
                                        <th class="text-end" style="width: 130px;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, idx) in cart" :key="item.kode">
                                        <td class="text-center text-muted">{{ idx + 1 }}</td>
                                        <td class="fw-bold text-dark">{{ item.nama }}</td>
                                        <td class="text-center font-monospace fw-bold fs-6">{{ item.quantity }}</td>
                                        <td class="text-end text-muted">{{ formatCurrency(item.harga) }}</td>
                                        <td class="text-end fw-bold text-dark">{{ formatCurrency(item.harga * item.quantity) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="modal-footer bg-light justify-content-between p-3">
                        <button type="button" class="btn btn-secondary px-3" @click="showConfirmModal = false; focusSearchInput();">
                            <i class="bx bx-x me-1"></i> Kembali <span class="badge bg-black bg-opacity-25 font-monospace ms-1">Esc</span>
                        </button>
                        <button type="button" class="btn btn-success btn-lg fw-bold px-4 shadow-sm d-flex align-items-center gap-2" :disabled="isProcessing" @click="executeFinalCheckout">
                            <i class="bx bx-check-circle fs-5"></i>
                            <span>PROSES FINAL TRANSAKSI</span>
                            <span class="badge bg-black bg-opacity-25 text-white font-monospace">Enter</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== MODAL KONFIRMASI BATAL TRANSAKSI (Ctrl+Shift+X) ===== -->
        <div v-if="showCancelConfirmModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.65);" aria-modal="true" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title fw-bold"><i class="bx bx-error-circle me-2"></i>Batalkan Transaksi POS?</h5>
                        <button type="button" class="btn-close btn-close-white" @click="showCancelConfirmModal = false; focusSearchInput();"></button>
                    </div>
                    <div class="modal-body text-dark py-4">
                        <p class="fs-6 mb-2 text-dark">Apakah Anda yakin ingin membatalkan transaksi POS ini?</p>
                        <div class="alert alert-warning mb-0 border-0 small">
                            <i class="bx bx-info-circle me-1"></i> Seluruh {{ cart.length }} item obat yang ada di keranjang transaksi saat ini akan dihapus.
                        </div>
                    </div>
                    <div class="modal-footer bg-light justify-content-between">
                        <button type="button" class="btn btn-secondary" @click="showCancelConfirmModal = false; focusSearchInput();">
                            Kembali <span class="badge bg-black bg-opacity-25 font-monospace ms-1">Esc</span>
                        </button>
                        <button type="button" class="btn btn-danger fw-bold px-3" @click="cancelTransactionConfirmed">
                            <i class="bx bx-trash me-1"></i> Ya, Batalkan <span class="badge bg-black bg-opacity-25 font-monospace ms-1">Enter</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== MODAL BUKA SHIFT KASIR ===== -->
        <div v-if="showOpenShiftModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.6);" aria-modal="true" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title fw-bold"><i class="bx bx-time-five me-2"></i>Buka Shift Kasir Terlebih Dahulu</h5>
                        <button type="button" class="btn-close" @click="showOpenShiftModal = false"></button>
                    </div>
                    <form @submit.prevent="submitOpenShift">
                        <div class="modal-body text-dark">
                            <div class="alert alert-warning border-0 small mb-3">
                                <i class="bx bx-error-circle me-1"></i> Shift belum dibuka. Kasir wajib membuka shift terlebih dahulu sebelum dapat memproses transaksi kasir.
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted d-flex justify-content-between align-items-center">
                                    <span>Pilihan Master Shift</span>
                                    <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">
                                        <i class="bx bx-time me-1"></i>Jam Sekarang: {{ new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }} WIB
                                    </span>
                                </label>
                                <select v-model="openShiftForm.master_shift_id" class="form-select form-select-lg fw-bold text-primary">
                                    <option v-for="ms in masterShifts" :key="ms.id" :value="ms.id">
                                        {{ ms.name }} ({{ ms.start_time.substring(0,5) }} – {{ ms.end_time.substring(0,5) }} WIB)
                                    </option>
                                </select>
                                <small v-if="selectedMasterShiftInPos" class="text-muted mt-1.5 d-block">
                                    <i class="bx bx-info-circle me-1 text-primary"></i>
                                    Jadwal Operasional: <strong>{{ selectedMasterShiftInPos.start_time.substring(0,5) }} – {{ selectedMasterShiftInPos.end_time.substring(0,5) }} WIB</strong> (Toleransi: {{ selectedMasterShiftInPos.grace_minutes }} menit).
                                </small>
                            </div>

                            <!-- Alert Warning if Mismatch -->
                            <div v-if="isPosShiftTimeMismatch" class="alert alert-warning border border-warning border-opacity-50 rounded-3 p-3 mb-3 shadow-xs">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bx bx-error text-warning fs-3 flex-shrink-0"></i>
                                    <div>
                                        <strong class="text-dark d-block">⚠️ PERINGATAN JAM OPERASIONAL SHIFT!</strong>
                                        <p class="text-dark small mb-0 mt-0.5">
                                            Jam saat ini (<strong>{{ new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }} WIB</strong>) berada di luar jam operasional resmi <strong>{{ selectedMasterShiftInPos?.name }}</strong> ({{ selectedMasterShiftInPos?.start_time?.substring(0,5) }} – {{ selectedMasterShiftInPos?.end_time?.substring(0,5) }} WIB).
                                        </p>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-2 pt-2 border-top border-warning border-opacity-25">
                                    * Membuka shift di luar jam operasional akan dicatat dengan status <em>Di Luar Jadwal</em>.
                                </small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">Modal Awal Kas (Laci Kasir) - Rp</label>
                                <input type="number" v-model.number="openShiftForm.opening_cash" min="0" class="form-control form-control-lg font-monospace fw-bold text-primary" required>
                                <small class="text-muted">Masukkan modal tunai yang ada di laci kasir saat membuka shift ini.</small>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="showOpenShiftModal = false">Batal</button>
                            <button type="submit" :disabled="openShiftForm.processing" class="btn btn-warning fw-bold px-4">
                                <i class="bx bx-check-circle me-1"></i> BUKA SHIFT & MULAI
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ===== MODAL PILIH RESEP DOKTER ===== -->
        <div v-if="showPrescriptionModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.6);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="bx bx-notepad me-2"></i>Pilih Resep Dokter (Terverifikasi)</h5>
                        <button type="button" class="btn-close btn-close-white" @click="showPrescriptionModal = false; focusSearchInput();"></button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light text-dark">
                                    <tr>
                                        <th>No. Resep</th>
                                        <th>Pasien / Pelanggan</th>
                                        <th>Dokter Penanggung Jawab</th>
                                        <th>Tanggal Resep</th>
                                        <th class="text-center">Total Item</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="rx in (prescriptions || [])" :key="rx.id">
                                        <td class="fw-bold text-primary font-monospace">{{ rx.prescription_number }}</td>
                                        <td class="fw-bold text-dark">{{ rx.patient_name || 'Pasien Umum / Non-Member' }}</td>
                                        <td>{{ rx.doctor_name || 'Dokter Spesialis' }}</td>
                                        <td>{{ rx.prescription_date }}</td>
                                        <td class="text-center fw-bold">{{ rx.items ? rx.items.length : 0 }} Item</td>
                                        <td class="text-center">
                                            <button type="button" @click="selectPrescription(rx)" class="btn btn-sm btn-success fw-bold">
                                                <i class="bx bx-cart me-1"></i> Gunakan Resep
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!prescriptions || prescriptions.length === 0">
                                        <td colspan="6" class="text-center py-4 text-muted">Belum ada resep terverifikasi yang siap diproses.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="showPrescriptionModal = false; focusSearchInput();">Tutup</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== MODAL DAFTAR KEYBOARD SHORTCUT (Ctrl + /) ===== -->
        <div v-if="showShortcutModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.65);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-lg">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
                    <div class="modal-header bg-dark text-white py-3">
                        <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                            <i class="bx bx-kbd fs-4 text-primary"></i> PANDUAN KEYBOARD SHORTCUT POS KASIR
                        </h5>
                        <button type="button" class="btn-close btn-close-white" @click="showShortcutModal = false; focusSearchInput();"></button>
                    </div>
                    <div class="modal-body p-4 text-dark" style="max-height: 70vh; overflow-y: auto;">
                        <p class="text-muted small mb-3">Kasir dapat memproses transaksi dengan sangat cepat tanpa menggunakan mouse dengan menekan kombinasi tombol berikut:</p>
                        
                        <div class="row g-3">
                            <!-- Kolom 1: Navigasi & Pencarian -->
                            <div class="col-md-6">
                                <div class="card h-100 border-0 bg-light p-3 rounded-3">
                                    <h6 class="fw-bold text-primary mb-3"><i class="bx bx-search me-1"></i>1. Pencarian & Navigasi Produk</h6>
                                    <table class="table table-sm table-borderless align-middle mb-0 small">
                                        <tbody>
                                            <tr>
                                                <td style="width: 130px;"><span class="badge bg-dark font-monospace fs-7">F1</span></td>
                                                <td>Fokus Kolom Cari / Scan Produk</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-secondary font-monospace fs-7">Arrow Up / Down</span></td>
                                                <td>Pilih Hasil Pencarian Obat</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-primary font-monospace fs-7">Enter</span></td>
                                                <td>Tambahkan Produk ke Keranjang</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-secondary font-monospace fs-7">Esc</span></td>
                                                <td>Kosongkan / Tutup Pencarian</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-info text-dark font-monospace fs-7">Barcode Scan</span></td>
                                                <td>Otomatis Tambah Produk + Qty</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Kolom 2: Keranjang -->
                            <div class="col-md-6">
                                <div class="card h-100 border-0 bg-light p-3 rounded-3">
                                    <h6 class="fw-bold text-success mb-3"><i class="bx bx-shopping-bag me-1"></i>2. Navigasi Keranjang Belanja</h6>
                                    <table class="table table-sm table-borderless align-middle mb-0 small">
                                        <tbody>
                                            <tr>
                                                <td style="width: 130px;"><span class="badge bg-dark font-monospace fs-7">F2</span></td>
                                                <td>Fokus ke Area Keranjang</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-secondary font-monospace fs-7">Arrow Up / Down</span></td>
                                                <td>Pilih Item di Dalam Keranjang</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-primary font-monospace fs-7">Enter</span></td>
                                                <td>Ketik / Edit Qty Item Terpilih</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-success font-monospace fs-7">+ / Ctrl + +</span></td>
                                                <td>Tambah Qty +1</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-warning text-dark font-monospace fs-7">- / Ctrl + -</span></td>
                                                <td>Kurangi Qty -1</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-danger font-monospace fs-7">Delete</span></td>
                                                <td>Hapus Item Terpilih dari Keranjang</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Kolom 3: Fokus & Pembayaran -->
                            <div class="col-md-6">
                                <div class="card h-100 border-0 bg-light p-3 rounded-3">
                                    <h6 class="fw-bold text-warning text-dark mb-3"><i class="bx bx-credit-card me-1"></i>3. Pembayaran & Pelanggan</h6>
                                    <table class="table table-sm table-borderless align-middle mb-0 small">
                                        <tbody>
                                            <tr>
                                                <td style="width: 130px;"><span class="badge bg-dark font-monospace fs-7">F3</span></td>
                                                <td>Pilih Pelanggan / Member Apotek</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-dark font-monospace fs-7">F4</span></td>
                                                <td>Pilih Metode Pembayaran</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-secondary font-monospace fs-7">1 / 2 / 3 / 4</span></td>
                                                <td>Pilih Cash / QRIS / Debit / Transfer</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-dark font-monospace fs-7">F6</span></td>
                                                <td>Input Diskon Transaksi (Rp)</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-dark font-monospace fs-7">F8 / F9</span></td>
                                                <td>Fokus Input Nominal Pembayaran</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Kolom 4: Final Transaksi & Cetak Nota -->
                            <div class="col-md-6">
                                <div class="card h-100 border-0 bg-light p-3 rounded-3">
                                    <h6 class="fw-bold text-danger mb-3"><i class="bx bx-check-shield me-1"></i>4. Final Transaksi & Struk</h6>
                                    <table class="table table-sm table-borderless align-middle mb-0 small">
                                        <tbody>
                                            <tr>
                                                <td style="width: 130px;"><span class="badge bg-success font-monospace fs-7">F10</span></td>
                                                <td>Finalisasi Transaksi POS</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-primary font-monospace fs-7">Enter</span></td>
                                                <td>Konfirmasi Final / Cetak Struk Nota</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-info text-dark font-monospace fs-7">Ctrl + N / N</span></td>
                                                <td>Mulai Transaksi Baru Baru</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-danger font-monospace fs-7">Ctrl + Shift + X</span></td>
                                                <td>Batalkan Seluruh Transaksi POS</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-dark font-monospace fs-7">Ctrl + /</span></td>
                                                <td>Buka Modal Panduan Shortcut Ini</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-dark px-4 fw-bold" @click="showShortcutModal = false; focusSearchInput();">
                            Paham & Tutup <span class="badge bg-white text-dark font-monospace ms-1">Esc</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </LegacyLayout>
</template>

<style scoped>
.product-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
}

.pos-item-row:hover {
    background-color: #f1f5f9 !important;
}

.pos-input-field:focus {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.25) !important;
}

.pos-cart-card.border-warning {
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.3) !important;
}

.active-search-item {
    border-left: 4px solid #3b82f6 !important;
}

.active-cart-item {
    border-left: 4px solid #f59e0b !important;
}

.pointer {
    cursor: pointer;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.35; }
}

.animate-pulse {
    animation: pulse 1.2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>
