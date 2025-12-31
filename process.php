<?php
/**
 * process.php - Processador de Receitas PDF
 * 
 * Versão melhorada com:
 * - Regex robusta para e-SUS (quebras de linha, múltiplas páginas)
 * - Suporte a receituário especial e controlado
 * - Integração com banco de dados
 * - Integração com Gemini AI para análise de interações
 */

session_start();
require 'vendor/autoload.php';
require_once 'config/database.php';

use Smalot\PdfParser\Parser;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// ============================================================================
// FUNÇÕES DE EXTRAÇÃO E REGEX MELHORADAS
// ============================================================================

/**
 * Normaliza o texto do PDF para processamento
 * @param string $texto Texto bruto do PDF
 * @return string Texto normalizado
 */
function normalizarTexto($texto)
{
    // Normalize line endings
    $texto = preg_replace('/\r\n|\r/', "\n", $texto);

    // Remove múltiplos espaços
    $texto = preg_replace('/[^\S\n]+/', ' ', $texto);

    // Remove cabeçalhos de página (receituários de 2+ páginas)
    $texto = preg_replace('/Página\s*\d+\s*de\s*\d+/i', '', $texto);

    // Remove quebras de linha dentro de palavras (ocasionadas por formatação PDF)
    $texto = preg_replace('/(\w)-\n(\w)/', '$1$2', $texto);

    return $texto;
}

/**
 * Identifica o tipo de receituário
 * @param string $texto Texto do PDF
 * @return string Tipo: 'especial', 'controlado', ou 'comum'
 */
function identificarTipoReceita($texto)
{
    if (preg_match('/RECEITU[ÁA]RIO\s+ESPECIAL/i', $texto)) {
        return 'especial';
    }
    if (preg_match('/RECEITU[ÁA]RIO\s+(CONTROLADO|AZUL|AMARELO)/i', $texto)) {
        return 'controlado';
    }
    if (preg_match('/ANTIMICROBIANOS?/i', $texto)) {
        return 'antimicrobiano';
    }
    return 'comum';
}

/**
 * Extrai medicamentos do texto do PDF com regex melhorada
 * Suporta receitas com múltiplas páginas e duas vias (paciente/farmácia)
 * @param string $texto Texto normalizado
 * @return array Lista de medicamentos extraídos (sem duplicatas)
 */
