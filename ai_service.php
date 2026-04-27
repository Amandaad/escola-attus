<?php

declare(strict_types=1);

function buildBoletoMetrics(array $boletos): array
{
    $today = new DateTimeImmutable('today');
    $dueSoonLimit = $today->modify('+7 days');

    $total = count($boletos);
    $paid = 0;
    $pending = 0;
    $overdue = 0;
    $dueSoon = 0;
    $pendingAmount = 0.0;
    $seriesRisk = [];

    foreach ($boletos as $boleto) {
        $status = (string) ($boleto['status'] ?? 'pendente');
        $amount = (float) ($boleto['amount'] ?? 0);
        $series = (string) ($boleto['series'] ?? 'Sem serie');
        $dueDateRaw = (string) ($boleto['due_date'] ?? '');

        if (!isset($seriesRisk[$series])) {
            $seriesRisk[$series] = [
                'series' => $series,
                'pending' => 0,
                'overdue' => 0,
                'pending_amount' => 0.0,
            ];
        }

        if ($status === 'pago') {
            $paid++;
            continue;
        }

        $pending++;
        $pendingAmount += $amount;
        $seriesRisk[$series]['pending']++;
        $seriesRisk[$series]['pending_amount'] += $amount;

        if ($dueDateRaw === '') {
            continue;
        }

        $dueDate = new DateTimeImmutable($dueDateRaw);
        if ($dueDate < $today) {
            $overdue++;
            $seriesRisk[$series]['overdue']++;
        } elseif ($dueDate <= $dueSoonLimit) {
            $dueSoon++;
        }
    }

    $seriesRiskList = array_values($seriesRisk);
    usort(
        $seriesRiskList,
        static function (array $a, array $b): int {
            if ($a['overdue'] !== $b['overdue']) {
                return $b['overdue'] <=> $a['overdue'];
            }

            return $b['pending_amount'] <=> $a['pending_amount'];
        }
    );

    return [
        'total' => $total,
        'paid' => $paid,
        'pending' => $pending,
        'overdue' => $overdue,
        'due_soon' => $dueSoon,
        'pending_amount' => $pendingAmount,
        'series_risk' => array_slice($seriesRiskList, 0, 3),
    ];
}

function buildFallbackRecommendations(array $metrics, bool $admin): array
{
    $recommendations = [];

    if ((int) $metrics['overdue'] > 0) {
        $recommendations[] = 'Priorize contato com responsaveis de boletos vencidos e ofereca renegociacao em ate 48h.';
    }

    if ((int) $metrics['due_soon'] > 0) {
        $recommendations[] = 'Envie lembretes preventivos para vencimentos dos proximos 7 dias.';
    }

    if ((float) $metrics['pending_amount'] > 0) {
        $recommendations[] = 'Valor pendente relevante: avalie campanha de pagamento antecipado com incentivo.';
    }

    if (!$recommendations) {
        $recommendations[] = 'Situacao saudavel: mantenha lembretes mensais para preservar baixa inadimplencia.';
    }

    if ($admin) {
        $recommendations[] = 'Acompanhe a serie com maior risco e monitore a evolucao semanal da inadimplencia.';
    } else {
        $recommendations[] = 'Mantenha os dados de contato atualizados para receber alertas de vencimento sem atraso.';
    }

    return $recommendations;
}

function getOpenAiApiKey(): string
{
    $apiKey = getenv('OPENAI_API_KEY');
    return is_string($apiKey) ? trim($apiKey) : '';
}

function requestOpenAiInsights(array $metrics, bool $admin): ?string
{
    $apiKey = getOpenAiApiKey();
    if ($apiKey === '' || !function_exists('curl_init')) {
        return null;
    }

    $model = getenv('OPENAI_MODEL');
    if (!is_string($model) || trim($model) === '') {
        $model = 'gpt-4o-mini';
    }

    $scope = $admin ? 'visao administrativa da escola' : 'visao de responsavel';
    $payload = [
        'model' => $model,
        'max_output_tokens' => 320,
        'input' => [
            [
                'role' => 'system',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => 'Voce e um analista financeiro escolar. Gere orientacoes objetivas em portugues do Brasil.',
                    ],
                ],
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => 'Considere este resumo em ' . $scope . ': ' . json_encode($metrics, JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ],
    ];

    $ch = curl_init('https://api.openai.com/v1/responses');
    if ($ch === false) {
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($response) || $response === '' || $httpCode >= 400) {
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return null;
    }

    $text = extractResponseText($decoded);
    return $text !== '' ? $text : null;
}

