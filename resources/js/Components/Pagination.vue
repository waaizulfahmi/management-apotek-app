<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: Array,
    from: [Number, String],
    to: [Number, String],
    total: [Number, String],
    only: Array,
});
</script>

<template>
    <div v-if="links && links.length > 3" class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2 border-top">
        <div class="small text-muted fw-medium">
            <template v-if="from !== undefined && to !== undefined && total !== undefined">
                Menampilkan <strong class="text-dark">{{ from || 0 }}</strong> – <strong class="text-dark">{{ to || 0 }}</strong> dari <strong class="text-dark">{{ total || 0 }}</strong> data
            </template>
            <template v-else>
                Menampilkan data halaman ini
            </template>
        </div>
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0 flex-wrap gap-1">
                <li 
                    v-for="(link, key) in links" 
                    :key="key" 
                    class="page-item" 
                    :class="{ 'active': link.active, 'disabled': !link.url }"
                >
                    <Link 
                        v-if="link.url" 
                        :href="link.url" 
                        class="page-link shadow-xs rounded-2 px-3 py-1 fw-semibold" 
                        v-html="link.label"
                        preserve-scroll
                        preserve-state
                        :only="only"
                    />
                    <span v-else class="page-link text-muted rounded-2 px-3 py-1 opacity-50" v-html="link.label"></span>
                </li>
            </ul>
        </nav>
    </div>
</template>

