<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Footer from '@/Layouts/Footer.vue';
import AppLayout from "@/Layouts/AppLayout.vue";

defineProps({

});

</script>

<template>
    <AppLayout title="Gangster">
    <Head title="Welcome" />
    <div>
        <H1>This is a test page.</H1>
    </div>
    </AppLayout>
</template>
