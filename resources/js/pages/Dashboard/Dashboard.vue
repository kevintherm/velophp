<script setup>
import {computed, ref} from "vue";
import { Input } from "@/components/ui";
import {Search} from "lucide-vue-next";
import DashboardLayout from "@/layouts/DashboardLayout.vue";
import DataTable from "@/pages/Dashboard/DataTable.vue";
import BulkActions from "@/pages/Dashboard/BulkActions.vue";

const records = ref([
    {id: 1, name: "John Doe", email: "john@example.com", role: "Admin", created_at: "2024-01-10"},
    {id: 2, name: "Jane Smith", email: "jane@example.com", role: "User", created_at: "2024-01-12"},
    {id: 3, name: "Bob Johnson", email: "bob@example.com", role: "User", created_at: "2024-01-15"},
    {id: 4, name: "Alice Brown", email: "alice@example.com", role: "Editor", created_at: "2024-01-18"},
    {id: 5, name: "Charlie Wilson", email: "charlie@example.com", role: "User", created_at: "2024-01-20"},
    {id: 6, name: "David Miller", email: "david@example.com", role: "User", created_at: "2024-01-22"},
    {id: 7, name: "Eve Davis", email: "eve@example.com", role: "Admin", created_at: "2024-01-25"},
    {id: 8, name: "Frank White", email: "frank@example.com", role: "User", created_at: "2024-01-28"},
    {id: 9, name: "Grace Lee", email: "grace@example.com", role: "Editor", created_at: "2024-02-01"},
    {id: 10, name: "Henry Ford", email: "henry@example.com", role: "User", created_at: "2024-02-05"},
]);

const selectedRecords = ref([]);
const searchQuery = ref("");
const currentPage = ref(1);
const itemsPerPage = 5;

const filteredRecords = computed(() => {
    let result = records.value;
    if (searchQuery.value) {
        result = result.filter(
            (record) =>
                record.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                record.email.toLowerCase().includes(searchQuery.value.toLowerCase())
        );
    }
    return result;
});

const totalPages = computed(() => Math.ceil(filteredRecords.value.length / itemsPerPage));

const paginatedRecords = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredRecords.value.slice(start, end);
});

const toggleAll = (checked) => {
    if (checked) {
        selectedRecords.value = paginatedRecords.value.map((r) => r.id);
    } else {
        selectedRecords.value = [];
    }
};

const toggleRecord = (id) => {
    const index = selectedRecords.value.indexOf(id);
    if (index > -1) {
        selectedRecords.value.splice(index, 1);
    } else {
        selectedRecords.value.push(id);
    }
};
</script>

<template>
    <DashboardLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div class="relative w-full">
                    <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"/>
                    <Input
                        v-model="searchQuery"
                        placeholder="Search records..."
                        class="pl-9 h-10 bg-card w-full"
                    />
                </div>
            </div>

            <!-- Floating Bulk Actions Bar -->
            <BulkActions :selected-records="selectedRecords" @clear-selection="selectedRecords = []"/>

            <DataTable
                :paginated-records="paginatedRecords"
                :selected-records="selectedRecords"
                :current-page="currentPage"
                :total-pages="totalPages"
                :items-per-page="itemsPerPage"
                :filtered-records-length="filteredRecords.length"
                @toggle-all="toggleAll"
                @toggle-record="toggleRecord"
                @prev-page="currentPage--"
                @next-page="currentPage++"
            />
        </div>
    </DashboardLayout>
</template>
