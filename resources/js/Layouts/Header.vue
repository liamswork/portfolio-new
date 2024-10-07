<script setup>
defineProps({
    on: Boolean,
});
</script>

<template>
    <header class="grid grid-cols-7 text-white py-4">
        <div class="col-span-1 flex flex-col lg:items-center lg:justify-center">
            <h2 class="text-white uppercase">Liam Wylie</h2>
        </div>
        <div class="col-span-6 flex flex-row lg:items-center lg:justify-evenly">
            <a>Test LINK!</a>
            <a>Test Link</a>
            <a>Test Link</a>
            <a>Test Link</a>
            <a href="https://github.com/liamswork" target="_blank">
                <i class="fa-brands fa-github text-2xl"></i>
            </a>
            <a href="https://www.linkedin.com/in/liam-wylie/" target="_blank">
                <i class="fa-brands fa-linkedin text-2xl"></i>
            </a>
            <a href="https://discord.com/users/174583425355874305" target="_blank">
                <i class="fa-brands fa-discord text-2xl"></i>
            </a>
        </div>
    </header>
</template>
