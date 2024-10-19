<script setup>
defineProps({
    on: Boolean,
});
</script>

<template>
    <header>
        <div class="container mx-auto flex flex-col lg:flex-row items-center justify-between p-4">
            <!-- Site Title / Logo -->
            <div class="mb-4 lg:mb-0">
                <h1 class="text-2xl font-bold uppercase">Liam Wylie</h1>
            </div>

            <!-- Navigation Links -->
            <nav>
                <ul class="flex flex-row lg:flex-row items-center gap-8 text-center">
                    <li><a href="#" class="transform inline-block h-8 transition duration-500 hover:scale-125">CVV</a></li>
                    <li><a href="#" class="transform inline-block h-8 transition duration-500 hover:scale-125">About Me</a></li>
                    <li>
                        <a href="https://github.com/liamswork" target="_blank">
                            <i class="fa-brands fa-github text-2xl transform h-8 transition duration-500 hover:scale-125"></i>
                        </a>
                    </li>
                    <li>
                        <a href="https://www.linkedin.com/in/liam-wylie/" target="_blank">
                            <i class="fa-brands fa-linkedin text-2xl transform h-8 transition duration-500 hover:scale-125"></i>
                        </a>
                    </li>
                    <li>
                        <a href="https://discord.com/users/174583425355874305" target="_blank">
                            <i class="fa-brands fa-discord text-2xl transform h-8 transition duration-500 hover:scale-125"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

</template>
