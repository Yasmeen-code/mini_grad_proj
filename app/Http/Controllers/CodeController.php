<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Code;

class CodeController extends Controller
{
    public function analyze(Request $request)
    {
        $code = $request->input('code');

        // Store code in temporary file
        $file = storage_path('app/code.py');
        file_put_contents($file, $code);

        // Execute code using python
        $output = shell_exec("python \"$file\" 2>&1");

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
