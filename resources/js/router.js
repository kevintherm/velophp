import { createRouter, createWebHistory } from 'vue-router'
import Dashboard from './pages/Dashboard/Dashboard.vue'
import Login from './pages/Login.vue'
import Register from './pages/Register.vue'
import Settings from './pages/Settings.vue'

const routes = [
  { path: '/', component: Dashboard },
  { path: '/login', component: Login },
  { path: '/register', component: Register },
  { path: '/settings', component: Settings },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
