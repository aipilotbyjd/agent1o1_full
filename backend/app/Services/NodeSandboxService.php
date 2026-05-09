<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NodeSandboxService
{
    /**
     * Determine if a safe, sandboxed execution is possible.
     */
    public function canExecute(): bool
    {
        $process = new Process(['node', '-v']);
        $process->run();
        return $process->isSuccessful();
    }

    /**
     * Execute Javascript code with the given execution state/inputs.
     * WARNING: This runs locally via Process. A true production SaaS
     * might dispatch this to a Lambda, Firecracker microVM, or isolated Docker image.
     */
    public function executeCode(string $code, array $inputData): array
    {
        $temporaryFilename = 'sandbox_' . Str::random(10) . '.js';
        
        $wrappedCode = "
            const inputData = " . json_encode($inputData) . ";
            
            async function main() {
                try {
                    // Inject user code
                    $code
                } catch (error) {
                    console.error('USER_CODE_ERROR:', error.toString());
                    process.exit(1);
                }
            }
            
            main();
        ";

        Storage::disk('local')->put('sandbox/' . $temporaryFilename, $wrappedCode);
        $filePath = Storage::disk('local')->path('sandbox/' . $temporaryFilename);

        // Run the process with a 5-second timeout and 128MB limit via arguments
        $process = new Process(['node', '--max-old-space-size=128', $filePath]);
        $process->setTimeout(5);

        try {
            $process->mustRun();
            
            // Try extracting valid JSON from stdout if the user console.logs a JSON object
            $output = $process->getOutput();
            // Basic cleanup
            Storage::disk('local')->delete('sandbox/' . $temporaryFilename);
            
            return [
                'success' => true,
                'output' => $output,
            ];
            
        } catch (ProcessTimedOutException $e) {
            Storage::disk('local')->delete('sandbox/' . $temporaryFilename);
            throw new \Exception('Code execution timed out after 5 seconds.');
        } catch (ProcessFailedException $e) {
            Storage::disk('local')->delete('sandbox/' . $temporaryFilename);
            throw new \Exception('Execution error: ' . $e->getMessage());
        }
    }
}
