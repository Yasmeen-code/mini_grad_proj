<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CompilerController extends Controller
{
    public function compile(Request $request)
    {
        $code = (string) $request->input('code', '');
        [$tokens, $lexErrors] = $this->lexicalAnalysis($code);

        if (!empty($lexErrors)) {
            return response()->json([
                'tokens' => $tokens,
                'errors' => $lexErrors,
                'status' => 'Failed'
            ]);
        }

        [$ast, $parseErrors] = $this->parseMini($tokens);

        $parseErrors = array_map(function ($e) {
            if (str_contains($e, "Unexpected token 'prnt'")) {
                return $e . " — هل تقصد 'print' ؟";
            }
            if (str_contains($e, "Unexpected token 'cot'")) {
                return $e . " — هل تقصد 'cout' ؟";
            }
            return $e;
        }, $parseErrors);

        if (!empty($parseErrors)) {
            return response()->json([
                'tokens' => $tokens,
                'errors' => $parseErrors,
                'status' => 'Failed'
            ]);
        }

        [$assembly, $machine] = $this->codeGen($ast);

        return response()->json([
            'tokens'   => $tokens,
            'ast'      => $ast,
            'assembly' => $assembly,
            'machine'  => $machine,
            'errors'   => [],
            'status'   => 'Success'
        ]);
    }
    private function lexicalAnalysis(string $code): array
    {
        $i = 0;
        $line = 1;
        $col = 1;
        $len = strlen($code);
        $tokens = [];
        $errors = [];

        $operators = [
            '<<',
            '>>',
            '==',
            '!=',
            '<=',
            '>=',
            '&&',
            '||',
            '+=',
            '-=',
            '*=',
            '/=',
            '=',
            '+',
            '-',
            '*',
            '/',
            '<',
            '>'
        ];
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

            if ($ch === '/' && $i + 1 < $len && $code[$i + 1] === '/') {

                while ($i < $len && $code[$i] !== "\n") {
                    $i++;
                    $col++;
                }
                continue;
            }
            if ($ch === '/' && $i + 1 < $len && $code[$i + 1] === '*') {
                $i += 2;
                $col += 2;
                $closed = false;
                while ($i < $len) {
                    if ($code[$i] === '*' && $i + 1 < $len && $code[$i + 1] === '/') {
                        $i += 2;
                        $col += 2;
                        $closed = true;
                        break;
                    }
                    if ($code[$i] === "\n") {
                        $line++;
                        $col = 1;
                        $i++;
                        continue;
                    }
                    $i++;
                    $col++;
                }
                if (!$closed) {
                    $errors[] = "Unclosed block comment at line $line, col $col";
                }
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
                    if ($c === '\\') {
                        if ($i + 1 < $len) {
                            $next = $code[$i + 1];
                            $map = ["n" => "\n", "t" => "\t", "r" => "\r", "\\" => "\\", "\"" => "\""];
                            $val .= $map[$next] ?? $next;
                            $i += 2;
                            $col += 2;
                            continue;
                        } else {
                            $i++;
                            $col++;
                            break;
                        }
                    }
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

            $matchedOp = null;
            foreach ($operators as $op) {
                $L = strlen($op);
                if ($i + $L <= $len && substr($code, $i, $L) === $op) {
                    $matchedOp = $op;
                    break;
                }
            }
            if ($matchedOp !== null) {
                $tokens[] = ['type' => 'OP', 'value' => $matchedOp, 'line' => $line, 'col' => $col];
                $i += strlen($matchedOp);
                $col += strlen($matchedOp);
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
                if ($i < $len && $code[$i] === '.' && $i + 1 < $len && ctype_digit($code[$i + 1])) {
                    $i++;
                    $col++;
                    while ($i < $len && ctype_digit($code[$i])) {
                        $i++;
                        $col++;
                    }
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
                $kw = ['print', 'if', 'end', 'else', 'let', 'var', 'cout'];
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

    private function parseMini(array $tokens): array
    {
        $i = 0;
        $n = count($tokens);
        $errors = [];
        $ast = [];

        $next = function () use (&$i, $n, $tokens) {
            return $i < $n ? $tokens[$i] : null;
        };
        $eat  = function ($type = null, $value = null) use (&$i, $n, $tokens) {
            if ($i >= $n) return null;
            $t = $tokens[$i];
            if (($type === null || $t['type'] === $type) && ($value === null || $t['value'] === $value)) {
                $i++;
                return $t;
            }
            return null;
        };

        $parseExpr = function () use (&$eat, &$next) {
            $t = $next();
            if (!$t) return [null, "Unexpected end of input while parsing expression"];
            if (in_array($t['type'], ['STRING', 'NUMBER', 'IDENTIFIER'], true)) {
                $eat();
                return [['type' => 'Expr', 'value' => $t], null];
            }
            return [null, "Expected expression, got '{$t['value']}' at line {$t['line']}"];
        };

        while ($i < $n) {
            $t = $next();
            if (!$t) break;

            if ($t['type'] === 'PUNC' && $t['value'] === ';') {
                $eat('PUNC', ';');
                continue;
            }

            if ($t['type'] === 'KEYWORD' && $t['value'] === 'print') {
                $eat('KEYWORD', 'print');
                [$expr, $err] = $parseExpr();
                if ($err) {
                    $errors[] = $err;
                    break;
                }

                if (!$eat('PUNC', ';')) {
                    $errors[] = "Missing ';' after print statement at line {$t['line']}";
                    break;
                }
                $ast[] = ['type' => 'Print', 'expr' => $expr];
                continue;
            }

            if ($t['type'] === 'KEYWORD' && $t['value'] === 'cout') {
                $start = $eat('KEYWORD', 'cout');
                $parts = [];
                if (!$eat('OP', '<<')) {
                    $errors[] = "Expected '<<' after cout at line {$start['line']}";
                    break;
                }
                [$expr, $err] = $parseExpr();
                if ($err) {
                    $errors[] = $err;
                    break;
                }
                $parts[] = $expr;

                while (true) {
                    $t2 = $next();
                    if ($t2 && $t2['type'] === 'OP' && $t2['value'] === '<<') {
                        $eat('OP', '<<');
                        [$expr, $err] = $parseExpr();
                        if ($err) {
                            $errors[] = $err;
                            break 2;
                        }
                        $parts[] = $expr;
                        continue;
                    }
                    break;
                }
                if (!$eat('PUNC', ';')) {
                    $errors[] = "Missing ';' after cout chain at line {$start['line']}";
                    break;
                }
                $ast[] = ['type' => 'Cout', 'parts' => $parts];
                continue;
            }

            $errors[] = "Unexpected token '{$t['value']}' at line {$t['line']}, col {$t['col']}";
            break;
        }

        return [$ast, $errors];
    }

    private function codeGen(array $ast): array
    {
        $assembly = [];
        $machine  = [];

        $emitMov = function ($reg, $imm) use (&$assembly, &$machine) {
            $assembly[] = "MOV R{$reg}, {$imm}";
            $machine[]  = $this->op('MOV', $reg, $imm);
        };
        $emitOut = function ($reg) use (&$assembly, &$machine) {
            $assembly[] = "OUT R{$reg}";
            $machine[]  = $this->op('OUT', $reg, 0);
        };

        foreach ($ast as $node) {
            if ($node['type'] === 'Print') {
                $val = $this->exprToImm($node['expr']);
                $emitMov(1, $val);
                $emitOut(1);
            } elseif ($node['type'] === 'Cout') {
                foreach ($node['parts'] as $part) {
                    $val = $this->exprToImm($part);
                    $emitMov(1, $val);
                    $emitOut(1);
                }
            }
        }
        return [$assembly, $machine];
    }

    private function exprToImm(array $expr)
    {
        $t = $expr['value'];
        if ($t['type'] === 'NUMBER') return (int)$t['value'];
        if ($t['type'] === 'STRING') return strlen($t['value']);
        return 0;
    }

    private function op(string $mn, int $reg, int $imm): string
    {
        $opc = [
            'MOV' => '0001',
            'OUT' => '0010',
        ][$mn] ?? '1111';

        $r = str_pad(decbin($reg), 4, '0', STR_PAD_LEFT);
        $im = str_pad(decbin(max(0, min(15, $imm))), 4, '0', STR_PAD_LEFT);
        return "$opc $r $im";
    }
}
