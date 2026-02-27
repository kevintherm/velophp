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
} from "@/components/ui";

const props = defineProps({
  open: {
    type: Boolean,
    required: true,
  },
  collectionName: {
    type: String,
    required: true,
  },
  record: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["update:open", "save"]);

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
        <SheetTitle>{{ record ? 'Edit' : 'Add' }} {{ collectionName }} Record</SheetTitle>
        <SheetDescription>
          Fill in the details for the new record in the {{ collectionName }} collection.
        </SheetDescription>
      </SheetHeader>

      <div class="grid gap-4 py-4">
        <!-- Mock fields for any collection -->
        <div class="grid gap-2">
          <Label for="name">Name</Label>
          <Input id="name" placeholder="Enter name..." />
        </div>
        <div class="grid gap-2">
          <Label for="email">Email</Label>
          <Input id="email" type="email" placeholder="Enter email..." />
        </div>
        <div class="grid gap-2">
          <Label for="role">Role</Label>
          <Input id="role" placeholder="Enter role..." />
        </div>

        <div class="pt-4">
            <p class="text-xs text-muted-foreground italic">
                * This is a reusable mockup. In a real application, fields would be dynamically generated based on the collection's schema.
            </p>
        </div>
      </div>

      <SheetFooter class="absolute bottom-0 left-0 right-0 p-6 bg-background border-t">
        <div class="flex gap-2 w-full">
          <Button variant="outline" class="flex-1" @click="handleClose">Cancel</Button>
          <Button class="flex-1" @click="handleSave">Save Record</Button>
        </div>
      </SheetFooter>
    </SheetContent>
  </Sheet>
</template>