function extractResponseText(array $response): string
{
    if (isset($response['output_text']) && is_string($response['output_text'])) {
        return trim($response['output_text']);
    }

    if (!isset($response['output']) || !is_array($response['output'])) {
        return '';
    }

    $chunks = [];
    foreach ($response['output'] as $outputItem) {
        if (!is_array($outputItem) || !isset($outputItem['content']) || !is_array($outputItem['content'])) {
            continue;
        }

        foreach ($outputItem['content'] as $contentItem) {
            if (!is_array($contentItem)) {
                continue;
            }

            if (isset($contentItem['text']) && is_string($contentItem['text'])) {
                $chunks[] = trim($contentItem['text']);
            }
        }
    }

    return trim(implode("\n\n", array_filter($chunks)));
}

function toneLabel(string $tone): string
{
    $map = [
        'amigavel' => 'amigavel',
        'neutro' => 'neutro',
        'firme' => 'firme',
    ];

    return $map[$tone] ?? 'neutro';
}

function currencyBr(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function buildFallbackCollectionMessage(string $parentName, array $summary, string $tone): string
{
    $tone = toneLabel($tone);
    $greeting = 'Ola, ' . trim($parentName) . '.';
    $pendingCount = (int) ($summary['pending_count'] ?? 0);
    $pendingAmount = (float) ($summary['pending_amount'] ?? 0);
    $overdueCount = (int) ($summary['overdue_count'] ?? 0);
    $nearestDueDate = (string) ($summary['nearest_due_date'] ?? '');

    $toneSentence = 'Estamos entrando em contato para apoiar a regularizacao dos boletos pendentes.';
    if ($tone === 'amigavel') {
        $toneSentence = 'Tudo bem? Queremos te ajudar a manter os pagamentos em dia de forma tranquila.';
    } elseif ($tone === 'firme') {
        $toneSentence = 'Identificamos pendencias financeiras que precisam de regularizacao imediata.';
    }

    $dueSnippet = '';
    if ($nearestDueDate !== '') {
        $dueSnippet = ' Proximo vencimento: ' . date('d/m/Y', strtotime($nearestDueDate)) . '.';
    }

    $overdueSnippet = '';
    if ($overdueCount > 0) {
        $overdueSnippet = ' Existem ' . $overdueCount . ' boleto(s) vencido(s).';
    }

    return $greeting . "\n\n"
        . $toneSentence . "\n"
        . 'Resumo atual: ' . $pendingCount . ' boleto(s) pendente(s), total de ' . currencyBr($pendingAmount) . '.' . $dueSnippet . $overdueSnippet . "\n\n"
        . 'Se precisar, podemos negociar uma melhor forma de pagamento. Retorne esta mensagem para te ajudarmos com prioridade.';
}

function requestOpenAiCollectionMessage(string $parentName, array $summary, string $tone): ?string
{
    $apiKey = getOpenAiApiKey();
    if ($apiKey === '' || !function_exists('curl_init')) {
        return null;
    }

    $model = getenv('OPENAI_MODEL');
    if (!is_string($model) || trim($model) === '') {
        $model = 'gpt-4o-mini';
    }

    $tone = toneLabel($tone);
    $prompt = 'Crie uma mensagem curta de cobranca escolar em portugues do Brasil para responsavel. '
        . 'Use tom ' . $tone . ', sem ameacas, com linguagem profissional e humana. '
        . 'Dados: nome=' . $parentName . ', resumo=' . json_encode($summary, JSON_UNESCAPED_UNICODE)
        . '. Termine com chamada para acao amigavel.';

    $payload = [
        'model' => $model,
        'max_output_tokens' => 220,
        'input' => [
            [
                'role' => 'system',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => 'Voce redige comunicacoes financeiras escolares eticas e claras.',
                    ],
                ],
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => $prompt,
                    ],
                ],
            ],
        ],
    ];

    $ch = curl_init('https://api.openai.com/v1/responses');
    if ($ch === false) {
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($response) || $response === '' || $httpCode >= 400) {
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return null;
    }

    $text = extractResponseText($decoded);
    return $text !== '' ? $text : null;
}
