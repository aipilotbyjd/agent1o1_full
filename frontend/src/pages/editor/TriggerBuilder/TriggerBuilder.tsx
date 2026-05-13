import { useState, useEffect } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { useTriggerAPI } from '@/api/modules/triggers';
import { IWorkflow } from '@/types';
import CategorySelector from './Steps/CategorySelector';
import TypeSelector from './Steps/TypeSelector';
import ConfigureFields from './Steps/ConfigureFields';
import ConnectAccount from './Steps/ConnectAccount';
import TriggerPreview from './Components/TriggerPreview';
import TriggerStatus from './Components/TriggerStatus';
import './TriggerBuilder.css';

interface TriggerBuilderProps {
  workflow: IWorkflow;
  onTriggerPublished?: (trigger: any) => void;
  onClose?: () => void;
}

export default function TriggerBuilder({
  workflow,
  onTriggerPublished,
  onClose,
}: TriggerBuilderProps) {
  const [step, setStep] = useState(1);
  const [selectedCategory, setSelectedCategory] = useState<string | null>(null);
  const [selectedType, setSelectedType] = useState<any | null>(null);
  const [fieldValues, setFieldValues] = useState<Record<string, string>>({});
  const [credentialId, setCredentialId] = useState<string | null>(null);
  const [triggerName, setTriggerName] = useState('');

  const triggerAPI = useTriggerAPI();

  // Fetch available triggers
  const { data: triggerData, isLoading: triggersLoading } = useQuery({
    queryKey: ['triggers', 'available'],
    queryFn: () => triggerAPI.getAvailable(),
  });

  // Create trigger mutation
  const createTriggerMutation = useMutation({
    mutationFn: () =>
      triggerAPI.createTrigger(workflow.id, {
        trigger_type_id: selectedType.id,
        credential_id: credentialId,
        name: triggerName || selectedType.name,
        field_values: fieldValues,
      }),
    onSuccess: (trigger) => {
      // Optionally publish
      if (step === 5) {
        publishTrigger();
      }
    },
  });

  // Publish trigger mutation
  const publishTriggerMutation = useMutation({
    mutationFn: (trigger: any) => triggerAPI.publishTrigger(workflow.id, trigger.id),
    onSuccess: (trigger) => {
      onTriggerPublished?.(trigger);
    },
  });

  const publishTrigger = () => {
    if (workflow.trigger) {
      publishTriggerMutation.mutate(workflow.trigger);
    }
  };

  const categories = triggerData?.data || [];
  const currentCategory = categories.find((cat) => cat.slug === selectedCategory);

  const canProceedToNextStep = () => {
    switch (step) {
      case 1:
        return selectedCategory !== null;
      case 2:
        return selectedType !== null;
      case 3:
        return validateFieldValues();
      case 4:
        return !selectedType?.requires_credential || credentialId !== null;
      default:
        return true;
    }
  };

  const validateFieldValues = () => {
    const requiredFields = selectedType?.fields?.filter(
      (f: any) => f.is_required
    ) || [];
    return requiredFields.every(
      (f: any) => fieldValues[f.field_name]?.trim() !== ''
    );
  };

  return (
    <div className="trigger-builder">
      <div className="trigger-builder__header">
        <h2>Configure Trigger</h2>
        <button
          className="trigger-builder__close"
          onClick={onClose}
          aria-label="Close"
        >
          ✕
        </button>
      </div>

      <div className="trigger-builder__content">
        <div className="trigger-builder__steps">
          {/* Step 1: Select Category */}
          {step === 1 && (
            <CategorySelector
              categories={categories}
              selectedCategory={selectedCategory}
              onSelect={setSelectedCategory}
              isLoading={triggersLoading}
            />
          )}

          {/* Step 2: Select Type */}
          {step === 2 && currentCategory && (
            <TypeSelector
              triggerTypes={currentCategory.types}
              selectedType={selectedType}
              onSelect={setSelectedType}
            />
          )}

          {/* Step 3: Configure Fields */}
          {step === 3 && selectedType && (
            <ConfigureFields
              triggerType={selectedType}
              fieldValues={fieldValues}
              onFieldChange={(fieldName, value) => {
                setFieldValues((prev) => ({
                  ...prev,
                  [fieldName]: value,
                }));
              }}
            />
          )}

          {/* Step 4: Connect Account */}
          {step === 4 && selectedType?.requires_credential && (
            <ConnectAccount
              triggerType={selectedType}
              workspaceId={workflow.workspace_id}
              selectedCredentialId={credentialId}
              onCredentialSelect={setCredentialId}
            />
          )}

          {/* Step 5: Preview & Publish */}
          {step === 5 && (
            <div className="trigger-builder__preview-step">
              <TriggerPreview
                category={currentCategory}
                triggerType={selectedType}
                fieldValues={fieldValues}
                triggerName={triggerName}
              />
              <div className="trigger-builder__name-input">
                <label>Trigger Name (Optional)</label>
                <input
                  type="text"
                  value={triggerName}
                  onChange={(e) => setTriggerName(e.target.value)}
                  placeholder={selectedType?.name}
                />
              </div>
            </div>
          )}
        </div>

        {/* Right Sidebar: Status & Summary */}
        <div className="trigger-builder__sidebar">
          <TriggerStatus
            currentStep={step}
            selectedCategory={currentCategory}
            selectedType={selectedType}
          />
        </div>
      </div>

      {/* Navigation Buttons */}
      <div className="trigger-builder__footer">
        <button
          className="trigger-builder__btn trigger-builder__btn--secondary"
          onClick={() => setStep(Math.max(1, step - 1))}
          disabled={step === 1}
        >
          ← Back
        </button>

        <div className="trigger-builder__step-indicator">
          Step {step} of {selectedType?.requires_credential ? 5 : 4}
        </div>

        {step < (selectedType?.requires_credential ? 5 : 4) ? (
          <button
            className="trigger-builder__btn trigger-builder__btn--primary"
            onClick={() => setStep(step + 1)}
            disabled={!canProceedToNextStep()}
          >
            Next →
          </button>
        ) : (
          <button
            className="trigger-builder__btn trigger-builder__btn--primary"
            onClick={() => createTriggerMutation.mutate()}
            disabled={!canProceedToNextStep() || createTriggerMutation.isPending}
          >
            {createTriggerMutation.isPending ? 'Creating...' : 'Create & Publish'}
          </button>
        )}
      </div>
    </div>
  );
}
