import { useQuery, useMutation } from '@tanstack/react-query';
import { useCredentialAPI } from '@/api/modules/credentials';
import './ConnectAccount.css';

interface ConnectAccountProps {
  triggerType: any;
  workspaceId: string;
  selectedCredentialId: string | null;
  onCredentialSelect: (credentialId: string | null) => void;
}

export default function ConnectAccount({
  triggerType,
  workspaceId,
  selectedCredentialId,
  onCredentialSelect,
}: ConnectAccountProps) {
  const credentialAPI = useCredentialAPI();

  // Fetch existing credentials for this service
  const { data: credentials = [] } = useQuery({
    queryKey: ['credentials', workspaceId, triggerType.slug],
    queryFn: () =>
      credentialAPI.listByType(
        workspaceId,
        getCredentialTypeForService(triggerType.slug)
      ),
  });

  // OAuth mutation
  const oauthMutation = useMutation({
    mutationFn: () =>
      credentialAPI.initiateOAuth(
        workspaceId,
        getCredentialTypeForService(triggerType.slug)
      ),
    onSuccess: (result) => {
      // Redirect to OAuth provider
      window.location.href = result.authorization_url;
    },
  });

  const service = triggerType.slug || 'Unknown';

  return (
    <div className="connect-account">
      <div className="connect-account__header">
        <h3>Connect Your Account</h3>
        <p>We need access to your {service} account to create this trigger</p>
      </div>

      {/* Existing Credentials */}
      {credentials.length > 0 && (
        <div className="connect-account__section">
          <h4>Existing Connections</h4>
          <div className="connect-account__list">
            {credentials.map((cred: any) => (
              <button
                key={cred.id}
                className={`connect-account__item ${
                  selectedCredentialId === cred.id
                    ? 'connect-account__item--selected'
                    : ''
                }`}
                onClick={() => onCredentialSelect(cred.id)}
              >
                <div className="connect-account__icon">✓</div>
                <div className="connect-account__details">
                  <div className="connect-account__name">
                    {cred.name || cred.identifier}
                  </div>
                  <div className="connect-account__created">
                    Connected{' '}
                    {new Date(cred.created_at).toLocaleDateString()}
                  </div>
                </div>
              </button>
            ))}
          </div>
        </div>
      )}

      {/* Add New Connection */}
      <div className="connect-account__section">
        <h4>Add New Connection</h4>
        <button
          className="connect-account__oauth-btn"
          onClick={() => oauthMutation.mutate()}
          disabled={oauthMutation.isPending}
        >
          {oauthMutation.isPending ? (
            '🔄 Redirecting...'
          ) : (
            <>🔐 Connect with {getServiceDisplayName(service)}</>
          )}
        </button>
        <p className="connect-account__info">
          You'll be redirected to {getServiceDisplayName(service)} to authorize
          access
        </p>
      </div>

      {/* Security Note */}
      <div className="connect-account__security">
        <div className="connect-account__security-icon">🔒</div>
        <div className="connect-account__security-text">
          <strong>Your data is safe.</strong> We only request the minimum
          permissions needed and store credentials encrypted.
        </div>
      </div>
    </div>
  );
}

function getCredentialTypeForService(service: string): string {
  const mapping: Record<string, string> = {
    github: 'github',
    slack: 'slack',
    stripe: 'stripe',
    google_sheets: 'google_oauth',
    airtable: 'airtable',
    discord: 'discord',
    gmail: 'gmail',
  };
  return mapping[service] || service;
}

function getServiceDisplayName(service: string): string {
  const names: Record<string, string> = {
    github: 'GitHub',
    slack: 'Slack',
    stripe: 'Stripe',
    google_sheets: 'Google',
    google_oauth: 'Google',
    airtable: 'Airtable',
    discord: 'Discord',
    gmail: 'Gmail',
  };
  return names[service] || service;
}
