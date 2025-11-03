'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { api } from '@/lib/api';

export default function DashboardPage() {
  const router = useRouter();
  const [today, setToday] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadToday();
  }, []);

  const loadToday = async () => {
    try {
      const data = await api.getDashboardToday();
      setToday(data);
    } catch (error: any) {
      if (error.message.includes('401') || error.message.includes('Token')) {
        router.push('/auth/login');
      }
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="flex flex-col items-center gap-4">
          <div className="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
          <div className="text-lg font-medium text-gray-700">Chargement de votre dashboard...</div>
        </div>
      </div>
    );
  }

  const formatTime = (date: string) => {
    return new Date(date).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <nav className="bg-white shadow-sm border-b sticky top-0 z-40">
        <div className="container mx-auto px-4 py-4">
          <div className="flex justify-between items-center">
            <Link href="/dashboard" className="text-2xl font-bold text-gray-900 hover:text-blue-600 transition-colors">
              HelloLuna
            </Link>
            <div className="flex gap-6">
              <Link href="/dashboard" className="text-blue-600 hover:text-blue-700 font-semibold border-b-2 border-blue-600 pb-1">
                Dashboard
              </Link>
              <Link href="/dashboard/services" className="text-gray-700 hover:text-blue-600 font-medium transition-colors">
                Services
              </Link>
              <Link href="/dashboard/customers" className="text-gray-700 hover:text-blue-600 font-medium transition-colors">
                Clients
              </Link>
              <Link href="/dashboard/settings" className="text-gray-700 hover:text-blue-600 font-medium transition-colors">
                Paramètres
              </Link>
            </div>
          </div>
        </div>
      </nav>

      <main className="container mx-auto px-4 py-8 max-w-7xl">
        <div className="mb-8">
          <h2 className="text-3xl font-bold mb-2 text-gray-900">Aujourd'hui</h2>
          <p className="text-gray-600 text-lg">
            {today?.date && new Date(today.date).toLocaleDateString('fr-FR', {
              weekday: 'long',
              year: 'numeric',
              month: 'long',
              day: 'numeric',
            })}
          </p>
        </div>

        {today?.metrics && (
          <div className="grid md:grid-cols-4 gap-6 mb-8">
            <div className="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow border-l-4 border-blue-500">
              <div className="text-sm font-medium text-gray-600 mb-2">Total RDV</div>
              <div className="text-3xl font-bold text-gray-900">{today.metrics.total}</div>
            </div>
            <div className="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow border-l-4 border-green-500">
              <div className="text-sm font-medium text-gray-600 mb-2">Confirmés</div>
              <div className="text-3xl font-bold text-green-600">{today.metrics.confirmed}</div>
            </div>
            <div className="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow border-l-4 border-yellow-500">
              <div className="text-sm font-medium text-gray-600 mb-2">En attente</div>
              <div className="text-3xl font-bold text-yellow-600">{today.metrics.pending}</div>
            </div>
            <div className="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow border-l-4 border-purple-500">
              <div className="text-sm font-medium text-gray-600 mb-2">Taux remplissage</div>
              <div className="text-3xl font-bold text-purple-600">{today.metrics.fillRate}%</div>
            </div>
          </div>
        )}

        <div className="bg-white rounded-xl shadow-md">
          <div className="p-6 border-b">
            <h3 className="text-xl font-semibold text-gray-900">Rendez-vous d'aujourd'hui</h3>
          </div>
          <div className="divide-y">
            {today?.appointments && today.appointments.length > 0 ? (
              today.appointments.map((apt: any) => (
                <div key={apt.id} className="p-6 hover:bg-gray-50 transition-colors">
                  <div className="flex justify-between items-start">
                    <div className="flex-1">
                      <div className="font-semibold text-lg text-gray-900 mb-2">
                        {formatTime(apt.startTime)} - {apt.service.name}
                      </div>
                      <div className="text-gray-600 mb-2">
                        {apt.customer.firstName} {apt.customer.lastName} • {apt.customer.phone}
                      </div>
                      <div>
                        <span
                          className={`px-3 py-1 rounded-full text-xs font-medium ${
                            apt.status === 'confirmed'
                              ? 'bg-green-100 text-green-800'
                              : apt.status === 'pending'
                              ? 'bg-yellow-100 text-yellow-800'
                              : 'bg-gray-100 text-gray-800'
                          }`}
                        >
                          {apt.status === 'confirmed' ? 'Confirmé' : apt.status === 'pending' ? 'En attente' : apt.status}
                        </span>
                      </div>
                    </div>
                    {apt.price && (
                      <div className="text-right ml-4">
                        <div className="font-bold text-lg text-gray-900">{apt.price}€</div>
                      </div>
                    )}
                  </div>
                </div>
              ))
            ) : (
              <div className="p-12 text-center">
                <div className="text-gray-400 text-lg mb-2">Aucun rendez-vous prévu aujourd'hui</div>
                <p className="text-gray-500 text-sm">Profitez de votre journée libre !</p>
              </div>
            )}
          </div>
        </div>
      </main>
    </div>
  );
}

