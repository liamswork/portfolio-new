<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
</script>

<template>
    <AppLayout title="Games">
        <Head title="Games" />
        <div class="container mx-auto py-8 px-4">
            <h1 class="text-3xl font-bold uppercase mb-8">Games</h1>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="h-48 rounded-lg bg-red-400"></div>
                <div class="h-48 rounded-lg bg-blue-400"></div>
                <div class="h-48 rounded-lg bg-green-400"></div>
                <div class="h-48 rounded-lg bg-yellow-400"></div>
                <div class="h-48 rounded-lg bg-purple-400"></div>
                <div class="h-48 rounded-lg bg-pink-400"></div>
                <div class="h-48 rounded-lg bg-orange-400"></div>
                <div class="h-48 rounded-lg bg-teal-400"></div>
                <div class="h-48 rounded-lg bg-indigo-400"></div>
            </div>
        </div>
    </AppLayout>
</template>
