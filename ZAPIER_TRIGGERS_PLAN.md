# Zapier-Like Trigger System Implementation Plan

## 🎯 Goal
Transform LinkFlow triggers from generic "webhooks + polling" into **Zapier-style pre-built triggers** where users see:
- "GitHub: On Push"
- "Slack: On Message"  
- "Stripe: On Charge"
- etc. (no webhook knowledge required)

---

## 📊 User Flow (Current vs Target)

### Current LinkFlow
```
User → Workflow → "Add Trigger Node"
  → Select: Webhook / Polling / Cron
  → If Webhook: Pick provider (GitHub) + events + config
  → Configure: owner, repo, etc.
  ⚠️  User sees "webhooks" terminology
```

### Target (Zapier-like)
```
User → Workflow → "Add Trigger"
  → See: [GitHub] [Slack] [Stripe] [Airtable] [Google Sheets] [Custom]
  → Select: GitHub
  → See: [On Push] [On Pull Request] [On New Issue] [On New Release]
  → Select: "On Push"
  → Configure: owner, repo, branch (simple form)
  → Authenticate GitHub (if not already)
  → Done ✅ (webhook registration is hidden)
```

---

## 🏗️ Architecture

### Phase 1: Database & Models

#### 1.1 New Table: `trigger_templates`
```sql
CREATE TABLE trigger_templates (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  service STRING,              -- "github", "slack", "stripe", etc.
  trigger_type STRING,         -- "on_push", "on_message", "on_charge"
  name STRING,                 -- "On Push", "On Message", "On Charge"
  description TEXT,            -- User-facing description
  events JSON,                 -- ["push"] or ["message", "app_mention"]
  required_config JSON,        -- {owner: required, repo: required, branch: optional}
  optional_config JSON,        -- {tags: optional}
  webhook_provider STRING,     -- "github", "slack", "stripe" (for auto-reg)
  is_polling BOOLEAN,          -- true if this uses polling instead of webhooks
  polling_interval INT,        -- seconds, if polling
  dedup_key STRING,            -- "id" or "timestamp", if polling
  docs_url STRING,             -- link to trigger docs
  is_active BOOLEAN,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  UNIQUE KEY (service, trigger_type)
);
```

#### 1.2 Seed Data Example
```php
// database/seeders/TriggerTemplateSeeder.php

TriggerTemplate::create([
  'service' => 'github',
  'trigger_type' => 'on_push',
  'name' => 'On Push',
  'description' => 'Triggers when code is pushed to a repository',
  'events' => ['push'],
  'required_config' => [
    'owner' => ['type' => 'string', 'label' => 'Repository Owner'],
    'repository' => ['type' => 'string', 'label' => 'Repository Name'],
  ],
  'optional_config' => [
    'branch' => ['type' => 'string', 'label' => 'Branch (optional)', 'default' => 'main'],
  ],
  'webhook_provider' => 'github',
  'is_polling' => false,
  'docs_url' => 'https://docs.github.com/en/developers/webhooks-and-events/webhooks/about-webhooks',
  'is_active' => true,
]);

TriggerTemplate::create([
  'service' => 'slack',
  'trigger_type' => 'on_message',
  'name' => 'On New Message',
  'description' => 'Triggers when a message is posted to a channel',
  'events' => ['message'],
  'required_config' => [
    'channel' => ['type' => 'string', 'label' => 'Channel ID or Name'],
  ],
  'optional_config' => [
    'include_threads' => ['type' => 'boolean', 'label' => 'Include thread replies', 'default' => false],
  ],
  'webhook_provider' => 'slack',
  'is_polling' => false,
  'docs_url' => 'https://api.slack.com/events/message',
  'is_active' => true,
]);

TriggerTemplate::create([
  'service' => 'google_sheets',
  'trigger_type' => 'on_new_row',
  'name' => 'On New Row',
  'description' => 'Triggers when a new row is added to a spreadsheet',
  'events' => [],
  'required_config' => [
    'spreadsheet_id' => ['type' => 'string', 'label' => 'Spreadsheet ID'],
    'sheet_name' => ['type' => 'string', 'label' => 'Sheet Name', 'default' => 'Sheet1'],
  ],
  'optional_config' => [
    'check_column' => ['type' => 'string', 'label' => 'Check Column (A, B, C)', 'default' => 'A'],
  ],
  'webhook_provider' => null,
  'is_polling' => true,
  'polling_interval' => 300,  // 5 minutes
  'dedup_key' => 'row_index',
  'docs_url' => 'https://developers.google.com/sheets/api',
  'is_active' => true,
]);
```

