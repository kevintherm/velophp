import { reactive, readonly } from "vue";
import axios from "axios";

const state = reactive({
  user: null,
  initialized: false,
});

export const useAuth = () => {
  const fetchUser = async () => {
    try {
      const response = await axios.get("/api/user");
      state.user = response.data.user;
    } catch (error) {
      state.user = null;
    } finally {
      state.initialized = true;
    }
  };

  const logout = async () => {
    try {
      await axios.post("/api/logout");
      state.user = null;
    } catch (error) {
      console.error("Logout error", error);
    }
  };

  return {
    state: readonly(state),
    fetchUser,
    logout,
  };
};
