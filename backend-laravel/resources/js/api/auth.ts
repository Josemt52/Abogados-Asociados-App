import axios from './axios';
import type { User } from '@/composables/useAuth';

export interface LoginCredentials {
    username: string;
    password: string;
}

export interface LoginResponse {
    user: User;
    token: string;
}

export const authAPI = {
    async login(credentials: LoginCredentials): Promise<LoginResponse> {
        const response = await axios.post('/auth/login', credentials);

        return {
            user: response.data.user,
            token: response.data.access_token,
        };
    },

    async logout(): Promise<void> {
        await axios.post('/auth/logout');
    },

    async me(): Promise<User> {
        const response = await axios.get('/auth/me');
        return response.data;
    },

    async refresh(): Promise<{ token: string }> {
        const response = await axios.post('/auth/refresh');
        return { token: response.data.access_token };
    },
};