#### 1.3 Update `workflows` table
```sql
ALTER TABLE workflows ADD COLUMN trigger_template_id BIGINT AFTER trigger_type;
ALTER TABLE workflows ADD COLUMN trigger_config JSON AFTER trigger_template_id;
ALTER TABLE workflows ADD FOREIGN KEY (trigger_template_id) REFERENCES trigger_templates(id);
```

---

### Phase 2: Backend Models & Services

#### 2.1 Create Model: `TriggerTemplate`
```php
// app/Models/TriggerTemplate.php
class TriggerTemplate extends Model {
    protected $fillable = [
        'service', 'trigger_type', 'name', 'description',
        'events', 'required_config', 'optional_config',
        'webhook_provider', 'is_polling', 'polling_interval',
        'dedup_key', 'docs_url', 'is_active'
    ];
    
    protected function casts(): array {
        return [
            'events' => 'array',
            'required_config' => 'array',
            'optional_config' => 'array',
            'is_polling' => 'boolean',
        ];
    }
    
    public function workflows(): HasMany {
        return $this->hasMany(Workflow::class);
    }
}
```

#### 2.2 Create Service: `TriggerTemplateService`
```php
// app/Services/TriggerTemplateService.php
class TriggerTemplateService {
    
    /**
     * Get all trigger templates grouped by service
     */
    public function getByService(string $service): Collection {
        return TriggerTemplate::where('service', $service)
            ->where('is_active', true)
            ->get();
    }
    
    /**
     * Get all services with available triggers
     */
    public function getAllServices(): array {
        return TriggerTemplate::where('is_active', true)
            ->distinct('service')
            ->pluck('service')
            ->toArray();
    }
    
    /**
     * Validate trigger config against template requirements
     */
    public function validateConfig(TriggerTemplate $template, array $config): bool {
        $required = $template->required_config ?? [];
        
        foreach ($required as $key => $spec) {
            if (!isset($config[$key]) || empty($config[$key])) {
                throw new \Exception("Missing required config: {$key}");
            }
        }
        
        return true;
    }
    
    /**
     * When workflow is published, register the trigger
     */
    public function registerTrigger(Workflow $workflow): void {
        $template = $workflow->triggerTemplate;
        
        if (!$template) {
            return;
        }
        
        if ($template->is_polling) {
            $this->registerPollingTrigger($workflow, $template);
        } else {
            $this->registerWebhookTrigger($workflow, $template);
        }
    }
    
    private function registerWebhookTrigger(Workflow $workflow, TriggerTemplate $template): void {
        // Use existing WebhookAutoRegistrationService
        // but pass template info
        
        // Build provider config from workflow trigger_config
        $providerConfig = $workflow->trigger_config ?? [];
        
        // Extract credentials for this service
        $credentials = $this->resolveCredentials($workflow, $template->service);
        
        // Get registrar for provider
        $registrar = WebhookRegistrarRegistry::resolveRegisterable(
            $template->webhook_provider
        );
        
        if (!$registrar) {
            throw new \Exception("No registrar for: {$template->webhook_provider}");
        }
        
        // Call registrar
        $callbackUrl = "https://linkflow.io/api/v1/webhook/" . Str::uuid();
        $result = $registrar->register(
            $callbackUrl,
            $template->events,
            $credentials,
            $providerConfig
        );
        
        // Save webhook
        Webhook::create([
            'workflow_id' => $workflow->id,
            'workspace_id' => $workflow->workspace_id,
            'uuid' => $callbackUrl,
            'provider' => $template->webhook_provider,
            'external_webhook_id' => $result['external_id'],
            'external_webhook_secret' => $result['secret'],
            'provider_config' => $providerConfig,
            'registered_url' => $callbackUrl,
            'is_active' => true,
        ]);
    }
    
    private function registerPollingTrigger(Workflow $workflow, TriggerTemplate $template): void {
        // Create PollingTrigger record
        PollingTrigger::create([
            'workflow_id' => $workflow->id,
            'workspace_id' => $workflow->workspace_id,
            'endpoint_url' => $workflow->trigger_config['endpoint_url'] ?? null,
            'http_method' => 'GET',
            'headers' => null,
            'dedup_key' => $template->dedup_key,
            'interval_seconds' => $template->polling_interval,
            'is_active' => true,
            'auth_config' => $this->resolveCredentials($workflow, $template->service),
            'next_poll_at' => now(),
        ]);
    }
    
    private function resolveCredentials(Workflow $workflow, string $service): ?array {
        // Load credential for this service from workspace
        // User must have already authenticated this service
        return $workflow->workspace
            ->credentials()
            ->where('type', $service)
            ->first()
            ?->data;
    }
}
```

