<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
</script>

<template>
    <AppLayout title="Games">
        <Head title="Games" />
        <div class="max-w-screen-xl mx-auto px-8 md:px-16 py-8">
            <h1 class="text-3xl font-bold uppercase mb-8">Games</h1>
            <div class="grid grid-cols-3 gap-6">
                <div class="flex flex-col overflow-hidden">
                    <div class="bg-accent flex-1 rounded-lg min-h-48"></div>
                    <div class="py-2 text-center"><a href="/games/tower">Tower Game</a></div>
                </div>
                <div class="flex flex-col overflow-hidden">
                    <div class="bg-secondary flex-1 rounded-lg min-h-48"></div>
                    <div class="py-2 text-center"><p>Hello World</p></div>
                </div>
                <div class="flex flex-col overflow-hidden">
                    <div class="bg-accent flex-1 rounded-lg min-h-48"></div>
                    <div class="py-2 text-center"><p>Hello World</p></div>
                </div>
                <div class="flex flex-col overflow-hidden">
                    <div class="bg-accent flex-1 rounded-lg min-h-48"></div>
                    <div class="py-2 text-center"><p>Hello World</p></div>
                </div>
                <div class="flex flex-col overflow-hidden">
                    <div class="bg-secondary flex-1 rounded-lg min-h-48"></div>
                    <div class="py-2 text-center"><p>Hello World</p></div>
                </div>
                <div class="flex flex-col overflow-hidden">
                    <div class="bg-accent flex-1 rounded-lg min-h-48"></div>
                    <div class="py-2 text-center"><p>Hello World</p></div>
                </div>
                <div class="flex flex-col overflow-hidden">
                    <div class="bg-accent flex-1 rounded-lg min-h-48"></div>
                    <div class="py-2 text-center"><p>Hello World</p></div>
                </div>
                <div class="flex flex-col overflow-hidden">
                    <div class="bg-secondary flex-1 rounded-lg min-h-48"></div>
                    <div class="py-2 text-center"><p>Hello World</p></div>
                </div>
                <div class="flex flex-col overflow-hidden">
                    <div class="bg-accent flex-1 rounded-lg min-h-48"></div>
                    <div class="py-2 text-center"><p>Hello World</p></div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
