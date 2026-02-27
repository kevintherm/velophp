<script setup>
import DashboardLayout from "@/layouts/DashboardLayout.vue";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
  Button,
  Input,
  Label,
  Switch,
  Separator,
} from "@/components/ui";
import { User, Shield, Zap, Bell, Monitor } from "lucide-vue-next";
import { useAuth } from "@/lib/auth.js";

const { state } = useAuth();
</script>

<template>
  <DashboardLayout>
    <div class="space-y-6">
      <div>
        <h2 class="text-3xl font-bold tracking-tight">Settings</h2>
        <p class="text-muted-foreground">Manage your account settings and preferences.</p>
      </div>

      <Tabs default-value="profile" class="space-y-4">
        <TabsList>
          <TabsTrigger value="profile" class="gap-2">
            <User class="h-4 w-4" />
            Profile
          </TabsTrigger>
          <TabsTrigger value="appearance" class="gap-2">
            <Monitor class="h-4 w-4" />
            Appearance
          </TabsTrigger>
          <TabsTrigger value="security" class="gap-2">
            <Shield class="h-4 w-4" />
            Security
          </TabsTrigger>
          <TabsTrigger value="api-keys" class="gap-2">
            <Zap class="h-4 w-4" />
            API Keys
          </TabsTrigger>
          <TabsTrigger value="notifications" class="gap-2">
            <Bell class="h-4 w-4" />
            Notifications
          </TabsTrigger>
        </TabsList>

        <TabsContent value="profile" class="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Profile Information</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" :value="state.user?.name" placeholder="Your name" />
              </div>
              <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input id="email" :value="state.user?.email" type="email" placeholder="Your email" />
              </div>
              <Button>Save Changes</Button>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="appearance" class="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Appearance</CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
              <div class="flex items-center justify-between">
                <div class="space-y-0.5">
                  <Label>Dark Mode</Label>
                  <p class="text-sm text-muted-foreground">Adjust the appearance of the dashboard.</p>
                </div>
                <Switch />
              </div>
              <Separator />
              <div class="flex items-center justify-between">
                <div class="space-y-0.5">
                  <Label>Compact View</Label>
                  <p class="text-sm text-muted-foreground">Show more records on the screen.</p>
                </div>
                <Switch />
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="security" class="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Security</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="grid gap-2">
                <Label for="current-password">Current Password</Label>
                <Input id="current-password" type="password" />
              </div>
              <div class="grid gap-2">
                <Label for="new-password">New Password</Label>
                <Input id="new-password" type="password" />
              </div>
              <Button>Update Password</Button>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="api-keys" class="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>API Keys</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="p-4 bg-muted rounded-lg border border-dashed text-center">
                <p class="text-sm text-muted-foreground mb-4">You haven't created any API keys yet.</p>
                <Button variant="outline" size="sm" class="gap-2">
                  <Zap class="h-4 w-4" />
                  Generate New Key
                </Button>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="notifications" class="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Notifications</CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
              <div class="flex items-center justify-between">
                <div class="space-y-0.5">
                  <Label>Email Notifications</Label>
                  <p class="text-sm text-muted-foreground">Receive updates about your account via email.</p>
                </div>
                <Switch :checked="true" />
              </div>
              <Separator />
              <div class="flex items-center justify-between">
                <div class="space-y-0.5">
                  <Label>Security Alerts</Label>
                  <p class="text-sm text-muted-foreground">Get notified of suspicious activity.</p>
                </div>
                <Switch :checked="true" />
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  </DashboardLayout>
</template>
