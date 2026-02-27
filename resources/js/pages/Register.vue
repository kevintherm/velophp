<script setup>
import {
  Button,
  Input,
  Label,
  Card,
  CardHeader,
  CardTitle,
  CardContent,
} from "@/components/ui";
import { ref } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";
import { useAuth } from "@/lib/auth";

const router = useRouter();
const { fetchUser } = useAuth();
const name = ref("");
const email = ref("");
const password = ref("");
const password_confirmation = ref("");
const error = ref("");
const loading = ref(false);

const handleSubmit = async () => {
  error.value = "";
  loading.value = true;
  try {
    await axios.get("/sanctum/csrf-cookie");
    await axios.post("/api/register", {
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: password_confirmation.value,
    });
    await fetchUser();
    router.push("/_/");
  } catch (err) {
    error.value = err.response?.data?.message || "An error occurred during registration.";
    console.error("Register error", err);
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-background px-4">
    <Card class="w-full max-w-md">
      <CardHeader>
        <div class="flex justify-center mb-4">
          <img :src="'/logo.svg'" alt="Velo Logo" class="h-12 w-12" />
        </div>
        <CardTitle class="text-center">Create an account</CardTitle>
      </CardHeader>
      <CardContent>
        <div v-if="error" class="mb-4 text-sm font-medium text-destructive">
          {{ error }}
        </div>
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div class="space-y-2">
            <Label for="name">Name</Label>
            <Input id="name" type="text" v-model="name" required :disabled="loading" />
          </div>
          <div class="space-y-2">
            <Label for="email">Email</Label>
            <Input
              id="email"
              type="email"
              placeholder="name@example.com"
              v-model="email"
              required
              :disabled="loading"
            />
          </div>
          <div class="space-y-2">
            <Label for="password">Password</Label>
            <Input id="password" type="password" v-model="password" required :disabled="loading" />
          </div>
          <div class="space-y-2">
            <Label for="password_confirmation">Confirm Password</Label>
            <Input
              id="password_confirmation"
              type="password"
              v-model="password_confirmation"
              required
              :disabled="loading"
            />
          </div>
          <Button type="submit" class="w-full" :disabled="loading">
            {{ loading ? "Registering..." : "Register" }}
          </Button>
        </form>
        <div class="mt-4 text-center text-sm">
          Already have an account?
          <router-link to="/login" class="text-primary hover:underline">
            Login
          </router-link>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
