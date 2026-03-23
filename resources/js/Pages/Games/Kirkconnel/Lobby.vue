<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    maps:  Array,
    games: Array,
    auth:  Object,
});

const selectedMap = ref(props.maps[0]?.id ?? null);

function createGame() {
    if (!selectedMap.value) return;
    router.post(route('kirkconnel.create'), { map_id: selectedMap.value });
}

function joinGame(gameId) {
    router.post(route('kirkconnel.join', gameId));
}

function goToGame(gameId) {
    router.get(route('kirkconnel.game', gameId));
}

function isInGame(game) {
    return game.is_player;
}
</script>

<template>
    <AppLayout title="Kirkconnel — Lobby">
        <Head title="Kirkconnel" />
        <div class="max-w-screen-xl mx-auto px-8 md:px-16 py-8">
            <h1 class="text-3xl font-bold uppercase mb-2">Kirkconnel</h1>
            <p class="text-secondary/50 mb-8">A conquest strategy game. Dominate all territories to win.</p>

            <div class="grid grid-cols-3 gap-8">
                <!-- Create game -->
                <div class="col-span-1 bg-secondary/10 border border-secondary/20 rounded-lg p-6">
                    <h2 class="font-bold uppercase text-sm text-secondary/60 mb-4">Create Game</h2>
                    <label class="block text-sm mb-1 text-secondary">Select Map</label>
                    <select v-model="selectedMap" class="w-full bg-primary border border-secondary/20 text-secondary rounded px-3 py-2 text-sm mb-4">
                        <option v-for="m in maps" :key="m.id" :value="m.id">{{ m.name }}</option>
                        <option v-if="!maps.length" disabled>No maps available</option>
                    </select>
                    <button
                        @click="createGame"
                        :disabled="!selectedMap"
                        class="w-full py-2 rounded bg-accent text-white font-bold text-sm hover:opacity-80 disabled:opacity-40"
                    >
                        Create Game
                    </button>
                    <div class="mt-4 border-t border-secondary/10 pt-4">
                        <a :href="route('kirkconnel.map-editor')" class="text-sm text-accent hover:underline">
                            + Create a new map
                        </a>
                    </div>
                </div>

                <!-- Open games -->
                <div class="col-span-2">
                    <h2 class="font-bold uppercase text-sm text-secondary/60 mb-4">Open Games</h2>
                    <div v-if="!games.length" class="text-secondary/40 text-sm">No open games. Create one!</div>
                    <div v-for="game in games" :key="game.id" class="bg-secondary/10 border border-secondary/20 rounded-lg p-4 mb-3 flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2 mb-0.5">
                                <p class="font-bold">{{ game.map?.name }}</p>
                                <span
                                    class="text-xs px-2 py-0.5 rounded-full font-bold"
                                    :style="game.status === 'active'
                                        ? 'background:rgba(34,197,94,0.15); color:#22c55e; border:1px solid #22c55e'
                                        : 'background:rgba(251,191,36,0.15); color:#fbbf24; border:1px solid #fbbf24'"
                                >{{ game.status === 'active' ? 'In Progress' : 'Waiting' }}</span>
                            </div>
                            <p class="text-sm text-secondary/50">
                                Host: {{ game.creator?.name }} &nbsp;·&nbsp;
                                {{ game.players?.length }} / {{ game.max_players }} players
                            </p>
                            <div class="flex gap-1 mt-1">
                                <span
                                    v-for="p in game.players" :key="p.id"
                                    class="text-xs px-2 py-0.5 rounded-full"
                                    :style="{ backgroundColor: p.color + '33', color: p.color, border: `1px solid ${p.color}` }"
                                >
                                    {{ p.user?.name }}
                                </span>
                            </div>
                        </div>
                        <button
                            v-if="isInGame(game)"
                            @click="goToGame(game.id)"
                            class="px-4 py-2 rounded text-sm font-bold hover:opacity-80"
                            style="background:#e94f37; color:#fff"
                        >
                            Join
                        </button>
                        <button
                            v-else-if="game.status === 'waiting'"
                            @click="joinGame(game.id)"
                            class="px-4 py-2 rounded bg-accent text-white text-sm font-bold hover:opacity-80"
                        >
                            Join
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
