<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: Array,
});
</script>

<template>
    <div v-if="links && links.length > 3" class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
        <div class="small text-muted">
            Menampilkan data halaman ini
        </div>
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0 flex-wrap">
                <li 
                    v-for="(link, key) in links" 
                    :key="key" 
                    class="page-item" 
                    :class="{ 'active': link.active, 'disabled': !link.url }"
                >
                    <Link 
                        v-if="link.url" 
                        :href="link.url" 
                        class="page-link shadow-xs" 
                        v-html="link.label"
                        preserve-scroll
                    />
                    <span v-else class="page-link text-muted" v-html="link.label"></span>
                </li>
            </ul>
        </nav>
    </div>
</template>
