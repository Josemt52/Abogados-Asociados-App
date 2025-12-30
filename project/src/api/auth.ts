import axios from './axios';
import { User } from '../hooks/useAuth';

export interface LoginCredentials {
  username: string;
  password: string;
}

export interface LoginResponse {
  user: User;
  token: string;
}

export const authAPI = {
  login: async (credentials: LoginCredentials): Promise<LoginResponse> => {
    const response = await axios.post('/auth/login', credentials);
    return {
      user: response.data.user,
      token: response.data.access_token,
    };
  },

  logout: async (): Promise<void> => {
    await axios.post('/auth/logout');
  },

  me: async (): Promise<User> => {
    const response = await axios.get('/auth/me');
    return response.data;
  },

  refresh: async (): Promise<{ token: string }> => {
    const response = await axios.post('/auth/refresh');
    return {
      token: response.data.access_token,
    };
  },
};
