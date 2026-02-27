<script setup>
import {ref} from "vue";
import {Search, Plus, Settings, LogOut} from "lucide-vue-next";
import {Button, Input} from "@/components/ui"
import CollectionFormSheet from "@/components/CollectionFormSheet.vue";

const props = defineProps({
    activeCollection: {
        type: Object,
        required: true
    },
    collectionSearchQuery: {
        type: String,
        required: true
    },
    filteredCollections: {
        type: Array,
        required: true
    },
    handleLogout: {
        type: Function,
        required: true
    },
    state: {
        type: Object,
        required: true
    },
})

defineEmits(['update:collectionSearchQuery', 'update:activeCollection'])

const isNewCollectionSheetOpen = ref(false);
</script>

<template>
    <aside class="w-64 border-r bg-card flex flex-col">
        <div class="p-6 gap-2">
            <img :src="'/logo.svg'" alt="Velo Logo" class="h-8 w-8"/>
        </div>

        <nav class="flex-1 px-4 space-y-1">
            <div class="mb-4">
                <p class="px-2 text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">
                    Collections</p>
                <div class="mb-4 px-2">
                    <div class="relative">
                        <Search class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground"/>
                        <Input
                            :model-value="collectionSearchQuery"
                            @update:model-value="$emit('update:collectionSearchQuery', $event)"
                            placeholder="Search collections..."
                            class="pl-8 h-8 text-xs bg-muted/50 border-none"
                        />
                    </div>
                </div>

                <router-link
                    v-for="collection in filteredCollections"
                    :key="collection.id"
                    to="/"
                    @click="$emit('update:activeCollection', collection)"
                    :class="[
              'w-full flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md transition-colors',
              activeCollection.id === collection.id && $route.path === '/'
                ? 'bg-primary/10 text-primary'
                : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
            ]"
                >
                    <component :is="collection.icon" class="h-4 w-4"/>
                    {{ collection.name }}
                </router-link>
                <button
                    @click="isNewCollectionSheetOpen = true"
                    class="w-full flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md text-primary hover:bg-primary/5 mt-1">
                    <Plus class="h-4 w-4"/>
                    New Collection
                </button>
            </div>

            <div>
                <p class="px-2 text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">System</p>
                <router-link
                    to="/settings"
                    class="w-full flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                    active-class="bg-accent text-accent-foreground"
                >
                    <Settings class="h-4 w-4"/>
                    Settings
                </router-link>
            </div>
        </nav>

        <div class="p-4 border-t mt-auto">
            <div v-if="state.user" class="flex items-center gap-3 mb-4">
                <div
                    class="h-8 w-8 rounded-full bg-primary flex items-center justify-center text-primary-foreground font-bold">
                    {{ state.user.name.charAt(0) }}
                </div>
                <div class="flex-1 overflow-hidden">
                    <p class="text-sm font-medium truncate">{{ state.user.name }}</p>
                    <p class="text-xs text-muted-foreground truncate">{{ state.user.email }}</p>
                </div>
            </div>
            <Button @click="handleLogout" variant="ghost"
                    class="w-full justify-start gap-2 h-9 text-muted-foreground">
                <LogOut class="h-4 w-4"/>
                Logout
            </Button>
        </div>

        <CollectionFormSheet
            v-model:open="isNewCollectionSheetOpen"
        />
    </aside>
</template>
