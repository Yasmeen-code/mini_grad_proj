<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Code;

class CodeController extends Controller
{
    public function analyze(Request $request)
    {
        $code = $request->input('code');

        // Check if it's C++ code (contains #include or main)
        $isCpp = strpos($code, '#include') !== false || strpos($code, 'int main') !== false;

        if ($isCpp) {
            // Store code in temporary C++ file
            $file = storage_path('app/code.cpp');
            file_put_contents($file, $code);

            // Generate assembly code with Intel syntax for better readability
            $asmFile = storage_path('app/code.s');
            $compileOutput = shell_exec("g++ \"$file\" -S -masm=intel -O0 -o \"$asmFile\" 2>&1");
            if ($compileOutput) {
                // Compilation failed
                $output = "Compilation Error:\n" . $compileOutput;
            } else {
                // Read the assembly code
                $assembly = file_get_contents($asmFile);

                // Extract only the main function for simplicity
                $lines = explode("\n", $assembly);
                $inMain = false;
                $mainAssembly = [];

                foreach ($lines as $line) {
                    if (strpos($line, '_main:') !== false) {
                        $inMain = true;
                        $mainAssembly[] = $line;
                    } elseif ($inMain) {
                        if (strpos($line, 'ret') !== false) {
                            $mainAssembly[] = $line;
                            break;
                        } elseif (trim($line) !== '' && !preg_match('/^\s*\./', $line)) {
                            $mainAssembly[] = $line;
                        }
                    }
                }

                $output = implode("\n", $mainAssembly);
                if (empty($output)) {
                    $output = $assembly; // Fallback to full assembly if main not found
                } else {
                    // Extract strings and variables from C++ code for dynamic data section
                    $strings = [];
                    $variables = [];

                    // Extract strings from cout statements
                    preg_match_all('/cout\s*<<\s*"([^"]*)"/', $code, $matches);
                    if (!empty($matches[1])) {
                        $strings = $matches[1];
                    }

                    // Extract variables (simple int declarations)
                    preg_match_all('/int\s+(\w+)\s*=\s*(\d+)/', $code, $varMatches);
                    if (!empty($varMatches[1])) {
                        for ($i = 0; $i < count($varMatches[1]); $i++) {
                            $variables[$varMatches[1][$i]] = $varMatches[2][$i];
                        }
                    }

                    // Format as NASM
                    $nasmOutput = "bits 32\n";
                    $nasmOutput .= "global main\n";
                    $nasmOutput .= "extern printf\n";
                    $nasmOutput .= "extern scanf\n";
                    $nasmOutput .= "\n";

                    // Dynamic data section
                    $nasmOutput .= "section .data\n";
                    foreach ($strings as $index => $str) {
                        $nasmOutput .= "    str{$index} db '{$str}', 10, 0\n";
                    }
                    foreach ($variables as $name => $value) {
                        $nasmOutput .= "    {$name} dd {$value}\n";
                    }
                    if (empty($strings) && empty($variables)) {
                        $nasmOutput .= "    ; No data defined\n";
                    }
                    $nasmOutput .= "\n";

                    $nasmOutput .= "section .text\n";
                    $nasmOutput .= "main:\n";

                    // Convert instructions to NASM style, filtering out library calls
                    foreach ($mainAssembly as $line) {
                        if (strpos($line, '_main:') !== false) {
                            continue; // Skip the label
                        }
                        // Skip library calls and complex C++ runtime calls
                        if (
                            preg_match('/call\s+__/', $line) ||
                            preg_match('/call\s+___/', $line) ||
                            preg_match('/OFFSET FLAT:/', $line) ||
                            preg_match('/lea\s+ecx,\s*\[esp\+4\]/', $line) ||
                            preg_match('/and\s+esp,\s*-16/', $line) ||
                            preg_match('/push\s+DWORD PTR \[ecx-4\]/', $line) ||
                            preg_match('/lea\s+esp,\s*\[ecx-4\]/', $line) ||
                            preg_match('/LFB\d+:/', $line) ||
                            preg_match('/LC\d+:/', $line) ||
                            preg_match('/push\s+ebp/', $line) ||
                            preg_match('/mov\s+ebp,\s*esp/', $line) ||
                            preg_match('/mov\s+esp,\s*ebp/', $line) ||
                            preg_match('/pop\s+ebp/', $line) ||
                            preg_match('/sub\s+esp,\s*\d+/', $line) ||
                            preg_match('/mov\s+ecx,\s*DWORD PTR \[ebp-4\]/', $line) ||
                            preg_match('/leave/', $line)
                        ) {
                            continue;
                        }
                        // Keep only simple instructions like mov, add, cmp, etc.
                        if (preg_match('/^\s*(mov|add|sub|cmp|jmp|je|jne|jg|jl|jge|jle|push|pop|ret|lea|and|or|xor|inc|dec|imul|idiv)\s+/i', $line)) {
                            $nasmOutput .= "    " . $line . "\n";
                        }
                    }

                    $output = $nasmOutput;
                }
            }
        } else {
            // Store code in temporary Python file
            $file = storage_path('app/code.py');
            file_put_contents($file, $code);

            // Execute code using python
            $output = shell_exec("python \"$file\" 2>&1");
        }

        return response()->json([
            'output' => $output
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'source_code' => 'required|string',
            'tokens' => 'nullable|array',
            'errors' => 'nullable|array',
            'ast' => 'nullable|array',
            'assembly' => 'nullable|array',
            'machine_code' => 'nullable|array',
            'cpu_simulation' => 'nullable|array',
            'compilation_steps' => 'nullable|array'
        ]);

        $code = Code::create($request->all());

        return response()->json([
            'message' => 'Code snippet saved successfully',
            'code' => $code
        ], 201);
    }

    public function index(Request $request)
    {
        $codes = Code::latest()->paginate(10);

        return response()->json([
            'codes' => $codes
        ]);
    }

    public function show($id)
    {
        $code = Code::findOrFail($id);

        return response()->json([
            'code' => $code
        ]);
    }

    public function update(Request $request, $id)
    {
        $code = Code::findOrFail($id);

        $request->validate([
            'source_code' => 'sometimes|required|string',
            'tokens' => 'nullable|array',
            'errors' => 'nullable|array',
            'ast' => 'nullable|array',
            'assembly' => 'nullable|array',
            'machine_code' => 'nullable|array',
            'cpu_simulation' => 'nullable|array',
            'compilation_steps' => 'nullable|array'
        ]);

        $code->update($request->all());

        return response()->json([
            'message' => 'Code snippet updated successfully',
            'code' => $code
        ]);
    }

    public function destroy($id)
    {
        $code = Code::findOrFail($id);
        $code->delete();

        return response()->json([
            'message' => 'Code snippet deleted successfully'
        ]);
    }

    public function compileAndStore(Request $request)
    {
        // Forward to CompilerController's compile method
        $compilerController = new CompilerController();

        // Add save=true to the request to trigger database storage
        $request->merge(['save' => true]);

        return $compilerController->compile($request);
    }

    public function getCompilationHistory(Request $request)
    {
        $query = Code::query();

        if ($request->has('status')) {
            if ($request->status === 'success') {
                $query->whereJsonLength('errors', 0);
            } elseif ($request->status === 'error') {
                $query->whereJsonLength('errors', '>', 0);
            }
        }

        $codes = $query->with(['tokens', 'errors', 'ast', 'assembly', 'machine_code'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'history' => $codes
        ]);
    }
}
