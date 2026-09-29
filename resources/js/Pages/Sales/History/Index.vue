<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';

const props = defineProps({
    sales: Object,
    outlets: Array,
    selectedOutletId: Number,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const paymentMethod = ref(props.filters?.payment_method || '');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');
const outletId = ref(props.filters?.outlet_id || props.selectedOutletId || '');

let debounceTimer = null;
const debouncedFilter = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(
            route('sales.history.index'),
            {
                search: search.value,
                payment_method: paymentMethod.value,
                start_date: startDate.value,
                end_date: endDate.value,
                outlet_id: outletId.value,
            },
            { preserveState: true, replace: true }
        );
    }, 300);
};

watch([search, paymentMethod, startDate, endDate, outletId], () => {
    debouncedFilter();
});

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount || 0);
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold text-dark mb-0">
                        <i class="bx bx-history text-primary me-2"></i>Riwayat Transaksi Penjualan
                    </h2>
                    <Link :href="route('pos.index')" class="btn btn-primary shadow-sm">
                        <i class="bx bx-shopping-bag me-1"></i> Buka Kasir / POS
                    </Link>
                </div>

                <!-- Filters -->
                <div class="card border-0 shadow-sm p-3 mb-4" style="border-radius: 12px;">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bx bx-search"></i></span>
                                <input v-model="search" type="text" placeholder="Cari No Invoice / Pelanggan..." class="form-control border-start-0">
                            </div>
                        </div>
                        <div class="col-md-3" v-if="outlets && outlets.length > 0">
                            <select v-model="outletId" class="form-select">
                                <option v-for="o in outlets" :key="o.id" :value="o.id">
                                    🏢 {{ o.code }} - {{ o.name }} {{ o.is_main ? '(Pusat)' : '' }}
                                </option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select v-model="paymentMethod" class="form-select">
                                <option value="">-- Semua Metode --</option>
                                <option value="cash">Cash / Tunai</option>
                                <option value="qris">QRIS</option>
                                <option value="debit">Kartu Debit</option>
                                <option value="transfer">Transfer Bank</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2 align-items-center">
                            <input type="date" v-model="startDate" class="form-control">
                            <span class="text-muted">s/d</span>
                            <input type="date" v-model="endDate" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- Table Card -->
                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>No. Invoice</th>
                                    <th>Kasir</th>
                                    <th>Pelanggan</th>
                                    <th>Tanggal Penjualan</th>
                                    <th>Metode Bayar</th>
                                    <th class="text-end">Total Penjualan</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="s in sales.data" :key="s.id">
                                    <td class="fw-bold text-primary">{{ s.invoice_number }}</td>
                                    <td>{{ s.user?.name || s.cashier_name || '-' }}</td>
                                    <td>{{ s.customer?.name || s.customer_name || 'Umum' }}</td>
                                    <td>{{ formatDate(s.sale_date) }}</td>
                                    <td>
                                        <span class="badge bg-info text-dark text-uppercase">{{ s.payment_method }}</span>
                                    </td>
                                    <td class="text-end fw-bold text-dark">{{ formatCurrency(s.grand_total) }}</td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <Link :href="route('sales.history.show', s.id)" class="btn btn-outline-primary" title="Lihat Detail Transaksi">
                                                <i class="bx bx-show me-1"></i> Detail
                                            </Link>
                                            <Link :href="route('returns.create', { type: 'sale', reference_id: s.id })" class="btn btn-outline-danger" title="Retur Barang">
                                                <i class="bx bx-repost me-1"></i> Retur
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!sales.data || sales.data.length === 0">
                                    <td colspan="7" class="text-center py-4 text-muted">Belum ada riwayat transaksi penjualan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top" v-if="sales.links && sales.links.length > 3">
                        <small class="text-muted">
                            Menampilkan {{ sales.from }} - {{ sales.to }} dari {{ sales.total }} hasil
                        </small>
                        <div class="btn-group btn-group-sm">
                            <template v-for="(link, k) in sales.links" :key="k">
                                <div v-if="link.url === null" class="btn btn-outline-secondary disabled" v-html="link.label"></div>
                                <Link v-else :href="link.url" :class="['btn', link.active ? 'btn-primary' : 'btn-outline-secondary']" v-html="link.label" />
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>
