import './TriggerPreview.css';

interface TriggerPreviewProps {
  category: any;
  triggerType: any;
  fieldValues: Record<string, string>;
  triggerName: string;
}

export default function TriggerPreview({
  category,
  triggerType,
  fieldValues,
  triggerName,
}: TriggerPreviewProps) {
  return (
    <div className="trigger-preview">
      <div className="trigger-preview__header">
        <h3>Review Your Trigger</h3>
        <p>Everything looks good? Click Create & Publish to activate</p>
      </div>

      <div className="trigger-preview__card">
        <div className="trigger-preview__category">
          {category.icon || '🔗'} {category.name}
        </div>

        <div className="trigger-preview__name">
          {triggerName || triggerType.name}
        </div>

        {triggerType.description && (
          <div className="trigger-preview__description">
            {triggerType.description}
          </div>
        )}

        <div className="trigger-preview__section">
          <h4>Configuration</h4>
          <div className="trigger-preview__config">
            {triggerType.fields?.map((field: any) => (
              <div key={field.id} className="trigger-preview__config-item">
                <div className="trigger-preview__config-label">
                  {field.field_label}
                </div>
                <div className="trigger-preview__config-value">
                  {fieldValues[field.field_name] || '(not configured)'}
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="trigger-preview__badges">
          {triggerType.zapier_mode === 'instant' && (
            <span className="trigger-preview__badge trigger-preview__badge--instant">
              ⚡ Instant
            </span>
          )}
          {triggerType.zapier_mode === 'polling' && (
            <span className="trigger-preview__badge trigger-preview__badge--polling">
              ⏳ Polling
            </span>
          )}
          {triggerType.execution_mode === 'manual' && (
            <span className="trigger-preview__badge">▶️ Manual</span>
          )}
        </div>
      </div>
    </div>
  );
}
