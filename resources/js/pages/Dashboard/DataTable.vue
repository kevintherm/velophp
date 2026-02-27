<script setup>
import {
    ChevronLeft,
    ChevronRight,
    Edit,
    MoreVertical,
    Trash2,
} from "lucide-vue-next";
import {
    Button,
    Card,
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
    Checkbox,
} from "@/components/ui";

defineProps({
    paginatedRecords: {
        type: Array,
        required: true
    },
    selectedRecords: {
        type: Array,
        required: true
    },
    currentPage: {
        type: Number,
        required: true
    },
    totalPages: {
        type: Number,
        required: true
    },
    itemsPerPage: {
        type: Number,
        required: true
    },
    filteredRecordsLength: {
        type: Number,
        required: true
    }
})

defineEmits(['toggle-all', 'toggle-record', 'prev-page', 'next-page'])
</script>

<template>
    <Card>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-[50px]">
                            <Checkbox
                                :model-value="selectedRecords.length === paginatedRecords.length && paginatedRecords.length > 0"
                                @update:model-value="(val) => $emit('toggle-all', val)"
                            />
                        </TableHead>
                        <TableHead>ID</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead>Email</TableHead>
                        <TableHead>Role</TableHead>
                        <TableHead>Created At</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="record in paginatedRecords"
                        :key="record.id"
                        :data-state="selectedRecords.includes(record.id) ? 'selected' : ''"
                    >
                        <TableCell>
                            <Checkbox
                                :model-value="selectedRecords.includes(record.id)"
                                @update:model-value="$emit('toggle-record', record.id)"
                            />
                        </TableCell>
                        <TableCell class="font-mono text-xs text-muted-foreground">#{{
                                record.id
                            }}
                        </TableCell>
                        <TableCell class="font-medium">{{ record.name }}</TableCell>
                        <TableCell>{{ record.email }}</TableCell>
                        <TableCell>
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-secondary text-secondary-foreground">
                          {{ record.role }}
                        </span>
                        </TableCell>
                        <TableCell class="text-muted-foreground">{{ record.created_at }}</TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-2">
                                <Button variant="ghost" size="icon" class="h-8 w-8">
                                    <Edit class="h-4 w-4"/>
                                </Button>
                                <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive">
                                    <Trash2 class="h-4 w-4"/>
                                </Button>
                                <Button variant="ghost" size="icon" class="h-8 w-8">
                                    <MoreVertical class="h-4 w-4"/>
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="paginatedRecords.length === 0">
                        <TableCell colspan="7" class="h-24 text-center text-muted-foreground">
                            No results found.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-6 py-4 border-t">
            <div class="text-sm text-muted-foreground">
                Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to
                {{ Math.min(currentPage * itemsPerPage, filteredRecordsLength) }} of
                {{ filteredRecordsLength }} records
            </div>
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="icon"
                    class="h-8 w-8"
                    :disabled="currentPage === 1"
                    @click="$emit('prev-page')"
                >
                    <ChevronLeft class="h-4 w-4"/>
                </Button>
                <div class="text-sm font-medium">Page {{ currentPage }} of {{ totalPages }}</div>
                <Button
                    variant="outline"
                    size="icon"
                    class="h-8 w-8"
                    :disabled="currentPage === totalPages"
                    @click="$emit('next-page')"
                >
                    <ChevronRight class="h-4 w-4"/>
                </Button>
            </div>
        </div>
    </Card>
</template>
