<script setup>
import { ref } from "vue";
import { Button } from "@/components/ui";
import {
    Plus,
    Sliders,
} from "lucide-vue-next";
import RecordFormSheet from "@/components/RecordFormSheet.vue";
import CollectionFormSheet from "@/components/CollectionFormSheet.vue";

defineProps({
    activeCollection: {
        type: Object,
        required: true
    }
})

const isRecordSheetOpen = ref(false);
const isCollectionSheetOpen = ref(false);
</script>

<template>
    <header class="h-16 border-b bg-card flex items-center justify-between px-8 shrink-0">
        <div class="flex items-center gap-4">
            <h1 class="text-xl font-semibold">{{ activeCollection.name }}</h1>
            <Button variant="ghost" size="sm" class="text-muted-foreground gap-2" @click="isCollectionSheetOpen = true">
                <Sliders class="h-4 w-4"/>
                Manage Collection
            </Button>
        </div>
        <div class="flex items-center gap-3">
            <Button size="sm" class="gap-1" @click="isRecordSheetOpen = true">
                <Plus class="h-4 w-4"/>
                Add Record
            </Button>
        </div>

        <RecordFormSheet
            v-model:open="isRecordSheetOpen"
            :collection-name="activeCollection.name"
        />

        <CollectionFormSheet
            v-model:open="isCollectionSheetOpen"
            :collection="activeCollection"
        />
    </header>
</template>
