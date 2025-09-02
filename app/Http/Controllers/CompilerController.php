<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Code;

class CompilerController extends Controller
{
    private $compilationSteps = [];

    public function compile(Request $request)
    {
        $code = (string) $request->input('code', '');
        $this->compilationSteps = [];

        try {
            // Stage 1: DSL → Assembly
            $this->addCompilationStep('stage1_dsl_to_assembly', 'Starting DSL to Assembly compilation...');

            // 1. Lexical Analysis
            [$tokens, $lexErrors] = $this->lexicalAnalysis($code);
            $this->addCompilationStep('lexical_analysis', 'Tokens generated', ['tokens' => $tokens, 'errors' => $lexErrors]);

            if (!empty($lexErrors)) {
                return $this->errorResponse($tokens, $lexErrors, 'Lexical Analysis Failed');
            }

            // 2. Syntax Analysis (Parsing)
            [$ast, $parseErrors] = $this->parseDSL($tokens);
            $this->addCompilationStep('syntax_analysis', 'AST generated', ['ast' => $ast, 'errors' => $parseErrors]);

            if (!empty($parseErrors)) {
                return $this->errorResponse($tokens, $parseErrors, 'Syntax Analysis Failed');
            }

            // 3. Semantic Analysis
            $semanticErrors = $this->semanticAnalysis($ast);
            $this->addCompilationStep('semantic_analysis', 'Semantic analysis completed', ['errors' => $semanticErrors]);

            if (!empty($semanticErrors)) {
                return $this->errorResponse($tokens, $semanticErrors, 'Semantic Analysis Failed');
            }

            // 4. Code Generation (DSL → Assembly)
            $assembly = $this->generateAssembly($ast);
            $this->addCompilationStep('code_generation', 'Assembly code generated', ['assembly' => $assembly]);

            // Stage 2: Assembly → Machine Code
            $this->addCompilationStep('stage2_assembly_to_machine', 'Starting Assembly to Machine Code compilation...');

            $machineCode = $this->generateMachineCode($assembly);
            $this->addCompilationStep('machine_code_generation', 'Machine code generated', ['machine_code' => $machineCode]);

            // Stage 3: AI Enhancement (Basic)
            $aiSuggestions = $this->generateAISuggestions($code, $ast, $assembly);
            $this->addCompilationStep('ai_enhancement', 'AI suggestions generated', ['suggestions' => $aiSuggestions]);

            // Save compilation result
            if ($request->input('save', false)) {
                $this->saveCompilation($code, $tokens, $ast, $assembly, $machineCode);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'tokens' => $tokens,
                    'ast' => $ast,
                    'assembly' => $assembly,
                    'machine_code' => $machineCode,
                    'ai_suggestions' => $aiSuggestions,
                    'compilation_steps' => $this->compilationSteps
                ],
                'message' => 'Compilation completed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Compilation failed: ' . $e->getMessage(),
                'compilation_steps' => $this->compilationSteps
            ], 500);
        }
    }

    private function errorResponse($tokens, $errors, $stage)
    {
        return response()->json([
            'success' => false,
            'error' => $stage,
            'data' => [
                'tokens' => $tokens,
                'errors' => $errors,
                'compilation_steps' => $this->compilationSteps
            ]
        ], 400);
    }

    private function parseDSL($tokens)
    {
        $i = 0;
        $n = count($tokens);
        $errors = [];
        $ast = [];

        while ($i < $n) {
            $t = $tokens[$i];
            if (!$t) break;

            if ($t['type'] === 'PUNC' && $t['value'] === ';') {
                $i++;
                continue;
            }

            if ($t['type'] === 'KEYWORD' && $t['value'] === 'print') {
                $i++; // consume 'print'
                $expr = $this->parseExpression($tokens, $i);
                if ($expr && isset($expr['type']) && $expr['type'] === 'Error') {
                    $errors[] = $expr['message'];
                    break;
                }

                if ($i >= $n || $tokens[$i]['type'] !== 'PUNC' || $tokens[$i]['value'] !== ';') {
                    $errors[] = "Missing ';' after print statement at line {$t['line']}";
                    break;
                }
                $i++; // consume ';'
                $ast[] = ['type' => 'Print', 'expr' => $expr];
                continue;
            }

            if ($t['type'] === 'KEYWORD' && $t['value'] === 'let') {
                $i++; // consume 'let'

                if ($i >= $n || $tokens[$i]['type'] !== 'IDENTIFIER') {
                    $errors[] = "Expected variable name after 'let' at line {$t['line']}";
                    break;
                }
                $varToken = $tokens[$i];
                $i++; // consume variable name

                if ($i >= $n || $tokens[$i]['type'] !== 'OP' || $tokens[$i]['value'] !== '=') {
                    $errors[] = "Expected '=' after variable name at line {$t['line']}";
                    break;
                }
                $i++; // consume '='

                $expr = $this->parseExpression($tokens, $i);
                if ($expr && isset($expr['type']) && $expr['type'] === 'Error') {
                    $errors[] = $expr['message'];
                    break;
                }

                if ($i >= $n || $tokens[$i]['type'] !== 'PUNC' || $tokens[$i]['value'] !== ';') {
                    $errors[] = "Missing ';' after variable declaration at line {$t['line']}";
                    break;
                }
                $i++; // consume ';'

                $ast[] = [
                    'type' => 'VariableDeclaration',
                    'variable' => $varToken,
                    'expression' => $expr
                ];
                continue;
            }

            $errors[] = "Unexpected token '{$t['value']}' at line {$t['line']}, col {$t['col']}";
            break;
        }

        return [$ast, $errors];
    }

    private function generateAssembly($ast)
    {
        $assembly = [];
        $registerCounter = 1;
        $variables = []; // Track variable to register mapping

        foreach ($ast as $node) {
            if ($node['type'] === 'VariableDeclaration') {
                $varName = $node['variable']['value'];
                $reg = $registerCounter++;
                $variables[$varName] = $reg;

                $val = $this->exprToImm($node['expression']);
                $assembly[] = "MOV R{$reg}, {$val}";
            } elseif ($node['type'] === 'Print') {
                $expr = $node['expr'];
                if ($expr['type'] === 'BinaryExpression') {
                    // Handle binary expression with variables
                    $leftReg = $this->generateExpression($expr['left'], $assembly, $variables, $registerCounter);
                    $rightReg = $this->generateExpression($expr['right'], $assembly, $variables, $registerCounter);

                    // Perform the operation
                    $op = $this->getAssemblyOp($expr['operator']);
                    $assembly[] = "{$op} R{$leftReg}, R{$rightReg}";
                    $assembly[] = "OUT R{$leftReg}";
                } else {
                    // Handle simple expressions
                    $val = $this->exprToImm($expr);
                    $assembly[] = "MOV R1, {$val}";
                    $assembly[] = "OUT R1";
                }
            }
        }

        return $assembly;
    }

    private function generateExpression($expr, &$assembly, &$variables, &$registerCounter)
    {
        if ($expr['type'] === 'Identifier') {
            $varName = $expr['name'];
            if (isset($variables[$varName])) {
                return $variables[$varName];
            }
            // If variable not found, create a new register for it
            $reg = $registerCounter++;
            $variables[$varName] = $reg;
            return $reg;
        } elseif ($expr['type'] === 'NumberLiteral') {
            $reg = $registerCounter++;
            $val = (int)$expr['value']['value'];
            $assembly[] = "MOV R{$reg}, {$val}";
            return $reg;
        } elseif ($expr['type'] === 'BinaryExpression') {
            $leftReg = $this->generateExpression($expr['left'], $assembly, $variables, $registerCounter);
            $rightReg = $this->generateExpression($expr['right'], $assembly, $variables, $registerCounter);

            $op = $this->getAssemblyOp($expr['operator']);
            $assembly[] = "{$op} R{$leftReg}, R{$rightReg}";
            return $leftReg; // Result is in left register
        }

        return 1; // fallback
    }

    private function getAssemblyOp($operator)
    {
        switch ($operator) {
            case '+':
                return 'ADD';
            case '-':
                return 'SUB';
            case '*':
                return 'MUL';
            case '/':
                return 'DIV';
            default:
                return 'ADD';
        }
    }

    private function generateMachineCode($assembly)
    {
        $machineCode = [];

        foreach ($assembly as $instruction) {
            if (preg_match('/MOV R(\d+), (\d+)/', $instruction, $matches)) {
                $reg = (int)$matches[1];
                $imm = (int)$matches[2];
                $machineCode[] = $this->encodeInstruction('MOV', $reg, $imm);
            } elseif (preg_match('/(ADD|SUB|MUL|DIV) R(\d+), R(\d+)/', $instruction, $matches)) {
                $op = $matches[1];
                $reg1 = (int)$matches[2];
                $reg2 = (int)$matches[3];
                $machineCode[] = $this->encodeInstruction($op, $reg1, $reg2);
            } elseif (preg_match('/OUT R(\d+)/', $instruction, $matches)) {
                $reg = (int)$matches[1];
                $machineCode[] = $this->encodeInstruction('OUT', $reg, 0);
            }
        }

        return $machineCode;
    }

    private function encodeInstruction($opcode, $reg, $imm)
    {
        $opcodes = [
            'MOV' => '0001',
            'OUT' => '0010',
            'ADD' => '0011',
            'SUB' => '0100',
            'MUL' => '0101',
            'DIV' => '0110',
        ];

        $op = $opcodes[$opcode] ?? '1111';
        $r = str_pad(decbin($reg), 4, '0', STR_PAD_LEFT);
        $im = str_pad(decbin(max(0, min(15, $imm))), 4, '0', STR_PAD_LEFT);

        return "$op $r $im";
    }

    private function generateAISuggestions($code, $ast, $assembly)
    {
        $suggestions = [];

        // Basic AI suggestions
        if (empty($ast)) {
            $suggestions[] = "Try writing some code! For example: print \"Hello World!\";";
        }

        if (count($assembly) === 0) {
            $suggestions[] = "Your code doesn't generate any assembly instructions. Try adding print statements or variable declarations.";
        }

        // Check for common patterns
        $hasVariables = false;
        $hasPrints = false;

        foreach ($ast as $node) {
            if ($node['type'] === 'VariableDeclaration') {
                $hasVariables = true;
            }
            if ($node['type'] === 'Print') {
                $hasPrints = true;
            }
        }

        if ($hasVariables && !$hasPrints) {
            $suggestions[] = "You declared variables but didn't print them. Try adding: print variableName;";
        }

        return $suggestions;
    }

    private function saveCompilation($code, $tokens, $ast, $assembly, $machineCode)
    {
        Code::create([
            'source_code' => $code,
            'tokens' => json_encode($tokens),
            'ast' => json_encode($ast),
            'assembly' => json_encode($assembly),
            'machine_code' => json_encode($machineCode),
            'compilation_steps' => json_encode($this->compilationSteps)
        ]);
    }

    private function semanticAnalysis($ast)
    {
        $errors = [];
        $variables = []; // Track declared variables

        // Basic semantic analysis - can be enhanced
        foreach ($ast as $node) {
            if ($node['type'] === 'Print') {
                // Check if expressions are valid
                $this->checkExpression($node['expr'], $variables, $errors);
            } elseif ($node['type'] === 'VariableDeclaration') {
                // Check variable declaration
                $varName = $node['variable']['value'];

                // Check if variable is already declared
                if (isset($variables[$varName])) {
                    $errors[] = "Variable '$varName' is already declared";
                } else {
                    $variables[$varName] = $node['expression'];
                }

                // Check if expression is valid
                $this->checkExpression($node['expression'], $variables, $errors);
            }
        }
        return $errors;
    }

    private function checkExpression($expr, &$variables, &$errors)
    {
        if (!$expr) return;

        if ($expr['type'] === 'NumberLiteral' || $expr['type'] === 'StringLiteral') {
            // Literals are always valid
            return;
        }

        if ($expr['type'] === 'Identifier') {
            $varName = $expr['name'];
            if (!isset($variables[$varName])) {
                $errors[] = "Variable '$varName' is not declared";
            }
            return;
        }

        if ($expr['type'] === 'BinaryExpression') {
            $this->checkExpression($expr['left'], $variables, $errors);
            $this->checkExpression($expr['right'], $variables, $errors);
            return;
        }

        if ($expr['type'] === 'Error') {
            $errors[] = $expr['message'];
            return;
        }
    }

    private function exprToImm($expr)
    {
        if (!$expr) return 0;

        if ($expr['type'] === 'NumberLiteral') {
            return (int)$expr['value']['value'];
        }

        if ($expr['type'] === 'StringLiteral') {
            return strlen($expr['value']['value']);
        }

        if ($expr['type'] === 'Identifier') {
            // For now, return a placeholder value for variables
            // In a real compiler, this would look up the variable's value
            return 42; // placeholder
        }

        if ($expr['type'] === 'BinaryExpression') {
            $left = $this->exprToImm($expr['left']);
            $right = $this->exprToImm($expr['right']);

            switch ($expr['operator']) {
                case '+':
                    return $left + $right;
                case '-':
                    return $left - $right;
                case '*':
                    return $left * $right;
                case '/':
                    return $right != 0 ? $left / $right : 0;
                default:
                    return 0;
            }
        }

        return 0;
    }

    private function addCompilationStep($stage, $message, $data = null)
    {
        $this->compilationSteps[] = [
            'stage' => $stage,
            'timestamp' => now()->toISOString(),
            'message' => $message,
            'data' => $data
        ];
    }

    private function lexicalAnalysis(string $code): array
    {
        $i = 0;
        $line = 1;
        $col = 1;
        $len = strlen($code);
        $tokens = [];
        $errors = [];

        $operators = ['=', '+', '-', '*', '/'];
        $punct = [';', '(', ')', ',', '{', '}'];

        while ($i < $len) {
            $ch = $code[$i];

            if (ctype_space($ch)) {
                if ($ch === "\n") {
                    $line++;
                    $col = 1;
                } else {
                    $col++;
                }
                $i++;
                continue;
            }

            if ($ch === '"') {
                $startLine = $line;
                $startCol = $col;
                $i++;
                $col++;
                $val = '';
                $closed = false;
                while ($i < $len) {
                    $c = $code[$i];
                    if ($c === '"') {
                        $closed = true;
                        $i++;
                        $col++;
                        break;
                    }
                    if ($c === "\n") {
                        $line++;
                        $col = 1;
                        $i++;
                        $val .= "\n";
                        continue;
                    }
                    $val .= $c;
                    $i++;
                    $col++;
                }
                if (!$closed) {
                    $errors[] = "Unterminated string starting at line $startLine, col $startCol";
                } else {
                    $tokens[] = ['type' => 'STRING', 'value' => $val, 'line' => $startLine, 'col' => $startCol];
                }
                continue;
            }

            if (in_array($ch, $operators, true)) {
                $tokens[] = ['type' => 'OP', 'value' => $ch, 'line' => $line, 'col' => $col];
                $i++;
                $col++;
                continue;
            }

            if (in_array($ch, $punct, true)) {
                $tokens[] = ['type' => 'PUNC', 'value' => $ch, 'line' => $line, 'col' => $col];
                $i++;
                $col++;
                continue;
            }

            if (ctype_digit($ch)) {
                $startLine = $line;
                $startCol = $col;
                $start = $i;
                while ($i < $len && ctype_digit($code[$i])) {
                    $i++;
                    $col++;
                }
                $tokens[] = ['type' => 'NUMBER', 'value' => substr($code, $start, $i - $start), 'line' => $startLine, 'col' => $startCol];
                continue;
            }

            if (ctype_alpha($ch) || $ch === '_') {
                $startLine = $line;
                $startCol = $col;
                $start = $i;
                while ($i < $len && (ctype_alnum($code[$i]) || $code[$i] === '_')) {
                    $i++;
                    $col++;
                }
                $word = substr($code, $start, $i - $start);
                $kw = ['print', 'let'];
                $type = in_array($word, $kw, true) ? 'KEYWORD' : 'IDENTIFIER';
                $tokens[] = ['type' => $type, 'value' => $word, 'line' => $startLine, 'col' => $startCol];
                continue;
            }

            $errors[] = "Unexpected character '{$ch}' at line $line, col $col";
            $i++;
            $col++;
        }

        return [$tokens, $errors];
    }

    private function parseExpression($tokens, &$i)
    {
        $left = $this->parsePrimary($tokens, $i);

        while (
            $i < count($tokens) && $tokens[$i]['type'] === 'OP' &&
            in_array($tokens[$i]['value'], ['+', '-', '*', '/'])
        ) {
            $op = $tokens[$i]['value'];
            $i++;
            $right = $this->parsePrimary($tokens, $i);
            $left = [
                'type' => 'BinaryExpression',
                'operator' => $op,
                'left' => $left,
                'right' => $right
            ];
        }

        return $left;
    }

    private function parsePrimary($tokens, &$i)
    {
        if ($i >= count($tokens)) {
            return null;
        }

        $token = $tokens[$i];

        if ($token['type'] === 'NUMBER') {
            $i++;
            return ['type' => 'NumberLiteral', 'value' => $token];
        }

        if ($token['type'] === 'STRING') {
            $i++;
            return ['type' => 'StringLiteral', 'value' => $token];
        }

        if ($token['type'] === 'IDENTIFIER') {
            $i++;
            return ['type' => 'Identifier', 'name' => $token['value'], 'token' => $token];
        }

        if ($token['type'] === 'PUNC' && $token['value'] === '(') {
            $i++; // consume '('
            $expr = $this->parseExpression($tokens, $i);
            if ($i < count($tokens) && $tokens[$i]['type'] === 'PUNC' && $tokens[$i]['value'] === ')') {
                $i++; // consume ')'
                return $expr;
            } else {
                return ['type' => 'Error', 'message' => 'Missing closing parenthesis'];
            }
        }

        return ['type' => 'Error', 'message' => 'Unexpected token: ' . $token['value']];
    }
}
