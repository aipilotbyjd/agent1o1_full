import './ConfigureFields.css';

interface ConfigureFieldsProps {
  triggerType: any;
  fieldValues: Record<string, string>;
  onFieldChange: (fieldName: string, value: string) => void;
}

export default function ConfigureFields({
  triggerType,
  fieldValues,
  onFieldChange,
}: ConfigureFieldsProps) {
  const fields = triggerType.fields || [];

  if (fields.length === 0) {
    return (
      <div className="configure-fields">
        <div className="configure-fields__empty">
          <p>✓ This trigger requires no configuration</p>
        </div>
      </div>
    );
  }

  return (
    <div className="configure-fields">
      <div className="configure-fields__header">
        <h3>Configure {triggerType.name}</h3>
        <p>Fill in the details for this trigger</p>
      </div>

      <form className="configure-fields__form">
        {fields.map((field: any) => (
          <div key={field.id} className="configure-fields__field">
            <label className="configure-fields__label">
              {field.field_label}
              {field.is_required && (
                <span className="configure-fields__required">*</span>
              )}
            </label>

            {field.help_text && (
              <div className="configure-fields__help">{field.help_text}</div>
            )}

            {field.field_type === 'text' || field.field_type === 'number' ? (
              <input
                type={field.field_type}
                className="configure-fields__input"
                placeholder={field.placeholder}
                value={fieldValues[field.field_name] || ''}
                onChange={(e) =>
                  onFieldChange(field.field_name, e.target.value)
                }
                required={field.is_required}
              />
            ) : field.field_type === 'textarea' ? (
              <textarea
                className="configure-fields__textarea"
                placeholder={field.placeholder}
                value={fieldValues[field.field_name] || ''}
                onChange={(e) =>
                  onFieldChange(field.field_name, e.target.value)
                }
                required={field.is_required}
                rows={4}
              />
            ) : field.field_type === 'time' ? (
              <input
                type="time"
                className="configure-fields__input"
                value={fieldValues[field.field_name] || ''}
                onChange={(e) =>
                  onFieldChange(field.field_name, e.target.value)
                }
                required={field.is_required}
              />
            ) : field.field_type === 'date' ? (
              <input
                type="date"
                className="configure-fields__input"
                value={fieldValues[field.field_name] || ''}
                onChange={(e) =>
                  onFieldChange(field.field_name, e.target.value)
                }
                required={field.is_required}
              />
            ) : field.field_type === 'select' ? (
              <select
                className="configure-fields__select"
                value={fieldValues[field.field_name] || ''}
                onChange={(e) =>
                  onFieldChange(field.field_name, e.target.value)
                }
                required={field.is_required}
              >
                <option value="">Choose an option...</option>
                {field.options?.map((opt: any) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
            ) : field.field_type === 'multiselect' ? (
              <select
                multiple
                className="configure-fields__select"
                value={(fieldValues[field.field_name] || '').split(',')}
                onChange={(e) =>
                  onFieldChange(
                    field.field_name,
                    Array.from(e.target.selectedOptions, (opt) => opt.value).join(
                      ','
                    )
                  )
                }
              >
                {field.options?.map((opt: any) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
            ) : null}
          </div>
        ))}
      </form>
    </div>
  );
}
