'use client';

import { memo } from 'react';
import { useDraggable } from '@dnd-kit/core';
import { CSS } from '@dnd-kit/utilities';
import { HelpCircleIcon, EditIcon } from '@/components/icons';

interface QuestionBankItemProps {
  id: string;
  label: string;
  category: string;
  responseType: string;
  isSystem?: boolean;
  onEdit?: (id: string) => void;
}

export const QuestionBankItem = memo(function QuestionBankItem({
  id,
  label,
  category,
  responseType,
  isSystem = false,
  onEdit,
}: QuestionBankItemProps) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    isDragging,
  } = useDraggable({
    id: `question-${id}`,
    data: {
      questionId: id,
      label,
      category,
      responseType,
    },
  });

  const style = {
    transform: CSS.Translate.toString(transform),
    opacity: isDragging ? 0.6 : 1,
    transition: isDragging ? 'none' : 'opacity 0.2s', // Pas de transition de transform pendant le drag
  };

  return (
    <div
      ref={setNodeRef}
      style={{
        ...style,
        zIndex: isDragging ? 9999 : 'auto',
      }}
      className={`group relative p-3 bg-white border-2 rounded-lg cursor-grab active:cursor-grabbing transition-all ${
        isDragging
          ? 'border-blue-500 shadow-lg'
          : 'border-gray-200 hover:border-blue-300 hover:shadow-md'
      }`}
      {...listeners}
      {...attributes}
    >
      <div className="flex items-start justify-between gap-2">
        <div className="flex items-start gap-2 flex-1 min-w-0">
          <HelpCircleIcon className="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" />
          <div className="flex-1 min-w-0">
            <div className="font-medium text-gray-900 truncate">{label || 'Question sans libellé'}</div>
            <div className="flex items-center gap-2 mt-1">
              <span className="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded">
                {category || 'autre'}
              </span>
              <span className="text-xs px-2 py-0.5 bg-blue-50 text-blue-600 rounded">
                {responseType || 'text'}
              </span>
              {!isSystem && (
                <span className="text-xs px-2 py-0.5 bg-purple-50 text-purple-600 rounded">
                  Personnalisée
                </span>
              )}
            </div>
          </div>
        </div>
        {onEdit && !isSystem && (
          <button
            onClick={(e) => {
              e.stopPropagation();
              onEdit(id);
            }}
            className="opacity-0 group-hover:opacity-100 transition-opacity p-1 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded"
            title="Modifier"
          >
            <EditIcon className="w-4 h-4" />
          </button>
        )}
      </div>
      {isDragging && (
        <div className="absolute inset-0 bg-blue-50 border-2 border-blue-500 rounded-lg flex items-center justify-center">
          <span className="text-sm font-medium text-blue-600">Glisser ici</span>
        </div>
      )}
    </div>
  );
});