#### 2.3 Update Workflow Model
```php
// app/Models/Workflow.php
class Workflow extends Model {
    
    public function triggerTemplate(): BelongsTo {
        return $this->belongsTo(TriggerTemplate::class, 'trigger_template_id');
    }
    
    /**
     * Get trigger node definition (for engine execution)
     */
    public function getTriggerNodeDefinition(): array {
        $template = $this->triggerTemplate;
        
        if (!$template) {
            // Fallback to manual webhook/cron
            return $this->getLegacyTriggerNode();
        }
        
        return [
            'id' => 'trigger',
            'type' => 'trigger',
            'provider' => $template->webhook_provider ?? 'polling',
            'trigger_type' => $template->trigger_type,
            'service' => $template->service,
            'events' => $template->events,
            'config' => array_merge(
                $template->optional_config ?? [],
                $this->trigger_config ?? []
            ),
        ];
    }
}
```

---

### Phase 3: API Endpoints

#### 3.1 Controller: `TriggerTemplateController`
```php
// app/Http/Controllers/Api/V1/TriggerTemplateController.php
class TriggerTemplateController {
    
    /**
     * GET /api/v1/trigger-templates
     * Returns all available trigger templates grouped by service
     */
    public function index(): JsonResponse {
        $services = TriggerTemplate::where('is_active', true)
            ->get()
            ->groupBy('service')
            ->map(function ($templates) {
                return $templates->map(fn ($t) => [
                    'id' => $t->id,
                    'type' => $t->trigger_type,
                    'name' => $t->name,
                    'description' => $t->description,
                    'required_config' => $t->required_config,
                    'optional_config' => $t->optional_config,
                    'docs_url' => $t->docs_url,
                ]);
            });
        
        return response()->json($services);
    }
    
    /**
     * GET /api/v1/trigger-templates/{service}
     * Returns triggers for a specific service
     */
    public function byService(string $service): JsonResponse {
        $triggers = TriggerTemplate::where('service', $service)
            ->where('is_active', true)
            ->get();
        
        return response()->json($triggers);
    }
    
    /**
     * GET /api/v1/trigger-templates/{service}/{trigger_type}
     * Returns single trigger template
     */
    public function show(string $service, string $triggerType): JsonResponse {
        $template = TriggerTemplate::where('service', $service)
            ->where('trigger_type', $triggerType)
            ->firstOrFail();
        
        return response()->json($template);
    }
}
```

