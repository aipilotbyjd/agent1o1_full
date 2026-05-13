import './TriggerStatus.css';

interface TriggerStatusProps {
  currentStep: number;
  selectedCategory: any;
  selectedType: any;
}

export default function TriggerStatus({
  currentStep,
  selectedCategory,
  selectedType,
}: TriggerStatusProps) {
  const steps = [
    { num: 1, title: 'Choose Type', icon: '📋' },
    { num: 2, title: 'Select Trigger', icon: '⚡' },
    { num: 3, title: 'Configure', icon: '⚙️' },
    { num: 4, title: 'Connect Account', icon: '🔐' },
    { num: 5, title: 'Review', icon: '✓' },
  ];

  // Hide account step if not needed
  const visibleSteps = selectedType?.requires_credential
    ? steps
    : steps.filter((s) => s.num !== 4);

  return (
    <div className="trigger-status">
      <div className="trigger-status__header">
        <h4>Setup Progress</h4>
      </div>

      <div className="trigger-status__steps">
        {visibleSteps.map((step) => (
          <div
            key={step.num}
            className={`trigger-status__step ${
              currentStep >= step.num ? 'trigger-status__step--completed' : ''
            } ${
              currentStep === step.num ? 'trigger-status__step--active' : ''
            }`}
          >
            <div className="trigger-status__step-icon">{step.icon}</div>
            <div className="trigger-status__step-title">{step.title}</div>
          </div>
        ))}
      </div>

      {/* Summary */}
      <div className="trigger-status__summary">
        <h4>Summary</h4>

        {selectedCategory && (
          <div className="trigger-status__item">
            <span className="trigger-status__label">Type:</span>
            <span className="trigger-status__value">{selectedCategory.name}</span>
          </div>
        )}

        {selectedType && (
          <>
            <div className="trigger-status__item">
              <span className="trigger-status__label">Trigger:</span>
              <span className="trigger-status__value">{selectedType.name}</span>
            </div>

            {selectedType.zapier_mode && (
              <div className="trigger-status__item">
                <span className="trigger-status__label">Mode:</span>
                <span className="trigger-status__value">
                  {selectedType.zapier_mode === 'instant'
                    ? '⚡ Real-time'
                    : '⏳ Polling'}
                </span>
              </div>
            )}

            {selectedType.requires_credential && (
              <div className="trigger-status__item trigger-status__item--warning">
                <span className="trigger-status__label">🔐 Auth Required</span>
              </div>
            )}
          </>
        )}
      </div>

      {/* Help Section */}
      <div className="trigger-status__help">
        <h4>💡 Tips</h4>
        <ul>
          <li>
            {currentStep === 1
              ? 'Start by selecting a trigger type'
              : 'Review your trigger configuration at any time'}
          </li>
          <li>
            {selectedType?.zapier_mode === 'instant'
              ? 'Instant triggers fire as soon as events occur'
              : 'Polling triggers check periodically (every 5 min)'}
          </li>
          <li>
            You can test and modify this trigger after creation
          </li>
        </ul>
      </div>
    </div>
  );
}
