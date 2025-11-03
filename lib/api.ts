import { API_URL } from './utils';

class ApiClient {
  private baseUrl: string;
  private token: string | null = null;

  constructor(baseUrl: string) {
    this.baseUrl = baseUrl;
    if (typeof window !== 'undefined') {
      this.token = localStorage.getItem('token');
    }
  }

  setToken(token: string) {
    this.token = token;
    if (typeof window !== 'undefined') {
      localStorage.setItem('token', token);
    }
  }

  clearToken() {
    this.token = null;
    if (typeof window !== 'undefined') {
      localStorage.removeItem('token');
    }
  }

  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> {
    // Mettre à jour le token depuis localStorage à chaque requête
    if (typeof window !== 'undefined') {
      this.token = localStorage.getItem('token');
    }

    const headers: HeadersInit = {
      'Content-Type': 'application/json',
      ...options.headers,
    };

    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }

    const response = await fetch(`${this.baseUrl}${endpoint}`, {
      ...options,
      headers,
    });

    if (!response.ok) {
      // Si 401, rediriger vers login et nettoyer le token
      if (response.status === 401) {
        this.clearToken();
        if (typeof window !== 'undefined') {
          window.location.href = '/auth/login';
        }
      }
      const error = await response.json().catch(() => ({ error: 'Erreur serveur' }));
      throw new Error(error.error || 'Une erreur est survenue');
    }

    return response.json();
  }

  // Auth
  async register(data: {
    email: string;
    password: string;
    name: string;
    organizationName: string;
    businessType: string;
    phone?: string;
  }) {
    const result = await this.request<{
      token: string;
      refreshToken: string;
      user: any;
    }>('/api/auth/register', {
      method: 'POST',
      body: JSON.stringify(data),
    });
    this.setToken(result.token);
    return result;
  }

  async login(email: string, password: string) {
    const result = await this.request<{
      token: string;
      refreshToken: string;
      user: any;
    }>('/api/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });
    this.setToken(result.token);
    return result;
  }

  // Organizations
  async getOrganization() {
    return this.request('/api/organizations');
  }

  // Services
  async getServices() {
    return this.request('/api/services');
  }

  async getService(id: string) {
    return this.request(`/api/services/${id}`);
  }

  async createService(data: any) {
    return this.request('/api/services', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateService(id: string, data: any) {
    return this.request(`/api/services/${id}`, {
      method: 'PATCH',
      body: JSON.stringify(data),
    });
  }

  async deleteService(id: string) {
    return this.request(`/api/services/${id}`, {
      method: 'DELETE',
    });
  }

  // Questions
  async getQuestionBank(businessType?: string) {
    const query = businessType ? `?businessType=${businessType}` : '';
    return this.request(`/api/questions/bank${query}`);
  }

  async generateQuestions(serviceName: string, serviceDescription?: string, businessType?: string) {
    return this.request('/api/questions/generate', {
      method: 'POST',
      body: JSON.stringify({
        serviceName,
        serviceDescription,
        businessType,
      }),
    });
  }

  // Customers
  async getCustomers(params?: { search?: string; loyaltyTier?: string }) {
    const query = new URLSearchParams(params as any).toString();
    return this.request(`/api/customers${query ? `?${query}` : ''}`);
  }

  // Appointments
  async getAppointments(params?: {
    start?: string;
    end?: string;
    status?: string;
  }) {
    const query = new URLSearchParams(params as any).toString();
    return this.request(`/api/appointments${query ? `?${query}` : ''}`);
  }

  async createAppointment(data: any) {
    return this.request('/api/appointments', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  // Dashboard
  async getDashboardToday() {
    return this.request('/api/dashboard/today');
  }

  async getDashboardWeek(start?: string) {
    const query = start ? `?start=${start}` : '';
    return this.request(`/api/dashboard/week${query}`);
  }

  async getDashboardAnalytics(month?: number, year?: number) {
    const params = new URLSearchParams();
    if (month) params.append('month', month.toString());
    if (year) params.append('year', year.toString());
    const query = params.toString();
    return this.request(`/api/dashboard/analytics${query ? `?${query}` : ''}`);
  }
}

export const api = new ApiClient(API_URL);