#### 3.2 Update WorkflowController
```php
// app/Http/Controllers/Api/V1/WorkflowController.php
class WorkflowController {
    
    /**
     * POST /api/v1/workspaces/{id}/workflows/{id}/setup-trigger
     * Configure trigger from template
     */
    public function setupTrigger(Request $request, Workflow $workflow): JsonResponse {
        $validated = $request->validate([
            'trigger_template_id' => 'required|exists:trigger_templates,id',
            'trigger_config' => 'required|array',
            'authenticate' => 'nullable|boolean', // If true, redirect to OAuth
        ]);
        
        $template = TriggerTemplate::findOrFail($validated['trigger_template_id']);
        
        // Validate config against template
        $service = $template->service;
        $triggerTemplateService = app(TriggerTemplateService::class);
        $triggerTemplateService->validateConfig($template, $validated['trigger_config']);
        
        // Check if user has credentials for this service
        $hasCredential = $request->user()
            ->workspace
            ->credentials()
            ->where('type', $service)
            ->exists();
        
        if (!$hasCredential && $validated['authenticate'] !== true) {
            // Return OAuth redirect URL
            $oauthService = app(OAuthCredentialFlowService::class);
            $oauth = $oauthService->initiate(
                $request->user()->workspace,
                $request->user(),
                $service
            );
            
            return response()->json([
                'status' => 'requires_auth',
                'authorization_url' => $oauth['authorization_url'],
                'state_token' => $oauth['state_token'],
            ]);
        }
        
        // Save trigger template selection
        $workflow->update([
            'trigger_template_id' => $template->id,
            'trigger_config' => $validated['trigger_config'],
        ]);
        
        return response()->json([
            'status' => 'configured',
            'trigger' => [
                'service' => $template->service,
                'type' => $template->trigger_type,
                'name' => $template->name,
                'config' => $workflow->trigger_config,
            ],
        ]);
    }
}
```

---

### Phase 4: Frontend UI

#### 4.1 Trigger Picker Component (React)
```tsx
// frontend/src/pages/editor/WorkflowEditor/_partial/TriggerPicker.tsx

interface TriggerPickerProps {
  onSelect: (template: TriggerTemplate, config: Record<string, any>) => void;
}

export const TriggerPicker: React.FC<TriggerPickerProps> = ({ onSelect }) => {
  const [step, setStep] = useState<'service' | 'type' | 'config'>('service');
  const [selectedService, setSelectedService] = useState<string | null>(null);
  const [selectedTemplate, setSelectedTemplate] = useState<TriggerTemplate | null>(null);
  
  const { data: services } = useQuery(
    ['trigger-templates'],
    () => api.get('/trigger-templates')
  );
  
  const { data: templates } = useQuery(
    ['trigger-templates', selectedService],
    () => selectedService ? api.get(`/trigger-templates/${selectedService}`) : null,
    { enabled: !!selectedService }
  );
  
  // Step 1: Select Service
  if (step === 'service') {
    return (
      <div className="p-4">
        <h3 className="text-lg font-bold mb-4">Select a Service</h3>
        <div className="grid grid-cols-2 gap-2">
          {Object.keys(services || {}).map(service => (
            <button
              key={service}
              onClick={() => {
                setSelectedService(service);
                setStep('type');
              }}
              className="p-3 border rounded hover:bg-gray-100 text-left"
            >
              <div className="font-bold capitalize">{service}</div>
              <div className="text-sm text-gray-500">
                {(services?.[service] || []).length} triggers
              </div>
            </button>
          ))}
        </div>
      </div>
    );
  }
  
  // Step 2: Select Trigger Type
  if (step === 'type') {
    return (
      <div className="p-4">
        <button
          onClick={() => {
            setSelectedService(null);
            setStep('service');
          }}
          className="text-blue-500 mb-4"
        >
          ← Back
        </button>
        <h3 className="text-lg font-bold mb-4">
          Select Trigger Type ({selectedService})
        </h3>
        <div className="space-y-2">
          {(templates || []).map(template => (
            <button
              key={template.id}
              onClick={() => {
                setSelectedTemplate(template);
                setStep('config');
              }}
              className="w-full p-3 border rounded hover:bg-gray-100 text-left"
            >
              <div className="font-bold">{template.name}</div>
              <div className="text-sm text-gray-500">{template.description}</div>
            </button>
          ))}
        </div>
      </div>
    );
  }
  
  // Step 3: Configure
  if (step === 'config' && selectedTemplate) {
    return (
      <TriggerConfigForm
        template={selectedTemplate}
        onSubmit={(config) => onSelect(selectedTemplate, config)}
        onBack={() => {
          setSelectedTemplate(null);
          setStep('type');
        }}
      />
    );
  }
};
```

