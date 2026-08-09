<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';

const props = defineProps({
    journals: Object,
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold text-dark mb-0"><i class="bx bx-book text-primary me-2"></i>Jurnal Umum Keuangan (Double-Entry Accounting)</h2>
                    <span class="badge bg-success p-2"><i class="bx bx-check-circle me-1"></i> STATUS: ALL JOURNALS BALANCED</span>
                </div>

                <div v-for="j in journals.data" :key="j.id" class="card border-0 shadow-xs p-3 mb-3 bg-white rounded-3">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <div>
                            <span class="badge bg-primary me-2">{{ j.journal_number }}</span>
                            <strong class="text-dark">{{ j.notes }}</strong>
                            <small class="text-muted ms-2">| Ref: <code>{{ j.reference_number || '-' }}</code></small>
                        </div>
                        <small class="text-muted">Tanggal: {{ j.transaction_date }} | Posted by: <strong>{{ j.posted_by_name }}</strong></small>
                    </div>

                    <table class="table table-sm align-middle mb-0 small">
                        <thead class="bg-light">
                            <tr>
                                <th>Kode & Nama Akun (COA)</th>
                                <th>Memo</th>
                                <th class="text-end">Debit (Rp)</th>
                                <th class="text-end">Kredit (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in j.lines" :key="line.id">
                                <td>
                                    <span class="font-monospace fw-bold text-primary">{{ line.coa_code }}</span> — {{ line.coa_name }}
                                </td>
                                <td><small class="text-muted">{{ line.memo || '-' }}</small></td>
                                <td class="text-end fw-bold text-dark">{{ line.debit > 0 ? formatCurrency(line.debit) : '-' }}</td>
                                <td class="text-end fw-bold text-dark">{{ line.credit > 0 ? formatCurrency(line.credit) : '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="!journals.data || journals.data.length === 0" class="card p-4 text-center text-muted">
                    Belum ada jurnal keuangan posted.
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>
