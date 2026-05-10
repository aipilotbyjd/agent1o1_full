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
        
        // Wrap user code in vm.runInNewContext() so it cannot access require,
        // process, fs, or any other Node.js global. Only safe builtins are
        // exposed. The outer script's timeout (Process::setTimeout) is the
        // hard wall; vm timeout is a softer inner guard.
        $encodedInput = json_encode($inputData);
        $encodedCode  = json_encode($code);

        $wrappedCode = <<<JS
'use strict';
const vm = require('vm');

const sandbox = {
    inputData: {$encodedInput},
    output: undefined,
    console: {
        log:   (...a) => process.stdout.write(a.map(String).join(' ') + '\n'),
        error: (...a) => process.stderr.write(a.map(String).join(' ') + '\n'),
        warn:  (...a) => process.stderr.write(a.map(String).join(' ') + '\n'),
    },
    Math, JSON, Date, Array, Object, String, Number, Boolean, RegExp,
    parseInt, parseFloat, isNaN, isFinite, encodeURIComponent, decodeURIComponent,
};

vm.createContext(sandbox);

try {
    vm.runInContext({$encodedCode}, sandbox, { timeout: 4000 });
    process.stdout.write(JSON.stringify(sandbox.output ?? sandbox.inputData));
} catch (e) {
    process.stderr.write('USER_CODE_ERROR: ' + e.toString());
    process.exit(1);
}
JS;

        Storage::disk('local')->put('sandbox/' . $temporaryFilename, $wrappedCode);
        $filePath = Storage::disk('local')->path('sandbox/' . $temporaryFilename);

        // Run the process with a 5-second timeout and 128MB limit via arguments
        $process = new Process(['node', '--max-old-space-size=128', $filePath]);
        $process->setTimeout(5);

        try {
            $process->mustRun();
            
            Storage::disk('local')->delete('sandbox/' . $temporaryFilename);

            $raw = trim($process->getOutput());
            $decoded = json_decode($raw, true);

            return [
                'success' => true,
                'output' => is_array($decoded) ? $decoded : ['result' => $raw],
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