#### 4.2 Config Form Component
```tsx
// frontend/src/pages/editor/WorkflowEditor/_partial/TriggerConfigForm.tsx

interface TriggerConfigFormProps {
  template: TriggerTemplate;
  onSubmit: (config: Record<string, any>) => void;
  onBack: () => void;
}

export const TriggerConfigForm: React.FC<TriggerConfigFormProps> = ({
  template,
  onSubmit,
  onBack,
}) => {
  const [config, setConfig] = useState<Record<string, any>>({});
  const [loading, setLoading] = useState(false);
  const [authUrl, setAuthUrl] = useState<string | null>(null);
  
  const handleSubmit = async () => {
    try {
      setLoading(true);
      const response = await api.post(`/workflows/${workflowId}/setup-trigger`, {
        trigger_template_id: template.id,
        trigger_config: config,
        authenticate: false,
      });
      
      if (response.data.status === 'requires_auth') {
        // Redirect to OAuth
        setAuthUrl(response.data.authorization_url);
        window.location.href = response.data.authorization_url;
        return;
      }
      
      onSubmit(config);
    } catch (error) {
      console.error('Failed to setup trigger:', error);
    } finally {
      setLoading(false);
    }
  };
  
  return (
    <div className="p-4">
      <button onClick={onBack} className="text-blue-500 mb-4">
        ← Back
      </button>
      
      <h3 className="text-lg font-bold mb-4">{template.name}</h3>
      <p className="text-gray-600 mb-6">{template.description}</p>
      
      {/* Required fields */}
      <div className="space-y-4">
        <h4 className="font-bold">Required</h4>
        {Object.entries(template.required_config || {}).map(([key, spec]: any) => (
          <div key={key}>
            <label className="block font-medium mb-1">{spec.label}</label>
            <input
              type={spec.type === 'boolean' ? 'checkbox' : 'text'}
              value={config[key] || ''}
              onChange={(e) =>
                setConfig({ ...config, [key]: e.target.value })
              }
              className="w-full border rounded px-3 py-2"
              required
            />
          </div>
        ))}
      </div>
      
      {/* Optional fields */}
      {template.optional_config && (
        <div className="space-y-4 mt-6">
          <h4 className="font-bold">Optional</h4>
          {Object.entries(template.optional_config || {}).map(([key, spec]: any) => (
            <div key={key}>
              <label className="block font-medium mb-1">{spec.label}</label>
              <input
                type={spec.type === 'boolean' ? 'checkbox' : 'text'}
                placeholder={spec.default}
                value={config[key] || ''}
                onChange={(e) =>
                  setConfig({ ...config, [key]: e.target.value })
                }
                className="w-full border rounded px-3 py-2"
              />
            </div>
          ))}
        </div>
      )}
      
      <div className="mt-8 flex gap-2">
        <button
          onClick={handleSubmit}
          disabled={loading}
          className="flex-1 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 disabled:opacity-50"
        >
          {loading ? 'Setting up...' : 'Configure Trigger'}
        </button>
      </div>
    </div>
  );
};
```

---

## 📋 Implementation Checklist

### Week 1: Database & Backend Models
- [ ] Create migration: `trigger_templates` table
- [ ] Create `TriggerTemplate` model
- [ ] Create `TriggerTemplateService`
- [ ] Seed initial templates (GitHub, Slack, Stripe, Google Sheets, Airtable)
- [ ] Write tests for validation

### Week 2: API Endpoints
- [ ] Create `TriggerTemplateController`
- [ ] Implement `GET /trigger-templates`
- [ ] Implement `GET /trigger-templates/{service}`
- [ ] Implement `POST /workflows/{id}/setup-trigger`
- [ ] Update WorkflowController (publish flow)
- [ ] Wire in OAuth redirect logic

### Week 3: Frontend UI
- [ ] Build `TriggerPicker` component (service selection)
- [ ] Build `TriggerConfigForm` component (config input)
- [ ] Integrate into WorkflowEditor
- [ ] Add to workflow creation flow
- [ ] Handle OAuth callback redirect

### Week 4: Integration & Testing
- [ ] Test end-to-end: GitHub trigger
- [ ] Test end-to-end: Slack trigger
- [ ] Test end-to-end: Stripe trigger
- [ ] Test end-to-end: Google Sheets polling
- [ ] Handle edge cases (missing credentials, API errors)
- [ ] Add error messaging

