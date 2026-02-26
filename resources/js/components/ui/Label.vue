<script setup>
import { Label } from "radix-vue";
import { cn } from "@/lib/utils";

const props = defineProps({
  for: { type: String, default: undefined },
  asChild: { type: Boolean, default: false },
});
</script>

<template>
  <Label
    v-bind="props"
    :class="
      cn(
        'text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70',
        $attrs.class
      )
    "
  >
    <slot />
  </Label>
</template>
