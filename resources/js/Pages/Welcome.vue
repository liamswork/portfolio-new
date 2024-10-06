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
    <div class="flex flex-col justify-center items-center">
        <h1>This is a test page.</h1>
        <h2>If you're seeing this, the site is under construction.</h2>
        <h3>Frankly, it's very early in the production stage.</h3>
    </div>
    </AppLayout>
</template>
