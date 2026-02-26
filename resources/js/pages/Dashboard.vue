<script setup>
import { onMounted } from "vue";
import { useAuth } from "@/lib/auth";
import { useRouter } from "vue-router";
import Button from "@/components/ui/Button.vue";

const { state, fetchUser, logout } = useAuth();
const router = useRouter();

onMounted(() => {
  fetchUser();
});

const handleLogout = async () => {
  await logout();
  router.push("/login");
};
</script>

<template>
  <div class="flex min-h-screen items-center justify-center">
    <div class="text-center">
      <div class="flex justify-center mb-6">
        <img :src="'/logo.svg'" alt="Velo Logo" class="h-20 w-20" />
      </div>
      <h1 class="text-4xl font-bold">Dashboard</h1>
      <p class="text-muted-foreground mt-2">Welcome to your dashboard at /</p>

      <div v-if="state.initialized" class="mt-8">
        <div v-if="state.user" class="space-y-4">
          <p class="text-lg">Logged in as: <span class="font-semibold">{{ state.user.name }}</span> ({{ state.user.email }})</p>
          <Button @click="handleLogout" variant="destructive">Logout</Button>
        </div>
        <div v-else class="space-x-4">
          <p class="mb-4 text-muted-foreground">You are not logged in.</p>
          <router-link to="/login">
            <Button variant="outline">Login</Button>
          </router-link>
          <router-link to="/register">
            <Button>Register</Button>
          </router-link>
        </div>
      </div>
      <div v-else class="mt-8">
        <p>Loading...</p>
      </div>
    </div>
  </div>
</template>
