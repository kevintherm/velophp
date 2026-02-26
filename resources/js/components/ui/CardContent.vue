<script setup>
import { cn } from "@/lib/utils";

defineProps({
  class: { type: null, default: "" },
});
</script>

<template>
  <div :class="cn('p-6 pt-0', $props.class)">
    <slot />
  </div>
</template>
