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

            // Store input in temporary file
            $inputFile = storage_path('app/input.txt');
            $input = $request->input('input', '');
            file_put_contents($inputFile, $input);

            // Generate assembly code with Intel syntax for better readability
            $asmFile = storage_path('app/code.s');
            $asmCompileOutput = shell_exec("g++ \"$file\" -S -masm=intel -O0 -o \"$asmFile\" 2>&1");

            // Compile the code
            $exeFile = storage_path('app/code.exe');
            $compileOutput = shell_exec("g++ \"$file\" -o \"$exeFile\" 2>&1");
            if ($compileOutput) {
                // Compilation failed
                $output = "Compilation Error:\n" . $compileOutput;
                $visualization = null;
            } else {
                // Run the executable with input redirected
                $output = shell_exec("\"$exeFile\" < \"$inputFile\" 2>&1");
                $visualization = null;
            }
        } else {
            // Store code in temporary Python file
            $file = storage_path('app/code.py');
            file_put_contents($file, $code);

            // Store input in temporary file
            $inputFile = storage_path('app/input.txt');
            $input = $request->input('input', '');
            file_put_contents($inputFile, $input);

            // Execute code using python with input redirected
            $output = shell_exec("python \"$file\" < \"$inputFile\" 2>&1");
            $visualization = null;
        }

        return response()->json([
            'output' => $output,
            'visualization' => $visualization
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
