<script setup>
defineProps({
    value: String,
});
</script>

<template>
    <label class="block font-medium text-sm text-secondary/70">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