function extrairMedicamentos($texto)
{
    $medicamentos = [];
    $medicamentosUnicos = [];

    // Normaliza texto
    $texto = normalizarTexto($texto);

    // Divide em linhas para processamento seguro e evitar problemas de regex com multiline
    $linhasRaw = explode("\n", $texto);
    $linhasLimpas = [];

    // 1. Limpeza inicial linha a linha (muito mais seguro que regex global)
    foreach ($linhasRaw as $linha) {
        // Remove lixos intra-linha: EMITENTE e tudo que vier depois
        $linha = preg_replace('/EMITENTE.*$/iu', '', $linha);
        // Remove paginação tipo ".1 / 2", "1/2", "2 / 2" (causa duplicação na última medicação)
        $linha = preg_replace('/\.?\s*\d+\s*\/\s*\d+\s*$/u', '', $linha);
        $linha = trim($linha);
        if (empty($linha))
            continue;

        // Ignora linhas que são claramente cabeçalho/rodapé repetido ou identificadores de Via
        if (preg_match('/(1|2)[ªa]\s*Via/i', $linha))
            continue;
        if (preg_match('/CreatePDF/i', $linha))
            continue;
        if (preg_match('/Assinado\s+digitalmente/i', $linha))
            continue;

        // Filtros robustos para nome do médico e assinatura
        if (preg_match('/CRM/i', $linha)) // CRM em qualquer formato
            continue;
        if (preg_match('/^Dra?\.?\s+/i', $linha)) // Prefixo Dr. ou Dra.
            continue;
        if (preg_match('/\(CRM.*\)/i', $linha)) // (CRM - GO 12345)
            continue;
        // Linhas que parecem apenas nomes próprios (3+ palavras capitalizadas, sem números)
        if (preg_match('/^[A-ZÀ-Ú][a-zà-ú]+(\s+[A-ZÀ-Ú][a-zà-ú]+){2,}$/u', $linha) && !preg_match('/\d/', $linha))
            continue;

        if (preg_match('/Página\s+\d+/i', $linha))
            continue;
        if (preg_match('/PRESCRIÇÃO\s+ELETRÔNICA/i', $linha))
            continue;
        if (preg_match('/RECEITUÁRIO.*(SIMPLES|ESPECIAL|CONTROLADO|VIA)/i', $linha))
            continue;
        if (preg_match('/ORIENTAÇ[ÃÂ]O\s+AO\s+PACIENTE/i', $linha))
            continue;
        if (preg_match('/Médico\s+da\s+estratégia\s+de\s+saúde/i', $linha))
            continue;

        // LIMPA (não descarta) lixo de impressão/rodapé e cabeçalho cidadão
        // Remove "Impresso em..." e tudo depois
        $linha = preg_replace('/Impresso\s+em.*$/iu', '', $linha);
        // Remove "CIDADÃO" e tudo depois (nome do paciente)
        $linha = preg_replace('/CIDADÃO.*$/iu', '', $linha);
        $linha = trim($linha);
        if (empty($linha))
            continue;

        if (preg_match('/Data\s+de\s+emiss/i', $linha))
            continue;

        // Remove linhas de Cidade/Data tipo "Catalão - GO, 22 de setembro de 2025"
        if (preg_match('/^[A-Z][a-zçã]+\s*-\s*[A-Z]{2},\s*\d{1,2}\s+de\s+[a-z]+\s+de\s+\d{4}/u', $linha))
            continue;

        // Remove endereços comuns para não confundir
        if (preg_match('/(Rua|Av\.|Avenida|Alameda|Travessa|Praça)\s+/i', $linha))
            continue;
        if (preg_match('/CEP:?\s*\d{5}/', $linha))
            continue;

        $linhasLimpas[] = $linha;
    }

    // 2. Agrupamento por blocos numerados
    $blocos = [];
    $blocoAtual = '';

    foreach ($linhasLimpas as $linha) {
        // Se encontrar novo item numerado (Ex: "1. Amoxicilina...")
        if (preg_match('/^\d+[\.\)\-]\s+/', $linha)) {
            if (!empty($blocoAtual)) {
                $blocos[] = $blocoAtual;
            }
            $blocoAtual = $linha;
        } else {
            // Se já temos um bloco aberto, é continuação
            if (!empty($blocoAtual)) {
                // Evita concatenar coisas que pareçam cabeçalhos de seção se eles sobreviveram
                if (!preg_match('/(MEDICAMENTOS?|RECOMENDAÇÕES?|OBSERVAÇÕES?)/i', $linha)) {
                    $blocoAtual .= "\n" . $linha;
                } else {
                    // Se for OBS, pode ser parte do medicamento anterior
                    if (preg_match('/(RECOMENDAÇÕES?|OBSERVAÇÕES?)/i', $linha)) {
                        $blocoAtual .= "\n" . $linha;
                    }
                }
            }
        }
    }
    if (!empty($blocoAtual)) {
        $blocos[] = $blocoAtual;
    }

    // 3. Processamento de cada bloco
    foreach ($blocos as $bloco) {
        $linhas = explode("\n", $bloco);
        $primeiraLinha = trim(array_shift($linhas));

        // Extrai nome
        $nome = trim(preg_replace('/^\d+[\.\)\-]\s*/', '', $primeiraLinha));
        $nome = preg_replace('/\s*\|\s*(Oral|Uso.*)$/i', '', $nome);
        $nome = preg_replace('/\s+\d+\s+(comprimidos|capsulas)[\s\w]*$/i', '', $nome);
        $nome = preg_replace('/\s+Comprimido$/i', '', $nome);

        if (empty($nome) || strlen($nome) < 3)
            continue;



        // Filtros extras de segurança
        if (preg_match('/(Paciente|Cidade|Estado)/i', $nome))
            continue;

        // Processa restante das linhas
        $orientacoes = '';
        $recomendacoes = '';
        $duracao = '';
        $quantidade = ''; // Mantendo compatibilidade com array return structure

        $textoRestante = implode(" ", $linhas);

        // Extrai recomendações
        if (preg_match('/(Recomenda[çc][õo]es?|Obs\.?|Observa[çc][õo]es?|Aten[çc][ãa]o|Importante):?\s*(.+)/i', $textoRestante, $recMatch)) {
            $recomendacoes = trim($recMatch[2]);
            // Remove do texto para não virar orientação
            $textoRestante = str_replace($recMatch[0], '', $textoRestante);
            $textoRestante = trim($textoRestante);
        }

        $orientacoes = $textoRestante;

        // Limpeza AGRESSIVA nas orientações e recomendações
        $cleaner = function ($str) {
            // Remove "X comprimidos Comprimido" de QUALQUER posição (início, meio ou fim)
            $str = preg_replace('/\d+\s+(comprimidos?|c[aá]psulas?)\s+Comprimido\s*/iu', '', $str);
            $str = preg_replace('/\s+Comprimido$/iu', '', $str);

            // Remove padrão "NOME COMPLETO - NUMERO_LONGO" (ex: NATHALIA BARBOSA RODRIGUES COSTA - 706001819574745)
            $str = preg_replace('/[A-ZÀ-Ú][A-ZÀ-Ú\s]+\s+-\s*\d{10,}/u', '', $str);

            // Remove nomes em MAIÚSCULAS que parecem assinaturas (3+ palavras maiúsculas seguidas)
            $str = preg_replace('/\b[A-ZÀ-Ú]{2,}\s+[A-ZÀ-Ú]{2,}\s+[A-ZÀ-Ú]{2,}(\s+[A-ZÀ-Ú]{2,})*\b/u', '', $str);

            // Remove EMITENTE e variantes
            $str = preg_replace('/EMITENTE.*$/iu', '', $str);
            $str = preg_replace('/CIDADÃO.*$/iu', '', $str);

            // Remove espaços múltiplos e trim
            $str = preg_replace('/\s+/', ' ', $str);
            return trim($str);
        };
        $orientacoes = $cleaner($orientacoes);
        $recomendacoes = $cleaner($recomendacoes);

        // Deduplicação DENTRO DA PÁGINA (cada página tem 2 vias idênticas)
        // Mas NÃO afeta outras páginas - cada página é uma receita separada
        $nomeNormalizado = strtolower(preg_replace('/\s+/', ' ', $nome));
        $orientNormalizada = strtolower(preg_replace('/\s+/', ' ', $orientacoes));
        $hashUnico = md5($nomeNormalizado . $orientNormalizada);

        if (isset($medicamentosUnicos[$hashUnico]))
            continue;

        $medicamentosUnicos[$hashUnico] = true;
        $medicamentos[] = [
            'nome' => $nome,
            'orientacoes' => $orientacoes ?: 'Verificar posologia na receita original',
            'recomendacoes' => $recomendacoes,
            'duracao' => $duracao,
            'quantidade' => $quantidade
        ];
    }

    return $medicamentos;
}

