'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { api } from '@/lib/api';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { 
  PlusIcon, 
  TrashIcon, 
  EditIcon,
  XIcon, 
  ChevronUpIcon, 
  ChevronDownIcon, 
  ClockIcon, 
  DollarIcon, 
  HelpCircleIcon 
} from '@/components/icons';
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  DragEndEvent,
} from '@dnd-kit/core';
import {
  arrayMove,
  sortableKeyboardCoordinates,
} from '@dnd-kit/sortable';
import { SortableQuestionItem } from '@/components/ui/sortable-question-item';
import { DroppableQuestionsZone } from '@/components/ui/droppable-questions-zone';
import { QuestionBankZone } from '@/components/ui/question-bank-zone';
import { CreateQuestionModal } from '@/components/ui/create-question-modal';

interface Question {
  id: string;
  label: string;
  aiPrompt: string;
  responseType: string;
  category: string;
  options?: any;
}

interface Service {
  id: string;
  name: string;
  description?: string;
  duration: number;
  price?: number;
  color: string;
  questions: any[];
  isActive: boolean;
}

export default function ServicesPage() {
  const router = useRouter();
  const [services, setServices] = useState<Service[]>([]);
  const [questions, setQuestions] = useState<Question[]>([]);
  const [loading, setLoading] = useState(true);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [editingServiceId, setEditingServiceId] = useState<string | null>(null);
  const [deleteDialog, setDeleteDialog] = useState<{ open: boolean; serviceId: string | null; serviceName: string }>({
    open: false,
    serviceId: null,
    serviceName: '',
  });
  const [formData, setFormData] = useState({
    name: '',
    description: '',
    duration: 30,
    price: '',
    color: '#3b82f6',
    selectedQuestions: [] as string[],
  });
  const [searchQuery, setSearchQuery] = useState('');
  const [showCreateQuestionModal, setShowCreateQuestionModal] = useState(false);
  const [editingQuestionId, setEditingQuestionId] = useState<string | null>(null);
  const [generatingQuestions, setGeneratingQuestions] = useState(false);
  const [organization, setOrganization] = useState<any>(null);

  // Optimisation des sensors pour réduire le lag
  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8, // Démarrer le drag seulement après 8px de mouvement
      },
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  useEffect(() => {
    loadData();
  }, []);

  // Synchroniser les questions des services avec la banque de questions
  useEffect(() => {
    if (services.length === 0 || questions.length === 0) return;

    // Extraire toutes les questions de tous les services
    const serviceQuestions: Question[] = [];
    services.forEach((service) => {
      if (service.questions && Array.isArray(service.questions)) {
        service.questions.forEach((q: any) => {
          const qId = typeof q === 'string' ? q : q.questionId || q.questionTemplate?.id || q.id;
          const template = typeof q === 'object' && q.questionTemplate ? q.questionTemplate : q;
          
          if (qId && template && typeof template === 'object' && template.label) {
            serviceQuestions.push({
              id: qId,
              label: template.label || '',
              aiPrompt: template.aiPrompt || template.label || '',
              responseType: template.responseType || 'text',
              category: template.category || 'autre',
              options: template.options || null,
              isSystem: template.isSystem !== false,
            });
          }
        });
      }
    });

    // Ajouter les questions manquantes à la banque
    if (serviceQuestions.length > 0) {
      setQuestions((prev) => {
        const existingIds = new Set(prev.map((q) => q.id));
        const newQuestions = serviceQuestions.filter((q) => !existingIds.has(q.id));
        if (newQuestions.length > 0) {
          return [...prev, ...newQuestions];
        }
        return prev;
      });
    }
  }, [services]);

  const loadData = async () => {
    try {
      const [servicesData, questionsData, orgData] = await Promise.all([
        api.getServices(),
        api.getQuestionBank(),
        api.getOrganization().catch(() => null),
      ]);
      setServices(servicesData as Service[]);
      setQuestions(questionsData as Question[]);
      if (orgData) setOrganization(orgData);
    } catch (error: any) {
      if (error.message.includes('401')) {
        api.clearToken();
        router.push('/auth/login');
      }
    } finally {
      setLoading(false);
    }
  };

  const resetForm = () => {
    setFormData({
      name: '',
      description: '',
      duration: 30,
      price: '',
      color: '#3b82f6',
      selectedQuestions: [],
    });
    setEditingServiceId(null);
  };

  const handleEditService = async (service: Service) => {
    // Récupérer les IDs des questions dans l'ordre
    const questionIds = service.questions
      .sort((a: any, b: any) => {
        const aOrder = typeof a === 'object' && a.order !== undefined ? a.order : 0;
        const bOrder = typeof b === 'object' && b.order !== undefined ? b.order : 0;
        return aOrder - bOrder;
      })
      .map((q: any) => (typeof q === 'string' ? q : q.questionId || q.questionTemplate?.id || q.id))
      .filter(Boolean);

    // Extraire les questions du service et les ajouter à la banque si elles n'y sont pas
    const serviceQuestions = service.questions
      .map((q: any) => {
        const qId = typeof q === 'string' ? q : q.questionId || q.questionTemplate?.id || q.id;
        const template = typeof q === 'object' && q.questionTemplate ? q.questionTemplate : q;
        
        if (qId && template && typeof template === 'object') {
          return {
            id: qId,
            label: template.label || '',
            aiPrompt: template.aiPrompt || template.label || '',
            responseType: template.responseType || 'text',
            category: template.category || 'autre',
            options: template.options || null,
            isSystem: template.isSystem !== false,
          };
        }
        return null;
      })
      .filter(Boolean) as Question[];

    // Ajouter les questions du service à la banque si elles n'y sont pas déjà
    setQuestions((prev) => {
      const existingIds = new Set(prev.map((q) => q.id));
      const newQuestions = serviceQuestions.filter((q) => !existingIds.has(q.id));
      return [...prev, ...newQuestions];
    });

    setFormData({
      name: service.name,
      description: service.description || '',
      duration: service.duration,
      price: service.price?.toString() || '',
      color: service.color || '#3b82f6',
      selectedQuestions: questionIds,
    });
    setEditingServiceId(service.id);
    setShowCreateModal(true);
  };

  const handleSubmitService = async (e: React.FormEvent) => {
    e.preventDefault();
    const serviceName = formData.name; // Sauvegarder le nom avant réinitialisation
    const isEditing = !!editingServiceId;
    
    try {
      const selectedQuestionsData = formData.selectedQuestions.map((qId) => {
        return {
          questionId: qId,
          order: formData.selectedQuestions.indexOf(qId),
          required: true,
        };
      });

      const serviceData = {
        name: formData.name,
        description: formData.description || undefined,
        duration: formData.duration,
        price: formData.price ? parseFloat(formData.price) : undefined,
        color: formData.color,
        questions: selectedQuestionsData,
      };

      if (isEditing && editingServiceId) {
        await api.updateService(editingServiceId, serviceData);
      } else {
        await api.createService(serviceData);
      }

      setShowCreateModal(false);
      resetForm();
      loadData();
    } catch (error: any) {
      // Erreur silencieuse
    }
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;

    if (!over) return;

    // Si on drop depuis la banque de questions vers la zone sélectionnée
    if (active.id.toString().startsWith('question-')) {
      const questionId = (active.data.current as any)?.questionId;
      if (questionId && over.id === 'selected-questions-zone') {
        // Vérifier que la question n'est pas déjà sélectionnée
        setFormData((prev) => {
          if (prev.selectedQuestions.includes(questionId)) {
            return prev; // Pas de changement si déjà présente
          }
          return {
            ...prev,
            selectedQuestions: [...prev.selectedQuestions, questionId],
          };
        });
      }
      return;
    }

    // Si on drop depuis les questions sélectionnées vers la banque (pour retirer)
    if (over.id === 'question-bank-zone') {
      const activeId = active.id as string;
      setFormData((prev) => ({
        ...prev,
        selectedQuestions: prev.selectedQuestions.filter((id) => id !== activeId),
      }));
      return;
    }

    // Si on réorganise dans les questions sélectionnées
    const activeId = active.id as string;
    const overId = over.id as string;
    
    // Si on drop dans la zone mais pas sur un autre élément, on ne fait rien
    if (overId === 'selected-questions-zone') {
      return;
    }

    // Réorganisation par drag & drop
    const oldIndex = formData.selectedQuestions.indexOf(activeId);
    const newIndex = formData.selectedQuestions.indexOf(overId);

    if (oldIndex !== -1 && newIndex !== -1 && oldIndex !== newIndex) {
      setFormData((prev) => ({
        ...prev,
        selectedQuestions: arrayMove(prev.selectedQuestions, oldIndex, newIndex),
      }));
    }
  };

  const toggleQuestion = (questionId: string) => {
    setFormData((prev) => ({
      ...prev,
      selectedQuestions: prev.selectedQuestions.includes(questionId)
        ? prev.selectedQuestions.filter((id) => id !== questionId)
        : [...prev.selectedQuestions, questionId],
    }));
  };

  const moveQuestion = (index: number, direction: 'up' | 'down') => {
    const newOrder = [...formData.selectedQuestions];
    if (direction === 'up' && index > 0) {
      [newOrder[index - 1], newOrder[index]] = [newOrder[index], newOrder[index - 1]];
    } else if (direction === 'down' && index < newOrder.length - 1) {
      [newOrder[index], newOrder[index + 1]] = [newOrder[index + 1], newOrder[index]];
    }
    setFormData((prev) => ({ ...prev, selectedQuestions: newOrder }));
  };
  
  const handleCloseModal = () => {
    setShowCreateModal(false);
    resetForm();
  };

  const handleDeleteService = (serviceId: string, serviceName: string) => {
    setDeleteDialog({ open: true, serviceId, serviceName });
  };

  const confirmDeleteService = async () => {
    if (!deleteDialog.serviceId) return;

    try {
      await api.deleteService(deleteDialog.serviceId);
      loadData();
    } catch (error: any) {
      // Erreur silencieuse
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="flex flex-col items-center gap-4">
          <div className="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
          <div className="text-lg font-medium text-gray-700">Chargement de vos services...</div>
        </div>
      </div>
    );
  }

  const filteredQuestions = questions.filter((q) => {
    const searchLower = searchQuery.toLowerCase();
    const matchesSearch = 
      (q.label?.toLowerCase().includes(searchLower) || false) ||
      (q.aiPrompt?.toLowerCase().includes(searchLower) || false);
    return matchesSearch;
  });

  const handleCreateQuestionSuccess = () => {
    loadData();
  };

  const getSelectedQuestionsData = () => {
    return formData.selectedQuestions.map((qId) => {
      const question = questions.find((q) => q.id === qId);
      
      // Si la question n'est pas trouvée, essayer de la récupérer depuis les services chargés
      if (!question) {
        // Chercher dans tous les services pour trouver la question
        const serviceWithQuestion = services.find((s) => {
          return s.questions?.some((q: any) => {
            const qIdFromService = typeof q === 'string' ? q : q.questionId || q.questionTemplate?.id || q.id;
            return qIdFromService === qId;
          });
        });
        
        if (serviceWithQuestion) {
          const qFromService = serviceWithQuestion.questions?.find((q: any) => {
            const qIdFromService = typeof q === 'string' ? q : q.questionId || q.questionTemplate?.id || q.id;
            return qIdFromService === qId;
          });
          
          if (qFromService) {
            const template = typeof qFromService === 'object' && qFromService.questionTemplate ? qFromService.questionTemplate : qFromService;
            if (template && typeof template === 'object' && template.label) {
              // Retourner le label depuis le template du service
              return {
                id: qId,
                label: template.label || 'Question inconnue',
              };
            }
          }
        }
      }
      
      return {
        id: qId,
        label: question?.label || 'Question inconnue',
      };
    });
  };

  const handleGenerateQuestions = async () => {
    if (!formData.name.trim()) {
      return;
    }

    setGeneratingQuestions(true);
    try {
      const result = await api.generateQuestions(
        formData.name,
        formData.description || undefined,
        organization?.businessType
      );

      // Les questions générées sont déjà dans result.questions avec tous les détails
      // Les ajouter uniquement à la banque de questions (pas automatiquement sélectionnées)
      const newQuestions = result.questions.map((q: any) => ({
        id: q.id,
        label: q.label || '',
        aiPrompt: q.aiPrompt || q.label || '',
        responseType: q.responseType || 'text',
        category: q.category || 'autre',
        options: q.options || null,
        isSystem: false,
      }));

      // Ajouter les nouvelles questions à la liste (elles apparaîtront dans la banque)
      setQuestions((prev) => [...prev, ...newQuestions]);
    } catch (error: any) {
      // Erreur silencieuse
    } finally {
      setGeneratingQuestions(false);
    }
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
              <Link href="/dashboard" className="text-gray-700 hover:text-blue-600 font-medium transition-colors">
                Dashboard
              </Link>
              <Link href="/dashboard/services" className="text-blue-600 hover:text-blue-700 font-semibold border-b-2 border-blue-600 pb-1">
                Services
              </Link>
              <Link href="/dashboard/customers" className="text-gray-700 hover:text-blue-600 font-medium transition-colors">
                Clients
              </Link>
            </div>
          </div>
        </div>
      </nav>

      <main className="container mx-auto px-4 py-8 max-w-7xl">
        <div className="flex justify-between items-center mb-8">
          <div>
            <h1 className="text-3xl font-bold mb-2 text-gray-900">Services</h1>
            <p className="text-gray-600 text-lg">Gérez vos services et configurez les questions de conversation</p>
          </div>
          <button
            onClick={() => {
              resetForm();
              setShowCreateModal(true);
            }}
            className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all flex items-center gap-2 font-medium shadow-md hover:shadow-lg active:scale-95"
          >
            <PlusIcon className="w-5 h-5" />
            Créer un service
          </button>
        </div>

        {/* Liste des services */}
        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
          {services.map((service) => (
            <div
              key={service.id}
              className="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-200 p-6 border-l-4 relative group transform hover:-translate-y-1"
              style={{ borderLeftColor: service.color }}
            >
              <div className="flex justify-between items-start mb-4">
                <div className="flex-1">
                  <h3 className="text-xl font-semibold">{service.name}</h3>
                  {service.description && (
                    <p className="text-gray-600 text-sm mt-1">{service.description}</p>
                  )}
                </div>
                <div className="flex items-center gap-2">
                  <span
                    className={`px-2 py-1 rounded text-xs ${
                      service.isActive
                        ? 'bg-green-100 text-green-800'
                        : 'bg-gray-100 text-gray-800'
                    }`}
                  >
                    {service.isActive ? 'Actif' : 'Inactif'}
                  </span>
                  <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button
                      onClick={() => handleEditService(service)}
                      className="p-1.5 text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded transition-colors"
                      title="Modifier le service"
                    >
                      <EditIcon className="w-5 h-5" />
                    </button>
                    <button
                      onClick={() => handleDeleteService(service.id, service.name)}
                      className="p-1.5 text-red-600 hover:text-red-700 hover:bg-red-50 rounded transition-colors"
                      title="Supprimer le service"
                    >
                      <TrashIcon className="w-5 h-5" />
                    </button>
                  </div>
                </div>
              </div>
              <div className="space-y-2 text-sm text-gray-600">
                <div className="flex items-center gap-2">
                  <ClockIcon className="w-4 h-4" />
                  <span>Durée: {service.duration} min</span>
                </div>
                {service.price && (
                  <div className="flex items-center gap-2">
                    <DollarIcon className="w-4 h-4" />
                    <span>Prix: {service.price}€</span>
                  </div>
                )}
                <div className="flex items-center gap-2">
                  <HelpCircleIcon className="w-4 h-4" />
                  <span>Questions: {service.questions?.length || 0}</span>
                </div>
              </div>
            </div>
          ))}
        </div>

        {services.length === 0 && (
          <div className="text-center py-16 bg-white rounded-xl shadow-lg border-2 border-dashed border-gray-300">
            <div className="max-w-md mx-auto">
              <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <HelpCircleIcon className="w-8 h-8 text-blue-600" />
              </div>
              <h3 className="text-xl font-semibold text-gray-900 mb-2">Aucun service créé</h3>
              <p className="text-gray-600 mb-6">Commencez par créer votre premier service pour gérer vos rendez-vous.</p>
              <button
                onClick={() => {
                  resetForm();
                  setShowCreateModal(true);
                }}
                className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all flex items-center gap-2 mx-auto font-medium shadow-md hover:shadow-lg active:scale-95"
              >
                <PlusIcon className="w-5 h-5" />
                Créer votre premier service
              </button>
            </div>
          </div>
        )}

        {/* Modal de création/édition de question */}
        <CreateQuestionModal
          open={showCreateQuestionModal}
          onClose={() => {
            setShowCreateQuestionModal(false);
            setEditingQuestionId(null);
          }}
          onSuccess={handleCreateQuestionSuccess}
          editingQuestion={editingQuestionId ? questions.find((q) => q.id === editingQuestionId) || null : null}
        />

        {/* Dialog de confirmation de suppression */}
        <ConfirmDialog
          open={deleteDialog.open}
          onOpenChange={(open) => setDeleteDialog({ ...deleteDialog, open })}
          title="Supprimer le service"
          description={`Êtes-vous sûr de vouloir supprimer le service "${deleteDialog.serviceName}" ? Cette action est irréversible et supprimera également toutes les données associées.`}
          confirmText="Supprimer"
          cancelText="Annuler"
          variant="danger"
          onConfirm={confirmDeleteService}
        />

        {/* Modal de création */}
        {showCreateModal && (
          <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-in fade-in-0">
            <div className="bg-white rounded-xl max-w-7xl w-full max-h-[95vh] overflow-y-auto shadow-2xl animate-in zoom-in-95 slide-in-from-bottom-2">
              <div className="p-6 border-b sticky top-0 bg-white z-10 rounded-t-xl">
                <div className="flex justify-between items-center">
                  <div>
                    <h2 className="text-2xl font-bold text-gray-900">
                      {editingServiceId ? 'Modifier le service' : 'Créer un service'}
                    </h2>
                    <p className="text-sm text-gray-600 mt-1">
                      {editingServiceId 
                        ? 'Modifiez les informations de votre service' 
                        : 'Configurez votre nouveau service en quelques étapes'}
                    </p>
                  </div>
                  <button
                    onClick={handleCloseModal}
                    className="text-gray-400 hover:text-gray-600 p-2 hover:bg-gray-100 rounded-lg transition-colors"
                    title="Fermer"
                  >
                    <XIcon className="w-5 h-5" />
                  </button>
                </div>
              </div>

              <form onSubmit={handleSubmitService} className="p-6">
                <div className="grid md:grid-cols-2 gap-6 mb-6">
                  <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-2">
                      Nom du service <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      required
                      value={formData.name}
                      onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                      placeholder="Ex: Coupe homme"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-2">
                      Durée (minutes) <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="number"
                      required
                      min="5"
                      step="5"
                      value={formData.duration}
                      onChange={(e) => setFormData({ ...formData, duration: parseInt(e.target.value) })}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-2">Prix (€)</label>
                    <input
                      type="number"
                      step="0.01"
                      value={formData.price}
                      onChange={(e) => setFormData({ ...formData, price: e.target.value })}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                      placeholder="Ex: 25.00"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-2">Couleur</label>
                    <div className="flex items-center gap-3">
                      <input
                        type="color"
                        value={formData.color}
                        onChange={(e) => setFormData({ ...formData, color: e.target.value })}
                        className="w-16 h-12 rounded-lg border-2 border-gray-300 cursor-pointer"
                      />
                      <input
                        type="text"
                        value={formData.color}
                        onChange={(e) => setFormData({ ...formData, color: e.target.value })}
                        className="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        placeholder="#3b82f6"
                      />
                    </div>
                  </div>

                  <div className="md:col-span-2">
                    <label className="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <textarea
                      value={formData.description}
                      onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"
                      rows={3}
                      placeholder="Décrivez votre service pour aider les clients à mieux comprendre..."
                    />
                  </div>
                </div>

                {/* Question Bank avec Drag & Drop */}
                <div className="border-t pt-6">
                  <div className="flex items-center justify-between mb-4 flex-wrap gap-3">
                    <div>
                      <h3 className="text-lg font-semibold text-gray-900">Questions de conversation</h3>
                      <p className="text-sm text-gray-600 mt-1">
                        Glissez les questions de la banque vers la zone de sélection ou réorganisez-les par drag & drop
                      </p>
                    </div>
                    <div className="flex gap-2">
                      <button
                        type="button"
                        onClick={() => {
                          setEditingQuestionId(null);
                          setShowCreateQuestionModal(true);
                        }}
                        className="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-all flex items-center gap-2 text-sm font-medium shadow-md"
                      >
                        <PlusIcon className="w-4 h-4" />
                        Question personnalisée
                      </button>
                      <button
                        type="button"
                        onClick={handleGenerateQuestions}
                        disabled={generatingQuestions || !formData.name.trim()}
                        className="px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:from-blue-700 hover:to-purple-700 transition-all flex items-center gap-2 text-sm font-medium shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                        title={!formData.name.trim() ? 'Saisissez d\'abord un nom de service' : 'Générer automatiquement 5 questions personnalisées avec ChatGPT'}
                      >
                        {generatingQuestions ? (
                          <>
                            <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                            Génération...
                          </>
                        ) : (
                          <>
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Générer avec IA
                          </>
                        )}
                      </button>
                    </div>
                  </div>

                  <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragEnd={handleDragEnd}
                  >
                    <div className="grid lg:grid-cols-2 gap-6">
                      {/* Zone de sélection des questions */}
                      <DroppableQuestionsZone
                        id="selected-questions-zone"
                        questions={getSelectedQuestionsData()}
                        onRemove={toggleQuestion}
                        onMove={moveQuestion}
                      />

                      {/* Banque de questions */}
                      <QuestionBankZone
                        id="question-bank-zone"
                        questions={filteredQuestions}
                        selectedQuestionIds={formData.selectedQuestions}
                        searchQuery={searchQuery}
                        onSearchChange={setSearchQuery}
                        onEdit={(id) => {
                          const q = questions.find((q) => q.id === id);
                          if (q) {
                            setEditingQuestionId(id);
                            setShowCreateQuestionModal(true);
                          }
                        }}
                      />
                    </div>
                  </DndContext>
                </div>

                <div className="flex justify-end gap-4 mt-6 pt-6 border-t">
                  <button
                    type="button"
                    onClick={handleCloseModal}
                    className="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 font-medium text-gray-700 transition-all"
                  >
                    Annuler
                  </button>
                  <button
                    type="submit"
                    className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium shadow-md hover:shadow-lg transition-all active:scale-95"
                  >
                    {editingServiceId ? 'Enregistrer les modifications' : 'Créer le service'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}
      </main>
    </div>
  );
}

