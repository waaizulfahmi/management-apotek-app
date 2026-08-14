<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    trashItems: Object,
    trashCounts: Object,
    totalTrashCount: Number,
    modules: Array,
    filters: Object,
});

const selectedModule = ref(props.filters?.module || '');
const searchQuery = ref(props.filters?.search || '');

let debounceTimer = null;
const debouncedSearch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(
            route('admin.trash.index'),
            {
                module: selectedModule.value,
                search: searchQuery.value,
            },
            { preserveState: true, replace: true }
        );
    }, 300);
};

watch([selectedModule, searchQuery], () => {
    debouncedSearch();
});

const handleRestore = (item) => {
    Swal.fire({
        title: 'Pulihkan Data Ini?',
        text: `Data '${item.name}' (${item.module}) akan dipulihkan kembali ke modul utama.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Restore Now!',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#64748b',
        customClass: { popup: 'rounded-4 shadow-lg border-0' }
    }).then((res) => {
        if (res.isConfirmed) {
            router.post(route('admin.trash.restore', { module: item.module, id: item.record_id }));
        }
    });
};

const handleForceDelete = (item) => {
    Swal.fire({
        title: 'HAPUS PERMANEN DATA INI?',
        text: `PERINGATAN! Data '${item.name}' (${item.module}) akan dihapus selamanya dari database dan TIDAK BISA DIKEMBALIKAN LAGI!`,
        icon: 'error',
        showCancelButton: true,
        confirmButtonText: 'YA, HAPUS PERMANEN!',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        customClass: { popup: 'rounded-4 shadow-lg border-0' }
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(route('admin.trash.force_delete', { module: item.module, id: item.record_id }));
        }
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-dark mb-1">
                            <i class="bx bx-trash-alt text-danger me-2"></i>Trash Manager / Data Terhapus
                        </h2>
                        <p class="text-muted small mb-0">Kelola dan pulihkan data terhapus (Soft Delete) lintas modul aplikasi management apotek.</p>
                    </div>
                    <span class="badge bg-danger fs-6 p-2 shadow-sm">
                        <i class="bx bx-archive-in me-1"></i> Total {{ totalTrashCount }} Data Terhapus
                    </span>
                </div>

                <!-- Module Summary Badges -->
                <div class="card border-0 shadow-sm p-3 mb-4" style="border-radius: 12px;">
                    <small class="text-muted d-block fw-bold text-uppercase mb-2">Ringkasan Data Terhapus Per Modul:</small>
                    <div class="d-flex flex-wrap gap-2">
                        <button 
                            @click="selectedModule = ''" 
                            class="btn btn-sm"
                            :class="selectedModule === '' ? 'btn-danger fw-bold' : 'btn-outline-secondary'"
                        >
                            Semua Modul ({{ totalTrashCount }})
                        </button>
                        <button 
                            v-for="(count, mod) in trashCounts" 
                            :key="mod" 
                            @click="selectedModule = mod"
                            class="btn btn-sm"
                            :class="selectedModule === mod ? 'btn-danger fw-bold' : 'btn-outline-danger'"
                        >
                            {{ mod }}: {{ count }}
                        </button>
                    </div>
                </div>

                <!-- Filters Bar -->
                <div class="card border-0 shadow-sm p-3 mb-4" style="border-radius: 12px;">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <select v-model="selectedModule" class="form-select">
                                <option value="">-- Semua Modul --</option>
                                <option v-for="mod in modules" :key="mod" :value="mod">{{ mod }}</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <input type="text" v-model="searchQuery" class="form-control" placeholder="Cari Nama Data, Kode, atau ID Terhapus...">
                        </div>
                    </div>
                </div>

                <!-- Trash Items Table -->
                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-4 rounded-3 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-danger text-white">
                                <tr>
                                    <th style="width: 35px;" class="text-center">No</th>
                                    <th>Modul</th>
                                    <th>Kode / Identifier</th>
                                    <th>Nama / Data</th>
                                    <th>Dihapus Oleh</th>
                                    <th>Waktu Penghapusan</th>
                                    <th class="text-center" style="width: 220px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, idx) in trashItems.data" :key="item.module + '_' + item.record_id">
                                    <td class="text-center text-muted">{{ (trashItems.current_page - 1) * trashItems.per_page + idx + 1 }}</td>
                                    <td>
                                        <span class="badge bg-secondary font-monospace text-uppercase">{{ item.module }}</span>
                                    </td>
                                    <td class="font-monospace fw-bold text-dark">{{ item.code }}</td>
                                    <td class="fw-bold text-dark">{{ item.name }}</td>
                                    <td>
                                        <i class="bx bx-user me-1 text-muted"></i>{{ item.deleted_by_name }}
                                    </td>
                                    <td>{{ formatDate(item.deleted_at) }}</td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <button @click="handleRestore(item)" class="btn btn-sm btn-success fw-bold shadow-xs">
                                                <i class="bx bx-undo me-1"></i> Restore
                                            </button>
                                            <button @click="handleForceDelete(item)" class="btn btn-sm btn-outline-danger" title="Hapus Permanen">
                                                <i class="bx bx-trash me-1"></i> Permanen
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!trashItems.data || trashItems.data.length === 0">
                                    <td colspan="7" class="text-center py-4 text-muted">Tempat sampah (Trash) kosong. Tidak ada data yang di-soft delete.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top" v-if="trashItems.links && trashItems.links.length > 3">
                        <small class="text-muted">
                            Menampilkan {{ trashItems.from }} - {{ trashItems.to }} dari {{ trashItems.total }} data terhapus
                        </small>
                        <div class="btn-group btn-group-sm">
                            <template v-for="(link, k) in trashItems.links" :key="k">
                                <div v-if="link.url === null" class="btn btn-outline-secondary disabled" v-html="link.label"></div>
                                <Link v-else :href="link.url" :class="['btn', link.active ? 'btn-danger' : 'btn-outline-secondary']" v-html="link.label" />
                            </template>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </LegacyLayout>
</template>
