import { apiClient } from '@/api/client';

export function useTriggerAPI() {
  return {
    /**
     * Get all available trigger categories, types, and fields
     */
    getAvailable: async () => {
      const response = await apiClient.get('/trigger/available');
      return response.data;
    },

    /**
     * Create a new trigger for a workflow
     */
    createTrigger: async (
      workflowId: string,
      data: {
        trigger_type_id: string;
        credential_id?: string | null;
        name?: string;
        field_values?: Record<string, string>;
      }
    ) => {
      const response = await apiClient.post(`/workflows/${workflowId}/trigger`, data);
      return response.data.data;
    },

    /**
     * Update trigger configuration
     */
    updateTrigger: async (
      workflowId: string,
      triggerId: string,
      data: {
        name?: string;
        field_values?: Record<string, string>;
      }
    ) => {
      const response = await apiClient.put(
        `/workflows/${workflowId}/trigger/${triggerId}`,
        data
      );
      return response.data.data;
    },

    /**
     * Delete a trigger
     */
    deleteTrigger: async (workflowId: string, triggerId: string) => {
      await apiClient.delete(`/workflows/${workflowId}/trigger/${triggerId}`);
    },

    /**
     * Publish (activate) a trigger
     */
    publishTrigger: async (workflowId: string, triggerId: string) => {
      const response = await apiClient.post(
        `/workflows/${workflowId}/trigger/${triggerId}/publish`
      );
      return response.data.data;
    },

    /**
     * Unpublish (deactivate) a trigger
     */
    unpublishTrigger: async (workflowId: string, triggerId: string) => {
      const response = await apiClient.post(
        `/workflows/${workflowId}/trigger/${triggerId}/unpublish`
      );
      return response.data.data;
    },

    /**
     * Get trigger execution history
     */
    getTriggerExecutions: async (
      workflowId: string,
      triggerId: string,
      page: number = 1,
      perPage: number = 25
    ) => {
      const response = await apiClient.get(
        `/workflows/${workflowId}/trigger/${triggerId}/executions`,
        {
          params: { page, per_page: perPage },
        }
      );
      return response.data;
    },

    /**
     * Set polling interval for a trigger
     */
    setPollingInterval: async (
      workflowId: string,
      triggerId: string,
      intervalSeconds: number
    ) => {
      const response = await apiClient.put(
        `/workflows/${workflowId}/trigger/${triggerId}/polling-interval`,
        { interval_seconds: intervalSeconds }
      );
      return response.data.data;
    },

    /**
     * Set schedule expression for a trigger
     */
    setSchedule: async (
      workflowId: string,
      triggerId: string,
      expression: string,
      timezone: string = 'UTC'
    ) => {
      const response = await apiClient.put(
        `/workflows/${workflowId}/trigger/${triggerId}/schedule`,
        { expression, timezone }
      );
      return response.data.data;
    },

    /**
     * Test a trigger (manual execution)
     */
    testTrigger: async (workflowId: string, triggerId: string) => {
      const response = await apiClient.post(
        `/workflows/${workflowId}/execute`,
        { trigger_id: triggerId }
      );
      return response.data.data;
    },
  };
}
