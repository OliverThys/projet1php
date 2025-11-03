'use client';

import { memo } from 'react';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ChevronUpIcon, ChevronDownIcon, XIcon } from '@/components/icons';

interface SortableQuestionItemProps {
  id: string;
  label: string;
  index: number;
  total: number;
  onRemove: (id: string) => void;
  onMove: (index: number, direction: 'up' | 'down') => void;
}

export const SortableQuestionItem = memo(function SortableQuestionItem({
  id,
  label,
  index,
  total,
  onRemove,
  onMove,
}: SortableQuestionItemProps) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition: isDragging ? 'none' : transition, // Pas de transition pendant le drag pour plus de fluidité
    opacity: isDragging ? 0.6 : 1,
    zIndex: isDragging ? 9999 : 'auto',
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`flex items-center justify-between p-4 bg-white rounded-lg border-2 ${
        isDragging ? 'border-blue-500 shadow-lg' : 'border-gray-200'
      } transition-all min-h-[60px]`}
    >
      <div
        {...attributes}
        {...listeners}
        className="flex items-center gap-3 flex-1 cursor-grab active:cursor-grabbing min-w-0"
      >
        <div className="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-semibold text-sm">
          {index + 1}
        </div>
        <span className="text-gray-900 font-medium flex-1 break-words min-w-0">{label}</span>
        <div className="flex-shrink-0 text-gray-400 ml-2">
          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
          </svg>
        </div>
      </div>
      <div className="flex gap-2 ml-4 flex-shrink-0">
        <button
          type="button"
          onClick={() => onMove(index, 'up')}
          disabled={index === 0}
          className="p-2 text-sm bg-gray-100 rounded-lg disabled:opacity-50 hover:bg-gray-200 transition"
          title="Déplacer vers le haut"
        >
          <ChevronUpIcon className="w-4 h-4" />
        </button>
        <button
          type="button"
          onClick={() => onMove(index, 'down')}
          disabled={index === total - 1}
          className="p-2 text-sm bg-gray-100 rounded-lg disabled:opacity-50 hover:bg-gray-200 transition"
          title="Déplacer vers le bas"
        >
          <ChevronDownIcon className="w-4 h-4" />
        </button>
        <button
          type="button"
          onClick={() => onRemove(id)}
          className="p-2 text-sm bg-red-100 rounded-lg hover:bg-red-200 transition"
          title="Retirer"
        >
          <XIcon className="w-4 h-4 text-red-600" />
        </button>
      </div>
    </div>
  );
});

