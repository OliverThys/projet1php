'use client';

import { memo } from 'react';
import { useDroppable } from '@dnd-kit/core';
import { QuestionBankItem } from './question-bank-item';

interface QuestionBankZoneProps {
  id: string;
  questions: Array<{
    id: string;
    label: string;
    category?: string;
    responseType?: string;
    isSystem?: boolean;
  }>;
  selectedQuestionIds: string[];
  searchQuery: string;
  onSearchChange: (query: string) => void;
  onEdit?: (id: string) => void;
}

export const QuestionBankZone = memo(function QuestionBankZone({
  id,
  questions,
  selectedQuestionIds,
  searchQuery,
  onSearchChange,
  onEdit,
}: QuestionBankZoneProps) {
  const { setNodeRef, isOver } = useDroppable({
    id,
  });

  const availableQuestions = questions.filter((q) => {
    const isSelected = selectedQuestionIds.includes(q.id);
    return !isSelected && q.label;
  });

  return (
    <div
      ref={setNodeRef}
      className={`bg-white rounded-xl border-2 p-4 relative z-0 transition-colors duration-200 ${
        isOver
          ? 'border-red-500 bg-red-50 border-dashed'
          : 'border-gray-200'
      }`}
    >
      <div className="mb-4">
        <div className="flex items-center justify-between mb-3">
          <h4 className="font-semibold text-gray-900">
            Banque de questions ({availableQuestions.length})
          </h4>
          {isOver && (
            <span className="text-sm text-red-600 font-medium animate-pulse">
              Relâchez pour retirer ✨
            </span>
          )}
        </div>
        
        {/* Recherche */}
        <div className="mb-4">
          <input
            type="text"
            placeholder="Rechercher une question..."
            value={searchQuery}
            onChange={(e) => onSearchChange(e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>
      </div>

      <div className="space-y-2 max-h-[600px] overflow-y-auto pr-2">
        {availableQuestions.length === 0 ? (
          <div className="text-center py-8 text-gray-400 text-sm">
            {searchQuery ? 'Aucune question trouvée' : 'Aucune question disponible'}
          </div>
        ) : (
          availableQuestions.map((question) => {
            const isSystem = (question as any).isSystem !== false;
            
            return (
              <QuestionBankItem
                key={question.id}
                id={question.id}
                label={question.label}
                category={question.category || 'autre'}
                responseType={question.responseType || 'text'}
                isSystem={isSystem}
                onEdit={onEdit}
              />
            );
          })
        )}
      </div>
    </div>
  );
});