/**
 * Obtém ícone e informações de orientação
 */
function getIconeParaOrientacao($orientacao)
{
    $orientacao = strtolower($orientacao);

    if (strpos($orientacao, 'pela noite') !== false || strpos($orientacao, 'ao deitar') !== false) {
        return ['tipo' => 'turno', 'texto' => 'Noite', 'icone_classe' => 'fas fa-moon text-indigo-500'];
    }
    if (strpos($orientacao, 'pela manhã') !== false || strpos($orientacao, 'em jejum') !== false) {
        return ['tipo' => 'turno', 'texto' => 'Manhã', 'icone_classe' => 'fas fa-sun text-yellow-500'];
    }
    if (preg_match('/a cada (\d+)\s?horas?/i', $orientacao, $matches)) {
        return ['tipo' => 'frequencia', 'texto' => 'A cada ' . $matches[1] . 'h', 'icone_classe' => 'fas fa-clock text-blue-500'];
    }
    if (preg_match('/(\d+)\s*vezes?\s*(ao|por)\s*dia/i', $orientacao, $matches)) {
        return ['tipo' => 'frequencia', 'texto' => $matches[1] . 'x ao dia', 'icone_classe' => 'fas fa-calendar-day text-green-500'];
    }

    return ['tipo' => 'dose', 'texto' => 'Ver receita', 'icone_classe' => 'fas fa-pills text-purple-500'];
}

