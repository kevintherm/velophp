<script setup>
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetFooter,
  Button,
  Input,
  Label,
  Separator,
} from "@/components/ui";

const props = defineProps({
  open: {
    type: Boolean,
    required: true,
  },
  collection: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["update:open", "save", "delete"]);

const handleClose = () => {
  emit("update:open", false);
};

const handleSave = () => {
  // Mock save action
  emit("save");
  handleClose();
};
</script>

<template>
  <Sheet :open="open" @update:open="emit('update:open', $event)">
    <SheetContent side="right" class="sm:max-w-md">
      <SheetHeader>
        <SheetTitle>{{ collection ? 'Manage' : 'Create' }} Collection</SheetTitle>
        <SheetDescription>
          Configure your collection settings and schema definition.
        </SheetDescription>
      </SheetHeader>

      <div class="grid gap-4 py-4 overflow-y-auto max-h-[calc(100vh-200px)]">
        <div class="grid gap-2 px-1">
          <Label for="collectionName">Collection Name</Label>
          <Input id="collectionName" :value="collection?.name" placeholder="e.g. Products, Blog Posts..." />
        </div>

        <Separator class="my-4" />

        <div class="grid gap-4 px-1 pb-10">
          <h3 class="text-sm font-semibold">Schema Fields</h3>
          <div class="space-y-3">
             <div class="flex gap-2 items-center">
                <Input placeholder="Field Name" class="flex-1" value="id" disabled />
                <Input value="ID (Auto-increment)" class="flex-1" disabled />
             </div>
             <div class="flex gap-2 items-center">
                <Input placeholder="Field Name" class="flex-1" :value="collection ? 'name' : ''" />
                <Input value="String" class="flex-1" disabled />
             </div>
             <div class="flex gap-2 items-center">
                <Input placeholder="Field Name" class="flex-1" :value="collection ? 'created_at' : ''" />
                <Input value="Timestamp" class="flex-1" disabled />
             </div>
             <Button variant="ghost" class="w-full border-dashed border-2 h-10">Add Field</Button>
          </div>
        </div>
      </div>

      <SheetFooter class="absolute bottom-0 left-0 right-0 p-6 bg-background border-t">
        <div class="flex flex-col gap-3 w-full">
            <div class="flex gap-2 w-full">
              <Button variant="outline" class="flex-1" @click="handleClose">Cancel</Button>
              <Button class="flex-1" @click="handleSave">Save Collection</Button>
            </div>
            <Button v-if="collection" variant="destructive" @click="$emit('delete', collection.id)">Delete Collection</Button>
        </div>
      </SheetFooter>
    </SheetContent>
  </Sheet>
</template>
