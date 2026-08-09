<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    rules: Array,
});

const rulesMap = reactive({});
props.rules.forEach(r => {
    rulesMap[r.key_name] = r.value;
});

const form = useForm({
    rules: rulesMap,
});

const submitSettings = () => {
    form.post(route('membership.settings.update'));
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-dark mb-0"><i class="bx bx-slider-alt text-primary me-2"></i>Pengaturan Membership & Rules Poin</h2>
                        <p class="text-muted small mb-0">Konfigurasi rate perolehan poin, nilai tukar diskon, bonus pendaftaran, dan masa kadaluarsa</p>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <form @submit.prevent="submitSettings">
                        <div class="row g-4">
                            <div class="col-md-6" v-for="r in rules" :key="r.id">
                                <div class="bg-light p-3 rounded-3 h-100">
                                    <label class="form-label fw-bold text-dark mb-1">{{ r.display_name }}</label>
                                    <input type="text" class="form-control mb-2" v-model="form.rules[r.key_name]" required>
                                    <small class="text-muted d-block">{{ r.description }}</small>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top text-end">
                            <button type="submit" class="btn btn-primary px-4 shadow-sm" :disabled="form.processing">
                                <i class="bx bx-save me-1"></i>Simpan Pengaturan Membership
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>
