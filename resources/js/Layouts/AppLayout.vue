<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Footer from "@/Layouts/Footer.vue";
import Header from "@/Layouts/Header.vue";0


defineProps({
    title: String,
});

</script>

<template>
    <div class="bg-black text-white/50">
        <Head :title="title" />
        <Header></Header>
        <div class="min-h-[80vh] w-full">
            <main class="w-full mt-6">
                <slot></slot>
            </main>
        </div>
        <Footer />
    </div>
</template>
