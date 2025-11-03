import Link from 'next/link';

export default function HomePage() {
  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
      <div className="container mx-auto px-4 py-16">
        <div className="text-center max-w-3xl mx-auto">
          <h1 className="text-5xl font-bold text-gray-900 mb-4">
            HelloLuna
          </h1>
          <p className="text-xl text-gray-600 mb-8">
            Automatisez 90% de la gestion de vos rendez-vous grâce à l'IA conversationnelle
          </p>
          
          <div className="flex gap-4 justify-center">
            <Link
              href="/auth/login"
              className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
            >
              Se connecter
            </Link>
            <Link
              href="/auth/register"
              className="px-6 py-3 bg-white text-blue-600 rounded-lg border border-blue-600 hover:bg-blue-50 transition"
            >
              Créer un compte
            </Link>
          </div>
        </div>

        <div className="mt-16 grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
          <div className="bg-white p-6 rounded-lg shadow">
            <h3 className="text-xl font-semibold mb-2">IA Conversationnelle</h3>
            <p className="text-gray-600">
              Répondez aux clients automatiquement via téléphone, WhatsApp et SMS
            </p>
          </div>
          
          <div className="bg-white p-6 rounded-lg shadow">
            <h3 className="text-xl font-semibold mb-2">Optimisation Automatique</h3>
            <p className="text-gray-600">
              L'IA comble les trous dans votre agenda et maximise votre taux de remplissage
            </p>
          </div>
          
          <div className="bg-white p-6 rounded-lg shadow">
            <h3 className="text-xl font-semibold mb-2">Service Builder</h3>
            <p className="text-gray-600">
              Créez des services personnalisés pour votre métier en quelques minutes
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}