/**
 * Busca informações do medicamento no banco de dados
 */
function getInfoMedicamento($nome, $fallbackDB = [])
{
    try {
        $stmt = executeQuery(
            "SELECT emoji, finalidade FROM icones_funcoes WHERE ? LIKE CONCAT('%', medicamento, '%') AND ativo = 1 LIMIT 1",
            [$nome]
        );
        $result = $stmt->fetch();
        if ($result) {
            return ['emoji' => $result['emoji'] ?: '💊', 'finalidade' => $result['finalidade']];
        }
    } catch (Exception $e) {
        // Fallback para JSON local
    }

    // Fallback para rename_map.json
    foreach ($fallbackDB as $item) {
        if (stripos($nome, $item['medicamento']) !== false) {
            return ['emoji' => $item['emoji'], 'finalidade' => $item['finalidade']];
        }
    }

    return ['emoji' => '💊', 'finalidade' => 'Tratamento'];
}

/**
 * Busca vídeo do medicamento no banco de dados
 */
function buscarVideo($medicamento, $fallbackDB = [])
{
    try {
        $stmt = executeQuery(
            "SELECT youtube_url FROM videos_medicamentos WHERE ? LIKE CONCAT('%', medicamento, '%') AND ativo = 1 LIMIT 1",
            [$medicamento]
        );
        $result = $stmt->fetch();
        if ($result) {
            return $result['youtube_url'];
        }
    } catch (Exception $e) {
        // Fallback para JSON local
    }

    // Fallback para videos.json
    foreach ($fallbackDB as $item) {
        if (stripos($medicamento, $item['medicamento']) !== false) {
            return $item['url'];
        }
    }

    return null;
}

/**
 * Chama a API Gemini para análise de interações medicamentosas
 */
function analisarInteracoesGemini($medicamentos)
{
    $apiKey = getenv('GEMINI_API_KEY');
    if (empty($apiKey)) {
        return null; // API key não configurada
    }

    // Monta lista de medicamentos
    $listaMeds = array_map(function ($med) {
        return $med['nome'];
    }, $medicamentos);

    // Verifica cache
    $cacheKey = md5(implode(',', $listaMeds));
    try {
        $stmt = executeQuery(
            "SELECT resposta_json FROM cache_gemini WHERE medicamentos_hash = ? AND expires_at > NOW()",
            [$cacheKey]
        );
        $cached = $stmt->fetch();
        if ($cached) {
            return json_decode($cached['resposta_json'], true);
        }
    } catch (Exception $e) {
    }

    // Prepara prompt
    $prompt = "Analise os seguintes medicamentos para possíveis interações medicamentosas. " .
        "Liste apenas interações clinicamente significativas de forma breve e objetiva. " .
        "Medicamentos: " . implode(', ', $listaMeds) . "\n\n" .
        "Responda em JSON no formato: {\"interacoes\": [{\"medicamentos\": [\"med1\", \"med2\"], \"risco\": \"alto/moderado/baixo\", \"descricao\": \"breve descrição\"}], \"alertas_gerais\": [\"alerta1\"]}";

    // Chamada à API Gemini
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . $apiKey;

    $data = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'temperature' => 0.2,
            'maxOutputTokens' => 10000
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return null;
    }

    $responseData = json_decode($response, true);
    $text = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

    // Extrai JSON da resposta
    if (preg_match('/\{.*\}/s', $text, $matches)) {
        $resultado = json_decode($matches[0], true);

        // Salva no cache (expira em 24h)
        try {
            executeQuery(
                "INSERT INTO cache_gemini (medicamentos_hash, resposta_json, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
                 ON DUPLICATE KEY UPDATE resposta_json = VALUES(resposta_json), expires_at = VALUES(expires_at)",
                [$cacheKey, $matches[0]]
            );
        } catch (Exception $e) {
        }

        return $resultado;
    }

    return null;
}

// ============================================================================
// PROCESSAMENTO PRINCIPAL
// ============================================================================

$medicamentosProcessados = [];
$tipoReceita = 'comum';
$analiseIA = null;
$textoCompletoAudio = "Atenção para as instruções da sua receita: ";

// Carrega fallbacks JSON
$renameDB = json_decode(file_get_contents('rename_map.json'), true) ?: [];
$videosDB = json_decode(file_get_contents('videos.json'), true) ?: [];