### Week 5: Polish & Docs
- [ ] Add trigger template docs page
- [ ] Create in-app help/tooltips
- [ ] Improve UX/styling
- [ ] Add support for more triggers (Discord, Airtable, etc.)
- [ ] Performance optimization

---

## 🎯 Success Criteria

Users should be able to:

```
✅ 1. Open workflow editor → Click "Add Trigger"
✅ 2. See list of services (no "webhook" terminology)
✅ 3. Select service (GitHub) → See trigger types
✅ 4. Select trigger type (On Push) → Fill config form
✅ 5. Authenticate if needed (OAuth redirect)
✅ 6. Hit "Done" → Webhook auto-registered, workflow ready
✅ 7. Publish workflow → Events automatically trigger executions
✅ 8. No manual webhook setup, no copying URLs
```

---

## 🚀 Additional Features (Phase 2)

### Custom Webhooks
```
User can still create custom webhooks if they want
├─ "Generic Webhook" template
├─ Manual URL entry
├─ Custom signature verification
└─ Advanced users benefit
```

### More Services (Batch Add)
```
Create templates for:
├─ Airtable: On New Record
├─ Discord: On Message
├─ Google Forms: On New Response
├─ Mailchimp: On New Email
├─ Twilio: On SMS
├─ etc.
```

### Trigger Marketplace
```
Future: Allow users to share custom triggers
├─ Community-created templates
├─ Ratings/reviews
├─ Versioning
└─ Like n8n community nodes
```

---

## 📚 Related Files to Create/Update

**Create:**
- `app/Models/TriggerTemplate.php`
- `app/Services/TriggerTemplateService.php`
- `app/Http/Controllers/Api/V1/TriggerTemplateController.php`
- `database/migrations/*_create_trigger_templates_table.php`
- `database/seeders/TriggerTemplateSeeder.php`
- `frontend/src/pages/editor/WorkflowEditor/_partial/TriggerPicker.tsx`
- `frontend/src/pages/editor/WorkflowEditor/_partial/TriggerConfigForm.tsx`

**Update:**
- `app/Models/Workflow.php` (add triggerTemplate relation, update publish logic)
- `app/Http/Controllers/Api/V1/WorkflowController.php` (add setupTrigger endpoint)
- `app/Services/WorkflowAutoRegistrationService.php` (integrate with templates)
- `frontend/src/pages/editor/WorkflowEditor/Build/` (add trigger picker to UI)

---

## 💡 Key Advantages Over Current System

| Aspect | Current | With Templates |
|--------|---------|---|
| **User sees** | "Webhook" + "Polling" | "GitHub: On Push" |
| **Setup steps** | 5-7 steps | 3-4 steps |
| **Knowledge required** | Webhooks, events, config | Just auth + config |
| **Error handling** | User's responsibility | Built-in per template |
| **Scalability** | Add registrar per service | Add template rows |
| **Docs needed** | Webhook setup docs | Template-specific docs |

---

## ⚠️ Migration Path (Backward Compatibility)

**Old workflows with manual webhooks still work:**
```php
if ($workflow->trigger_template_id) {
    // New template-based flow
} else {
    // Legacy webhook/polling flow (still supported)
}
```

No breaking changes. Old workflows continue running while new workflows use templates.

---

## 📞 Questions Before Starting?

1. **Priority**: Which 5 services should we template first?
   - Suggested: GitHub, Slack, Stripe, Google Sheets, Airtable
   
2. **Polling templates**: Include Google Sheets polling, or wait?
   - Recommended: Yes (easy to implement, high user demand)
   
3. **Mobile UI**: Do we need mobile-responsive trigger picker?
   - Recommended: Yes (same as other pickers)
   
4. **Analytics**: Track which triggers are used most?
   - Recommended: Yes (informs future templates)

---

## 🏁 Next Steps

1. Approve this plan
2. Prioritize services to template first
3. Start with Phase 1: Database & Models
4. Build out incrementally, test each service as we go

**Estimated timeline**: 4-5 weeks with 1 developer
