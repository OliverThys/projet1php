'use client';

import { memo } from 'react';
import { useDroppable } from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { SortableQuestionItem } from './sortable-question-item';

interface DroppableQuestionsZoneProps {
  id: string;
  questions: Array<{ id: string; label: string }>;
  onRemove: (id: string) => void;
  onMove: (index: number, direction: 'up' | 'down') => void;
}

export const DroppableQuestionsZone = memo(function DroppableQuestionsZone({
  id,
  questions,
  onRemove,
  onMove,
}: DroppableQuestionsZoneProps) {
  const { setNodeRef, isOver } = useDroppable({
    id,
  });

  return (
    <div
      ref={setNodeRef}
      className={`min-h-[600px] p-6 rounded-xl border-2 transition-colors duration-200 relative z-0 ${
        isOver
          ? 'border-blue-500 bg-blue-50 border-dashed'
          : 'border-gray-200 bg-gray-50'
      }`}
    >
      <div className="flex items-center justify-between mb-3">
        <h4 className="font-semibold text-gray-900">
          Questions sélectionnées ({questions.length})
        </h4>
        {isOver && (
          <span className="text-sm text-blue-600 font-medium animate-pulse">
            Déposez ici ✨
          </span>
        )}
      </div>

      {questions.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-12 text-gray-400">
          <svg
            className="w-16 h-16 mb-3 opacity-50"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1.5}
              d="M12 6v6m0 0v6m0-6h6m-6 0H6"
            />
          </svg>
          <p className="text-sm font-medium">Zone de dépôt</p>
          <p className="text-xs mt-1">Glissez des questions depuis la banque</p>
        </div>
      ) : (
        <SortableContext
          items={questions.map((q) => q.id)}
          strategy={verticalListSortingStrategy}
        >
          <div className="space-y-3">
            {questions.map((question, index) => (
              <SortableQuestionItem
                key={question.id}
                id={question.id}
                label={question.label}
                index={index}
                total={questions.length}
                onRemove={onRemove}
                onMove={onMove}
              />
            ))}
          </div>
        </SortableContext>
      )}
    </div>
  );
});

