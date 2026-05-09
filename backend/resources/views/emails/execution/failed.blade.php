<x-mail::message>
# Workflow Execution Failed

Hi there,

Your workflow **{{ $workflow->name }}** encountered an error and the execution has failed.

**Execution ID:** {{ $execution->id }}  
**Failed At:** {{ $execution->updated_at->format('M d, Y H:i:s') }}

<x-mail::button :url="config('app.frontend_url', config('app.url')) . '/workspaces/' . $workflow->workspace_id . '/workflows/' . $workflow->id . '/executions/' . $execution->id">
View Error Details
</x-mail::button>

Our AI Assistant might have also generated an auto-fix suggestion for this failure. Check the execution logs!

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