if (isset($_FILES['receitas']) && !empty($_FILES['receitas']['tmp_name'][0])) {
    $parser = new Parser();
    $writer = new PngWriter();
    $startTime = microtime(true);
    $totalPaginas = 0;

    unset($_SESSION['pdf_original_path']);

    foreach ($_FILES['receitas']['tmp_name'] as $key => $tmp_name) {
        try {
            // Salva o PDF temporariamente
            $original_pdf_path = sys_get_temp_dir() . '/' . session_id() . '_' . basename($_FILES['receitas']['name'][$key]);
            move_uploaded_file($tmp_name, $original_pdf_path);
            $_SESSION['pdf_original_path'] = $original_pdf_path;

            // Processamento PÁGINA A PÁGINA para suportar receitas longas
            $pdf = $parser->parseFile($original_pdf_path);
            $pages = $pdf->getPages();
            $totalPaginas += count($pages);

            // Texto completo apenas para identificar tipo (buscando palavras chaves globais)
            $textoCompleto = $pdf->getText();
            $tipoReceita = identificarTipoReceita($textoCompleto);

            // Processa TODAS as páginas - cada página é uma receita separada
            // O PDF está sempre correto - não filtramos nada
            $medicamentos = [];

            foreach ($pages as $page) {
                $textoPagina = $page->getText();
                // Cada página é processada com exatamente as mesmas regras
                $medsPagina = extrairMedicamentos($textoPagina);

                // Adiciona TODOS os medicamentos encontrados, sem deduplicação
                foreach ($medsPagina as $medEncontrado) {
                    $medicamentos[] = $medEncontrado;
                }
            }

            foreach ($medicamentos as $med) {
                $info = getInfoMedicamento($med['nome'], $renameDB);
                $med['info_pictograma'] = getIconeParaOrientacao($med['orientacoes']);
                $med['emoji'] = $info['emoji'];
                $med['finalidade'] = $info['finalidade'];

                $textoCompletoAudio .= " {$med['nome']}, tomar {$med['orientacoes']}.";

                // Busca vídeo
                $videoUrl = buscarVideo($med['nome'], $videosDB);
                $med['video_url'] = $videoUrl;
                $med['qr_video'] = $videoUrl ? $writer->write(QrCode::create($videoUrl))->getDataUri() : null;

                $medicamentosProcessados[] = $med;
            }

        } catch (Exception $e) {
            error_log("Erro ao processar PDF: " . $e->getMessage());
            die("Erro ao processar o PDF: " . htmlspecialchars($e->getMessage()));
        }
    }

    // Análise de interações via Gemini (se disponível)
    if (count($medicamentosProcessados) > 1) {
        $analiseIA = analisarInteracoesGemini($medicamentosProcessados);
    }

    // Gera QR code de áudio
    $urlAudio = 'https://simplificareceita.com.br/play_audio.php?text=' . urlencode($textoCompletoAudio);
    $qrAudioDataUri = $writer->write(QrCode::create($urlAudio))->getDataUri();

    // Log de uso (sem dados pessoais)
    $processingTime = round((microtime(true) - $startTime) * 1000);
    try {
        executeQuery(
            "INSERT INTO logs_uso (total_medicamentos, total_paginas_pdf, tipo_receita, tempo_processamento_ms, ip_hash) VALUES (?, ?, ?, ?, ?)",
            [count($medicamentosProcessados), $totalPaginas, $tipoReceita, $processingTime, hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '')]
        );
    } catch (Exception $e) {
    }

} else {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisão da Receita - Simplifica Receita</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

    <!-- Microsoft Clarity -->
    <script type="text/javascript">
        (function (c, l, a, r, i, t, y) {
            c[a] = c[a] || function () { (c[a].q = c[a].q || []).push(arguments) };
            t = l.createElement(r); t.async = 1; t.src = "https://www.clarity.ms/tag/" + i;
            y = l.getElementsByTagName(r)[0]; y.parentNode.insertBefore(t, y);
        })(window, document, "clarity", "script", "utbxj4ntae");
    </script>

    <style>
        .review-container {
            max-width: 900px;
            margin: var(--space-xl) auto;
        }

        .page-header {
            text-align: center;
            margin-bottom: var(--space-xl);
        }

        .page-title {
            font-size: 2rem;
            margin-bottom: var(--space-sm);
        }

        .page-subtitle {
            color: var(--text-secondary);
        }

        .recipe-type-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-full);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: var(--space-md);
        }

        .recipe-type-comum {
            background: rgba(59, 130, 246, 0.15);
            color: var(--primary-light);
        }

        .recipe-type-especial {
            background: rgba(245, 158, 11, 0.15);
            color: #fcd34d;
        }

        .recipe-type-controlado {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
        }

        .med-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-md);
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: var(--space-lg);
            align-items: start;
            position: relative;
            transition: all 0.3s ease;
        }

        .med-card.removing {
            opacity: 0;
            transform: translateX(-100%);
            max-height: 0;
            padding: 0;
            margin: 0;
            overflow: hidden;
        }

        .med-delete-btn {
            position: absolute;
            top: var(--space-sm);
            right: var(--space-sm);
            width: 32px;
            height: 32px;
            border: none;
            background: rgba(239, 68, 68, 0.15);
            border-radius: var(--radius-sm);
            color: #f87171;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            z-index: 10;
        }

        .med-delete-btn:hover {
            background: rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            transform: scale(1.1);
        }

        .med-main {
            flex: 1;
        }

        .med-name-group {
            margin-bottom: var(--space-md);
        }

        .med-name-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: var(--space-xs);
        }

        .med-name-input {
            width: 100%;
            padding: var(--space-sm) var(--space-md);
            background: var(--surface);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 600;
        }

        .med-orientacoes-input {
            width: 100%;
            padding: var(--space-sm) var(--space-md);
            background: var(--surface);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .med-sidebar {
            text-align: center;
            min-width: 120px;
        }

        .med-emoji {
            font-size: 2.5rem;
            margin-bottom: var(--space-sm);
        }

        .med-finalidade {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--space-sm);
        }

        .med-pictograma {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .med-qr {
            margin-top: var(--space-md);
            padding-top: var(--space-md);
            border-top: 1px solid var(--glass-border);
        }

        .med-qr img {
            width: 80px;
            height: 80px;
            border-radius: var(--radius-sm);
            background: white;
            padding: 4px;
        }

        .med-qr-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: var(--space-xs);
        }

        .audio-section {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            text-align: center;
            margin: var(--space-xl) 0;
        }

        .audio-section h3 {
            margin-bottom: var(--space-md);
        }

        .audio-qr {
            width: 150px;
            height: 150px;
            border-radius: var(--radius-md);
            background: white;
            padding: 8px;
            margin: var(--space-md) auto;
            cursor: pointer;
            transition: transform var(--transition-normal);
        }

        .audio-qr:hover {
            transform: scale(1.05);
        }

        .audio-textarea {
            width: 100%;
            min-height: 100px;
            padding: var(--space-md);
            background: var(--surface);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            font-size: 0.9rem;
            resize: vertical;
            margin-top: var(--space-md);
        }

        .ia-section {
            margin-bottom: var(--space-xl);
        }

        .ia-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
        }

        .ia-title {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: var(--space-md);
        }

        .ia-title i {
            color: var(--accent);
        }

        .interaction-item {
            padding: var(--space-md);
            background: var(--surface);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-sm);
        }

        .interaction-meds {
            font-weight: 600;
            margin-bottom: var(--space-xs);
        }

        .interaction-desc {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .risk-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: var(--space-sm);
        }

        .risk-alto {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }

        .risk-moderado {
            background: rgba(245, 158, 11, 0.2);
            color: #fcd34d;
        }

        .risk-baixo {
            background: rgba(34, 197, 94, 0.2);
            color: #86efac;
        }

        .submit-section {
            text-align: center;
            padding: var(--space-xl) 0;
            border-top: 1px solid var(--glass-border);
            margin-top: var(--space-xl);
        }

        .submit-note {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: var(--space-lg);
        }

        @media (max-width: 768px) {
            .med-card {
                grid-template-columns: 1fr;
            }

            .med-sidebar {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: center;
                gap: var(--space-md);
            }

            .med-qr {
                border-top: none;
                padding-top: 0;
                margin-top: 0;
            }
        }
    </style>
