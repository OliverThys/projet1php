'use client';

import { useState, useEffect } from 'react';
import { XIcon, PlusIcon } from '@/components/icons';
import { useToast } from '@/hooks/use-toast';

interface Question {
  id: string;
  label: string;
  aiPrompt: string;
  responseType: string;
  category: string;
  options?: any;
}

interface CreateQuestionModalProps {
  open: boolean;
  onClose: () => void;
  onSuccess: () => void;
  editingQuestion?: Question | null;
}

const RESPONSE_TYPES = [
  { value: 'text', label: 'Texte libre' },
  { value: 'number', label: 'Nombre' },
  { value: 'single', label: 'Choix unique' },
  { value: 'multiple', label: 'Choix multiple' },
  { value: 'date', label: 'Date' },
  { value: 'phone', label: 'Téléphone' },
  { value: 'email', label: 'Email' },
  { value: 'address', label: 'Adresse' },
];

const CATEGORIES = [
  'identite',
  'besoin',
  'preference',
  'contrainte',
  'information',
  'autre',
];

export function CreateQuestionModal({
  open,
  onClose,
  onSuccess,
  editingQuestion,
}: CreateQuestionModalProps) {
  const { toast } = useToast();
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    label: '',
    aiPrompt: '',
    responseType: 'text' as string,
    category: 'autre',
    options: '',
  });

  useEffect(() => {
    if (editingQuestion) {
      setFormData({
        label: editingQuestion.label || '',
        aiPrompt: editingQuestion.aiPrompt || '',
        responseType: editingQuestion.responseType || 'text',
        category: editingQuestion.category || 'autre',
        options: editingQuestion.options
          ? JSON.stringify(editingQuestion.options, null, 2)
          : '',
      });
    } else {
      setFormData({
        label: '',
        aiPrompt: '',
        responseType: 'text',
        category: 'autre',
        options: '',
      });
    }
  }, [editingQuestion, open]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      let options = undefined;
      if (formData.options.trim()) {
        try {
          options = JSON.parse(formData.options);
        } catch (e) {
          toast({
            variant: 'error',
            title: 'Erreur',
            description: 'Le format JSON des options est invalide.',
          });
          setLoading(false);
          return;
        }
      }

      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:3001'}/api/questions/custom`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Authorization: `Bearer ${localStorage.getItem('token')}`,
          },
          body: JSON.stringify({
            label: formData.label,
            aiPrompt: formData.aiPrompt,
            responseType: formData.responseType,
            category: formData.category,
            options: options,
            businessTypes: [],
          }),
        }
      );

      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Erreur lors de la création');
      }

      toast({
        variant: 'success',
        title: editingQuestion ? 'Question modifiée' : 'Question créée',
        description: `La question "${formData.label}" a été ${editingQuestion ? 'modifiée' : 'créée'} avec succès.`,
      });

      onSuccess();
      onClose();
      setFormData({
        label: '',
        aiPrompt: '',
        responseType: 'text',
        category: 'autre',
        options: '',
      });
    } catch (error: any) {
      toast({
        variant: 'error',
        title: 'Erreur',
        description: error.message || 'Une erreur est survenue.',
      });
    } finally {
      setLoading(false);
    }
  };

  if (!open) return null;

  return (
    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-in fade-in-0">
      <div className="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl animate-in zoom-in-95">
        <div className="p-6 border-b sticky top-0 bg-white z-10 rounded-t-xl">
          <div className="flex justify-between items-center">
            <div>
              <h2 className="text-2xl font-bold text-gray-900">
                {editingQuestion ? 'Modifier la question' : 'Créer une question personnalisée'}
              </h2>
              <p className="text-sm text-gray-600 mt-1">
                Définissez comment l'IA posera cette question aux clients
              </p>
            </div>
            <button
              onClick={onClose}
              className="text-gray-400 hover:text-gray-600 p-2 hover:bg-gray-100 rounded-lg transition-colors"
            >
              <XIcon className="w-5 h-5" />
            </button>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="p-6">
          <div className="space-y-6">
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-2">
                Libellé de la question <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                required
                value={formData.label}
                onChange={(e) => setFormData({ ...formData, label: e.target.value })}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                placeholder="Ex: Quelle est votre couleur de cheveux préférée ?"
              />
              <p className="text-xs text-gray-500 mt-1">
                Comment la question sera affichée dans l'interface
              </p>
            </div>

            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-2">
                Prompt pour l'IA <span className="text-red-500">*</span>
              </label>
              <textarea
                required
                value={formData.aiPrompt}
                onChange={(e) => setFormData({ ...formData, aiPrompt: e.target.value })}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"
                rows={3}
                placeholder="Ex: Demande au client quelle couleur de cheveux il préfère pour sa coupe."
              />
              <p className="text-xs text-gray-500 mt-1">
                Comment l'IA formulera la question lors de la conversation
              </p>
            </div>

            <div className="grid md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Type de réponse <span className="text-red-500">*</span>
                </label>
                <select
                  required
                  value={formData.responseType}
                  onChange={(e) => setFormData({ ...formData, responseType: e.target.value })}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all bg-white"
                >
                  {RESPONSE_TYPES.map((type) => (
                    <option key={type.value} value={type.value}>
                      {type.label}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Catégorie <span className="text-red-500">*</span>
                </label>
                <select
                  required
                  value={formData.category}
                  onChange={(e) => setFormData({ ...formData, category: e.target.value })}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all bg-white"
                >
                  {CATEGORIES.map((cat) => (
                    <option key={cat} value={cat}>
                      {cat.charAt(0).toUpperCase() + cat.slice(1)}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            {(formData.responseType === 'single' || formData.responseType === 'multiple') && (
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Options (JSON)
                </label>
                <textarea
                  value={formData.options}
                  onChange={(e) => setFormData({ ...formData, options: e.target.value })}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none font-mono text-sm"
                  rows={4}
                  placeholder='["Option 1", "Option 2", "Option 3"]'
                />
                <p className="text-xs text-gray-500 mt-1">
                  Format JSON : un tableau de chaînes pour les options de choix
                </p>
              </div>
            )}
          </div>

          <div className="flex justify-end gap-4 mt-6 pt-6 border-t">
            <button
              type="button"
              onClick={onClose}
              className="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 font-medium text-gray-700 transition-all"
            >
              Annuler
            </button>
            <button
              type="submit"
              disabled={loading}
              className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium shadow-md hover:shadow-lg transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {loading ? 'Enregistrement...' : editingQuestion ? 'Modifier' : 'Créer la question'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

