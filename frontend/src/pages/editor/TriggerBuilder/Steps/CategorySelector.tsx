import './CategorySelector.css';

interface CategorySelectorProps {
  categories: any[];
  selectedCategory: string | null;
  onSelect: (slug: string) => void;
  isLoading: boolean;
}

export default function CategorySelector({
  categories,
  selectedCategory,
  onSelect,
  isLoading,
}: CategorySelectorProps) {
  // Group categories: generic first, then services
  const genericCategories = categories.filter((cat) =>
    ['manual', 'schedule', 'webhook', 'polling'].includes(cat.slug)
  );
  const serviceCategories = categories.filter(
    (cat) => cat.category_type === 'app_specific'
  );

  if (isLoading) {
    return (
      <div className="category-selector">
        <div className="category-selector__loading">Loading triggers...</div>
      </div>
    );
  }

  return (
    <div className="category-selector">
      <div className="category-selector__header">
        <h3>Choose a Trigger Type</h3>
        <p>Select where your workflow will be triggered from</p>
      </div>

      {/* Generic Triggers */}
      <div className="category-selector__section">
        <h4>General</h4>
        <div className="category-selector__grid">
          {genericCategories.map((category) => (
            <button
              key={category.id}
              className={`category-selector__item ${
                selectedCategory === category.slug
                  ? 'category-selector__item--active'
                  : ''
              }`}
              onClick={() => onSelect(category.slug)}
              title={category.description}
            >
              <div className="category-selector__icon">
                {getIconForCategory(category.slug)}
              </div>
              <div className="category-selector__label">{category.name}</div>
            </button>
          ))}
        </div>
      </div>

      {/* Service Integrations */}
      {serviceCategories.length > 0 && (
        <div className="category-selector__section">
          <h4>Integrations</h4>
          <div className="category-selector__grid">
            {serviceCategories.map((category) => (
              <button
                key={category.id}
                className={`category-selector__item ${
                  selectedCategory === category.slug
                    ? 'category-selector__item--active'
                    : ''
                }`}
                onClick={() => onSelect(category.slug)}
                title={category.description}
              >
                <div className="category-selector__icon">
                  {category.icon || '🔗'}
                </div>
                <div className="category-selector__label">{category.name}</div>
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function getIconForCategory(slug: string): string {
  const icons: Record<string, string> = {
    manual: '▶️',
    schedule: '⏰',
    webhook: '🪝',
    polling: '🔄',
  };
  return icons[slug] || '🔗';
}