</head>

<body>
    <div class="bg-animated"></div>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <div class="navbar-content">
                <a href="/" class="navbar-logo">
                    <div class="navbar-logo-icon">
                        <i class="fas fa-prescription-bottle-medical"></i>
                    </div>
                    Simplifica Receita
                </a>
                <div class="navbar-links">
                    <a href="/" class="navbar-link">Nova Receita</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container review-container">
        <div class="page-header fade-in">
            <div class="recipe-type-badge recipe-type-<?= $tipoReceita ?>">
                <i
                    class="fas fa-<?= $tipoReceita === 'especial' ? 'star' : ($tipoReceita === 'controlado' ? 'exclamation-circle' : 'file-medical') ?>"></i>
                Receituário <?= ucfirst($tipoReceita) ?>
            </div>
            <h1 class="page-title">Revise as Informações</h1>
            <p class="page-subtitle">Confira os dados extraídos, ajuste se necessário, e gere o PDF final</p>
        </div>

        <?php if ($analiseIA): ?>
            <!-- Análise de IA -->
            <div class="ia-section slide-up">
                <?php include 'includes/ai_disclaimer.php'; ?>

                <div class="ia-card">
                    <h3 class="ia-title">
                        <i class="fas fa-robot"></i>
                        Análise de Interações Medicamentosas
                    </h3>

                    <?php if (!empty($analiseIA['interacoes'])): ?>
                        <?php foreach ($analiseIA['interacoes'] as $interacao): ?>
                            <div class="interaction-item">
                                <div class="interaction-meds">
                                    <?= htmlspecialchars(implode(' + ', $interacao['medicamentos'] ?? [])) ?>
                                    <span class="risk-badge risk-<?= strtolower($interacao['risco'] ?? 'baixo') ?>">
                                        <?= htmlspecialchars($interacao['risco'] ?? 'Baixo') ?>
                                    </span>
                                </div>
                                <div class="interaction-desc">
                                    <?= htmlspecialchars($interacao['descricao'] ?? '') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="interaction-item">
                            <div class="interaction-desc">
                                <i class="fas fa-check-circle" style="color: var(--success); margin-right: 8px;"></i>
                                Nenhuma interação significativa identificada entre os medicamentos listados.
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($analiseIA['alertas_gerais'])): ?>
                        <div
                            style="margin-top: var(--space-md); padding: var(--space-md); background: rgba(245, 158, 11, 0.1); border-radius: var(--radius-md);">
                            <strong style="color: var(--warning);"><i class="fas fa-exclamation-triangle"></i> Alertas:</strong>
                            <ul style="margin: var(--space-sm) 0 0 var(--space-lg); color: var(--text-secondary);">
                                <?php foreach ($analiseIA['alertas_gerais'] as $alerta): ?>
                                    <li><?= htmlspecialchars($alerta) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Formulário de Revisão -->
        <form action="generate_pdf.php" method="post" target="_blank">
            <h2 style="margin-bottom: var(--space-lg);"><i class="fas fa-pills" style="color: var(--primary);"></i>
                Medicamentos Prescritos</h2>

            <?php foreach ($medicamentosProcessados as $index => $med): ?>
                <div class="med-card slide-up" id="med-card-<?= $index ?>" style="animation-delay: <?= $index * 0.1 ?>s">
                    <button type="button" class="med-delete-btn" onclick="removerMedicamento(<?= $index ?>)"
                        title="Remover medicamento">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="med-main">
                        <div class="med-name-group">
                            <div class="med-name-label">Medicamento</div>
                            <input type="text" name="medicamentos[<?= $index ?>][nome]"
                                value="<?= htmlspecialchars($med['nome']) ?>" class="med-name-input">
                        </div>
                        <div class="med-name-group">
                            <div class="med-name-label">Orientações</div>
                            <input type="text" name="medicamentos[<?= $index ?>][orientacoes]"
                                value="<?= htmlspecialchars($med['orientacoes']) ?>" class="med-orientacoes-input">
                        </div>

                        <!-- Recomendações - campo visível e editável -->
                        <?php if (!empty($med['recomendacoes'])): ?>
                            <div class="med-name-group" style="margin-top: var(--space-sm);">
                                <div class="med-name-label" style="color: var(--warning);">
                                    <i class="fas fa-exclamation-triangle"></i> Recomendações
                                </div>
                                <input type="text" name="medicamentos[<?= $index ?>][recomendacoes]"
                                    value="<?= htmlspecialchars($med['recomendacoes']) ?>" class="med-orientacoes-input"
                                    style="border-color: var(--warning); background: rgba(245, 158, 11, 0.1);">
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="medicamentos[<?= $index ?>][recomendacoes]" value="">
                        <?php endif; ?>

                        <!-- Hidden fields -->
                        <input type="hidden" name="medicamentos[<?= $index ?>][duracao]"
                            value="<?= htmlspecialchars($med['duracao']) ?>">
                        <input type="hidden" name="medicamentos[<?= $index ?>][qr_video]"
                            value="<?= htmlspecialchars($med['qr_video'] ?? '') ?>">
                        <input type="hidden" name="medicamentos[<?= $index ?>][emoji]"
                            value="<?= htmlspecialchars($med['emoji']) ?>">
                        <input type="hidden" name="medicamentos[<?= $index ?>][finalidade]"
                            value="<?= htmlspecialchars($med['finalidade']) ?>">
                    </div>

                    <div class="med-sidebar">
                        <div class="med-emoji"><?= $med['emoji'] ?></div>
                        <div class="med-finalidade"><?= htmlspecialchars($med['finalidade']) ?></div>
                        <div class="med-pictograma">
                            <span class="<?= $med['info_pictograma']['icone_classe'] ?>"></span>
                            <?= htmlspecialchars($med['info_pictograma']['texto']) ?>
                        </div>

                        <?php if ($med['qr_video']): ?>
                            <div class="med-qr">
                                <img src="<?= $med['qr_video'] ?>" alt="QR Code para vídeo">
                                <div class="med-qr-label"><i class="fas fa-video"></i> Vídeo explicativo</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Seção de Áudio -->
            <div class="audio-section slide-up">
                <h3><i class="fas fa-volume-up" style="color: var(--secondary);"></i> QR Code de Áudio</h3>
                <p style="color: var(--text-secondary); margin-bottom: var(--space-md);">
                    O paciente pode escanear este QR code para ouvir as instruções da receita
                </p>

                <a href="<?= htmlspecialchars($urlAudio ?? '#') ?>" target="_blank">
                    <img src="<?= $qrAudioDataUri ?? '' ?>" alt="QR Code para áudio" class="audio-qr">
                </a>
                <p style="font-size: 0.8rem; color: var(--text-secondary);">Clique no QR code para testar</p>

                <textarea name="audio_text"
                    class="audio-textarea"><?= htmlspecialchars($textoCompletoAudio) ?></textarea>
            </div>

            <!-- Submit -->
            <div class="submit-section">
                <p class="submit-note">
                    <i class="fas fa-info-circle"></i>
                    Ao clicar, será gerado um PDF combinado com o guia visual, etiquetas de recorte, e a receita
                    original anexada.
                </p>
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-file-pdf"></i>
                    Gerar PDF Completo
                </button>
            </div>
        </form>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <p>&copy; <?php echo date("Y"); ?> Simplifica Receita - Victor Pedrosa</p>
            </div>
        </div>
    </footer>
</body>

</html>

<script>
    function removerMedicamento(index) {
        const card = document.getElementById('med-card-' + index);
        if (card) {
            // Confirmar exclusão
            if (confirm('Deseja remover este medicamento da receita?')) {
                // Animar remoção
                card.classList.add('removing');

                // Desabilitar todos os inputs para não enviar no form
                const inputs = card.querySelectorAll('input, textarea, select');
                inputs.forEach(input => {
                    input.disabled = true;
                    input.name = ''; // Remove o name para não enviar
                });

                // Remover do DOM após animação
                setTimeout(() => {
                    card.remove();

                    // Verificar se ainda há medicamentos
                    const medsRestantes = document.querySelectorAll('.med-card:not(.removing)');
                    if (medsRestantes.length === 0) {
                        alert('Você removeu todos os medicamentos. Redirecionando para o início...');
                        window.location.href = '/';
                    }
                }, 300);
            }
        }
    }
</script>