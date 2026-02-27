<script setup>
import {computed, onMounted, ref} from "vue";
import {useAuth} from "@/lib/auth.js";
import {useRouter} from "vue-router";
import {
    Database,
    Users,
} from "lucide-vue-next";
import Sidebar from "@/pages/Dashboard/Sidebar.vue";
import DashboardHeader from "@/pages/Dashboard/DashboardHeader.vue";

const {state, fetchUser, logout} = useAuth();
const router = useRouter();

const collections = ref([
    {id: 1, name: "Users", icon: Users},
    {id: 2, name: "Products", icon: Database},
    {id: 3, name: "Orders", icon: Database},
    {id: 4, name: "Blog Posts", icon: Database},
]);

const activeCollection = ref(collections.value[0]);
const collectionSearchQuery = ref("");

const filteredCollections = computed(() => {
    if (!collectionSearchQuery.value) return collections.value;
    return collections.value.filter((collection) =>
        collection.name.toLowerCase().includes(collectionSearchQuery.value.toLowerCase())
    );
});

onMounted(() => {
    fetchUser();
});

const handleLogout = async () => {
    await logout();
    router.push("/login");
};
</script>

<template>
    <div class="flex h-screen bg-background overflow-hidden">
        <!-- Sidebar -->
        <Sidebar
            v-model:active-collection="activeCollection"
            v-model:collection-search-query="collectionSearchQuery"
            :filtered-collections="filteredCollections"
            :handle-logout="handleLogout"
            :state="state"
        />

        <!-- Main Content -->
        <main class="flex-1 flex flex-col min-w-0 bg-muted/20">
            <!-- Top Bar -->
            <DashboardHeader v-if="$route.path !== '/settings'" :active-collection="activeCollection" />

            <!-- Content Area -->
            <div class="flex-1 overflow-auto p-8">
                <div class="max-w-7xl mx-auto space-y-6 pb-20">
                    <slot :active-collection="activeCollection"></slot>
                </div>
            </div>
        </main>
    </div>
</template>
