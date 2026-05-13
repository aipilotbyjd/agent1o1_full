import './TypeSelector.css';

interface TypeSelectorProps {
  triggerTypes: any[];
  selectedType: any | null;
  onSelect: (type: any) => void;
}

export default function TypeSelector({
  triggerTypes,
  selectedType,
  onSelect,
}: TypeSelectorProps) {
  return (
    <div className="type-selector">
      <div className="type-selector__header">
        <h3>Select Trigger Type</h3>
        <p>Choose what event should trigger this workflow</p>
      </div>

      <div className="type-selector__list">
        {triggerTypes.map((type) => (
          <button
            key={type.id}
            className={`type-selector__item ${
              selectedType?.id === type.id ? 'type-selector__item--active' : ''
            }`}
            onClick={() => onSelect(type)}
          >
            <div className="type-selector__content">
              <div className="type-selector__name">{type.name}</div>
              {type.description && (
                <div className="type-selector__description">
                  {type.description}
                </div>
              )}
              {type.zapier_mode && (
                <div className="type-selector__badge">
                  {type.zapier_mode === 'instant' ? '⚡ Instant' : '⏳ Polling'}
                </div>
              )}
            </div>
            {type.requires_credential && (
                <div className="type-selector__auth-required">🔐 Auth Required</div>
            )}
          </button>
        ))}
      </div>
    </div>
  );
}
