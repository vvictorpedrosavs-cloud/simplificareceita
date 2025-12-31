<?php
/**
 * generate_pdf.php - Versão Corrigida
 * Padrões visuais + PDF original no final
 */

require_once 'vendor/autoload.php';
require_once 'config/database.php';
session_start();

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use setasign\Fpdi\Tcpdf\Fpdi;

// ============================================================================
// VALIDAÇÃO
// ============================================================================

if (!isset($_POST['medicamentos'])) {
    die("ERRO: Dados da receita não encontrados.");
}

$pdf_original_path = $_SESSION['pdf_original_path'] ?? null;
$pdfOriginalDisponivel = $pdf_original_path && file_exists($pdf_original_path) && is_readable($pdf_original_path);

// Cores com padrões visuais expandidos (12 padrões)
$cores = [
    ['r' => 59, 'g' => 130, 'b' => 246, 'padrao' => 'horizontal'],
    ['r' => 239, 'g' => 68, 'b' => 68, 'padrao' => 'vertical'],
    ['r' => 16, 'g' => 185, 'b' => 129, 'padrao' => 'diagonal'],
    ['r' => 245, 'g' => 158, 'b' => 11, 'padrao' => 'pontos'],
    ['r' => 139, 'g' => 92, 'b' => 246, 'padrao' => 'grade'],
    ['r' => 236, 'g' => 72, 'b' => 153, 'padrao' => 'ondas'],
    ['r' => 6, 'g' => 182, 'b' => 212, 'padrao' => 'xis'],
    ['r' => 251, 'g' => 146, 'b' => 60, 'padrao' => 'quadrados'],
    ['r' => 163, 'g' => 230, 'b' => 53, 'padrao' => 'triangulos'],
    ['r' => 251, 'g' => 113, 'b' => 133, 'padrao' => 'zigzag'],
    ['r' => 148, 'g' => 163, 'b' => 184, 'padrao' => 'tijolos'],
    ['r' => 129, 'g' => 140, 'b' => 248, 'padrao' => 'losangos'],
];

$dadosReceita = [];
$audioText = $_POST['audio_text'] ?? '';

foreach ($_POST['medicamentos'] as $index => $med) {
    $med['cor'] = $cores[$index % count($cores)];
    $dadosReceita[] = $med;
}

// ============================================================================
// SESSÃO RECEITA DIGITAL
// ============================================================================

$receitaDigitalUrl = null;
try {
    $token = bin2hex(random_bytes(16));
    $medicamentosJson = json_encode($dadosReceita, JSON_UNESCAPED_UNICODE);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+6 months'));
    executeQuery(
        "INSERT INTO sessoes_receita (token, medicamentos_json, audio_text, expires_at) VALUES (?, ?, ?, ?)",
        [$token, $medicamentosJson, $audioText, $expiresAt]
    );
    $receitaDigitalUrl = 'https://simplificareceita.com.br/receita_digital.php?token=' . $token;
} catch (Exception $e) {
    error_log("Erro sessão: " . $e->getMessage());
}

// ============================================================================
// FUNÇÕES AUXILIARES
// ============================================================================

function desenharPadrao($pdf, $x, $y, $w, $h, $padrao, $cor)
{
    $pdf->SetFillColor($cor['r'], $cor['g'], $cor['b']);
    $pdf->Rect($x, $y, $w, $h, 'F');

    $pdf->SetDrawColor(255, 255, 255);
    $pdf->SetLineWidth(0.5);

    switch ($padrao) {
        case 'horizontal':
            for ($i = $y + 3; $i < $y + $h - 2; $i += 5) {
                $pdf->Line($x + 1, $i, $x + $w - 1, $i);
            }
            break;
        case 'vertical':
            for ($i = $x + 2; $i < $x + $w - 1; $i += 3) {
                $pdf->Line($i, $y + 2, $i, $y + $h - 2);
            }
            break;
        case 'diagonal':
            for ($i = 0; $i < $h + $w; $i += 4) {
                $x1 = $x + max(0, $i - $h);
                $y1 = $y + min($h, $i);
                $x2 = $x + min($w, $i);
                $y2 = $y + max(0, $i - $w);
                $pdf->Line($x1, $y1, $x2, $y2);
            }
            break;
        case 'pontos':
            $pdf->SetFillColor(255, 255, 255);
            for ($j = $y + 4; $j < $y + $h - 2; $j += 5) {
                for ($i = $x + 2; $i < $x + $w - 1; $i += 4) {
                    $pdf->Circle($i, $j, 1, 0, 360, 'F');
                }
            }
            break;
        case 'grade':
            for ($i = $y + 4; $i < $y + $h - 2; $i += 6) {
                $pdf->Line($x + 1, $i, $x + $w - 1, $i);
            }
            for ($i = $x + 2; $i < $x + $w - 1; $i += 4) {
                $pdf->Line($i, $y + 2, $i, $y + $h - 2);
            }
            break;
        case 'onda': // legacy
        case 'ondas':
            for ($i = $y + 4; $i < $y + $h - 2; $i += 4) {
                // Simplificação de onda como linha ondulada (zigzag suave)
                $pdf->Line($x + 1, $i, $x + $w - 1, $i);
                // Na verdade, desenhar onda é complexo com Line, faremos zigzag
                $py = $i;
                for ($px = $x; $px < $x + $w; $px += 4) {
                    $pdf->Line($px, $py, $px + 2, $py - 2);
                    $pdf->Line($px + 2, $py - 2, $px + 4, $py);
                }
            }
            break;
        case 'xis':
            for ($j = $y + 4; $j < $y + $h - 4; $j += 6) {
                for ($i = $x + 4; $i < $x + $w - 4; $i += 6) {
                    $pdf->Line($i - 2, $j - 2, $i + 2, $j + 2);
                    $pdf->Line($i + 2, $j - 2, $i - 2, $j + 2);
                }
            }
            break;
        case 'quadrados':
            for ($j = $y + 4; $j < $y + $h - 4; $j += 6) {
                for ($i = $x + 4; $i < $x + $w - 4; $i += 6) {
                    $pdf->Rect($i - 2, $j - 2, 4, 4);
                }
            }
            break;
        case 'triangulos':
            for ($j = $y + 4; $j < $y + $h - 2; $j += 6) {
                for ($i = $x + 4; $i < $x + $w - 2; $i += 6) {
                    $pdf->Line($i, $j + 2, $i + 2, $j - 2);
                    $pdf->Line($i + 2, $j - 2, $i + 4, $j + 2);
                    $pdf->Line($i + 4, $j + 2, $i, $j + 2);
                }
            }
            break;
        case 'zigzag':
            for ($j = $y + 4; $j < $y + $h - 2; $j += 4) {
                for ($i = $x; $i < $x + $w; $i += 4) {
                    $pdf->Line($i, $j, $i + 2, $j - 2);
                    $pdf->Line($i + 2, $j - 2, $i + 4, $j);
                }
            }
            break;
        case 'tijolos':
            for ($j = $y + 2; $j < $y + $h; $j += 4) {
                $offset = ($j % 8 == 2) ? 0 : 4;
                for ($i = $x + $offset; $i < $x + $w; $i += 8) {
                    $pdf->Rect($i, $j, 8, 4);
                }
            }
            break;
        case 'losangos':
            for ($j = $y + 4; $j < $y + $h - 4; $j += 6) {
                for ($i = $x + 4; $i < $x + $w - 4; $i += 6) {
                    $pdf->Line($i, $j - 3, $i + 3, $j);
                    $pdf->Line($i + 3, $j, $i, $j + 3);
                    $pdf->Line($i, $j + 3, $i - 3, $j);
                    $pdf->Line($i - 3, $j, $i, $j - 3);
                }
            }
            break;
        default:
            break;
    }
    $pdf->SetLineWidth(0.2);
}

function desenharSol($pdf, $x, $y, $size)
{
    $cx = $x + $size / 2;
    $cy = $y + $size / 2;
    $raio = $size * 0.25;
    $pdf->SetFillColor(255, 200, 0);
    $pdf->SetDrawColor(200, 150, 0);
    $pdf->Circle($cx, $cy, $raio, 0, 360, 'DF');
    $pdf->SetLineWidth(0.8);
    for ($a = 0; $a < 360; $a += 45) {
        $rad = deg2rad($a);
        $pdf->Line(
            $cx + cos($rad) * ($raio + 1),
            $cy + sin($rad) * ($raio + 1),
            $cx + cos($rad) * ($raio + 3),
            $cy + sin($rad) * ($raio + 3)
        );
    }
    $pdf->SetLineWidth(0.2);
}

function desenharPrato($pdf, $x, $y, $size)
{
    $cx = $x + $size / 2;
    $cy = $y + $size / 2;
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetDrawColor(150, 150, 150);
    $pdf->Circle($cx, $cy, $size * 0.35, 0, 360, 'DF');
    $pdf->Circle($cx, $cy, $size * 0.2, 0, 360, 'D');
}

function desenharLua($pdf, $x, $y, $size)
{
    $cx = $x + $size / 2;
    $cy = $y + $size / 2;
    $raio = $size * 0.3;
    $pdf->SetFillColor(255, 230, 150);
    $pdf->SetDrawColor(200, 180, 100);
    $pdf->Circle($cx - 1, $cy, $raio, 0, 360, 'DF');
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Circle($cx + 2, $cy - 1, $raio * 0.8, 0, 360, 'F');
}

// ============================================================================
// INICIA PDF
// ============================================================================

$pdf = new Fpdi();
$pdf->SetCreator('Simplifica Receita');
$pdf->SetTitle('Receita Visual');
$writer = new PngWriter();

// ============================================================================
// PÁGINAS DE MEDICAMENTOS
// ============================================================================

$medsPerPage = 4;
$totalMeds = count($dadosReceita);
$totalMedPages = ceil($totalMeds / $medsPerPage);

for ($pageNum = 0; $pageNum < $totalMedPages; $pageNum++) {
    $pdf->AddPage();
    $pdf->SetMargins(15, 15, 15);

    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(59, 130, 246);
    $pdf->Cell(0, 12, 'Guia Visual da sua Receita', 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(120, 120, 120);
    $pageLabel = $totalMedPages > 1 ? ' | Pagina ' . ($pageNum + 1) . '/' . $totalMedPages : '';
    $pdf->Cell(0, 5, 'Simplifica Receita - ' . date('d/m/Y') . $pageLabel, 0, 1, 'C');

    $pdf->SetDrawColor(59, 130, 246);
    $pdf->SetLineWidth(0.8);
    $pdf->Line(50, $pdf->GetY() + 3, 160, $pdf->GetY() + 3);
    $pdf->Ln(10);
    $pdf->SetTextColor(0, 0, 0);

    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(59, 130, 246);
    $pdf->Cell(0, 8, 'Seus Medicamentos', 0, 1);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(3);

    $startIdx = $pageNum * $medsPerPage;
    $medsThisPage = array_slice($dadosReceita, $startIdx, $medsPerPage);

    foreach ($medsThisPage as $med) {
        $startY = $pdf->GetY();
        $cor = $med['cor'];

        desenharPadrao($pdf, 15, $startY, 8, 28, $cor['padrao'], $cor);

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetXY(27, $startY);
        $pdf->MultiCell(118, 6, $med['nome'], 0, 'L');

        if (!empty($med['qr_video'])) {
            $qrVideoData = base64_decode(preg_replace('#^data:image/png;base64,#', '', $med['qr_video']));
            $pdf->Image('@' . $qrVideoData, 168, $startY, 24, 24, 'PNG');
        }

        $currentY = max($startY + 7, $pdf->GetY());
        $pdf->SetY($currentY);

        if (!empty($med['finalidade'])) {
            $pdf->SetX(27);
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 5, 'Para: ' . $med['finalidade'], 0, 1);
            $pdf->SetTextColor(0, 0, 0);
        }

        $pdf->SetX(27);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell(133, 5, 'Como tomar: ' . $med['orientacoes'], 0, 'L');

        if (!empty($med['duracao'])) {
            $pdf->SetX(27);
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->Cell(0, 5, $med['duracao'], 0, 1);
            $pdf->SetTextColor(0, 0, 0);
        }

        if (!empty($med['recomendacoes']) && trim($med['recomendacoes']) !== '') {
            $pdf->Ln(1);
            $pdf->SetX(27);
            $pdf->SetFillColor(255, 250, 200);
            $pdf->SetDrawColor(220, 180, 0);
            $pdf->SetLineWidth(0.4);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(150, 100, 0);
            $pdf->MultiCell(133, 6, 'ATENCAO: ' . $med['recomendacoes'], 1, 'L', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetLineWidth(0.2);
        }

        $pdf->Ln(6);
    }

    // TABELA DE HORÁRIOS
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(59, 130, 246);
    $pdf->Cell(0, 8, 'Quadro de Horarios', 0, 1);
    $pdf->SetTextColor(0, 0, 0);

    $tableStartX = 15;
    $medColWidth = 55;
    $colWidth = 32;
    $cellHeight = 14;
    $iconSize = 14;

    $iconY = $pdf->GetY();
    desenharSol($pdf, $tableStartX + $medColWidth + 9, $iconY, $iconSize);
    desenharPrato($pdf, $tableStartX + $medColWidth + $colWidth + 9, $iconY, $iconSize);
    desenharSol($pdf, $tableStartX + $medColWidth + $colWidth * 2 + 9, $iconY, $iconSize);
    desenharLua($pdf, $tableStartX + $medColWidth + $colWidth * 3 + 9, $iconY, $iconSize);

    $pdf->SetY($iconY + $iconSize + 2);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(245, 247, 250);
    $pdf->SetDrawColor(180, 180, 180);

    $pdf->Cell($medColWidth, $cellHeight, 'Medicamento', 1, 0, 'C', 1);
    $pdf->Cell($colWidth, $cellHeight, 'Manha', 1, 0, 'C', 1);
    $pdf->Cell($colWidth, $cellHeight, 'Almoco', 1, 0, 'C', 1);
    $pdf->Cell($colWidth, $cellHeight, 'Tarde', 1, 0, 'C', 1);
    $pdf->Cell($colWidth, $cellHeight, 'Noite', 1, 1, 'C', 1);

    foreach ($medsThisPage as $med) {
        $o = strtolower($med['orientacoes']);
        $horarios = ['manha' => false, 'almoco' => false, 'tarde' => false, 'noite' => false];

        if (preg_match('/(\d+)\s*vezes?\s*(ao|por)\s*dia/i', $o, $m)) {
            $vezes = (int) $m[1];
            if ($vezes >= 1)
                $horarios['manha'] = true;
            if ($vezes >= 2)
                $horarios['noite'] = true;
            if ($vezes >= 3)
                $horarios['tarde'] = true;
            if ($vezes >= 4)
                $horarios['almoco'] = true;
        }

        if (preg_match('/a cada (\d+)\s?horas?/i', $o, $m)) {
            $horas = (int) $m[1];
            if ($horas <= 6)
                $horarios = ['manha' => true, 'almoco' => true, 'tarde' => true, 'noite' => true];
            elseif ($horas <= 8)
                $horarios = ['manha' => true, 'tarde' => true, 'noite' => true];
            elseif ($horas <= 12)
                $horarios = ['manha' => true, 'noite' => true];
        }

        if (strpos($o, 'manh') !== false || strpos($o, 'jejum') !== false)
            $horarios['manha'] = true;
        if (strpos($o, 'noite') !== false || strpos($o, 'deitar') !== false)
            $horarios['noite'] = true;

        // Remove truncamento e usa MultiCell
        $nome = $med['nome'];

        $pdf->SetFont('helvetica', '', 8);

        // Guarda posição Y inicial
        $yStart = $pdf->GetY();
        $xStart = $pdf->GetX();

        // Calcula altura necessária para o nome
        $pdf->MultiCell($medColWidth, $cellHeight, $nome, 1, 'L', false, 0); // 0 no final = não mudar linha ainda

        // TCPDF MultiCell com reseting pointer é tricky. Melhor forma para row height variável:
        // 1. Calcular NumLines
        $numLines = $pdf->getNumLines($nome, $medColWidth);
        $realHeight = max($cellHeight, $numLines * 4); // 4mm por linha aprox

        // Volta para desenhar
        $pdf->SetXY($xStart, $yStart);
        $pdf->MultiCell($medColWidth, $realHeight, $nome, 1, 'L', 0, 0);

        // Move para colunas de horário
        $currentX = $xStart + $medColWidth;

        $cor = $med['cor'];

        foreach (['manha', 'almoco', 'tarde', 'noite'] as $horario) {
            $pdf->SetXY($currentX, $yStart);

            if ($horarios[$horario]) {
                desenharPadrao($pdf, $currentX, $yStart, $colWidth, $realHeight, $cor['padrao'], $cor);
                $pdf->SetXY($currentX, $yStart);
                $pdf->SetDrawColor(180, 180, 180);
                $pdf->Cell($colWidth, $realHeight, '', 1, 0, 'C');
            } else {
                $pdf->Cell($colWidth, $realHeight, '-', 1, 0, 'C');
            }
            $currentX += $colWidth;
        }
        $pdf->Ln($realHeight); // Avança linha final
    }
}

// ============================================================================
// PÁGINA: QR CODE RECEITA DIGITAL
// ============================================================================

if ($receitaDigitalUrl) {
    $pdf->AddPage();
    $pdf->Ln(25);
    $pdf->SetFont('helvetica', 'B', 22);
    $pdf->SetTextColor(59, 130, 246);
    $pdf->Cell(0, 12, 'Prescrição para Salvar', 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Ln(5);
    $pdf->Cell(0, 6, 'Escaneie o QR Code para acessar sua Receita Digital', 0, 1, 'C');

    $pdf->Ln(15);
    $qrDigital = QrCode::create($receitaDigitalUrl);
    $qrDigitalData = $writer->write($qrDigital)->getString();
    // QR Code menor (45x45)
    $pdf->Image('@' . $qrDigitalData, 82, $pdf->GetY(), 45, 45, 'PNG');

    $pdf->Ln(55);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(0, 5, 'Validade: 6 meses | Clarity: Alta Resolução', 0, 1, 'C');
}

// ============================================================================
// PÁGINA: ETIQUETAS
// ============================================================================

$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor(59, 130, 246);
$pdf->Cell(0, 10, 'Etiquetas para Colar nas Caixas', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'Recorte nas linhas pontilhadas', 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(5);

$labelWidth = 50;
$labelHeight = 30;
$labelsPerRow = 3;
$marginX = 15;
$marginY = $pdf->GetY();
$gapX = 5;
$gapY = 5;
$labelRowStart = 0;

foreach ($dadosReceita as $index => $med) {
    $col = $index % $labelsPerRow;
    $row = floor($index / $labelsPerRow) - $labelRowStart;

    $x = $marginX + ($col * ($labelWidth + $gapX));
    $y = $marginY + ($row * ($labelHeight + $gapY));

    if ($y + $labelHeight > 280) {
        $pdf->AddPage();
        $marginY = 20;
        $labelRowStart = floor($index / $labelsPerRow);
        $row = 0;
        $y = $marginY;
    }

    // Linha contínua
    $pdf->SetLineStyle(['width' => 0.3, 'color' => [180, 180, 180]]);
    $pdf->Rect($x, $y, $labelWidth, $labelHeight, 'D');
    desenharPadrao($pdf, $x, $y, 5, $labelHeight, $med['cor']['padrao'], $med['cor']);

    $textoQR = "{$med['nome']}. {$med['orientacoes']}.";
    $urlAudio = 'https://simplificareceita.com.br/play_audio.php?text=' . urlencode($textoQR);
    $qrCode = QrCode::create($urlAudio);
    $qrResult = $writer->write($qrCode)->getString();
    $pdf->Image('@' . $qrResult, $x + 6, $y + 4, 18, 18, 'PNG');

    $pdf->SetFont('helvetica', 'B', 7);
    // Remove truncamento para etiquetas
    $nomeDisplay = $med['nome'];
    $pdf->SetXY($x + 26, $y + 2);
    $pdf->MultiCell($labelWidth - 28, 3, $nomeDisplay, 0, 'L');
    $yNomeEnd = $pdf->GetY();

    $pdf->SetFont('helvetica', '', 6);
    // Posiciona Finalidade logo abaixo do nome (dinamicamente)
    $pdf->SetXY($x + 26, $yNomeEnd + 1);
    $pdf->Cell($labelWidth - 28, 3, $med['finalidade'] ?? '', 0, 1);

    $pdf->SetXY($x + 6, $y + 24);
    $pdf->SetFont('helvetica', '', 5);
    // Combina orientacao + recomendacao
    $fullText = $med['orientacoes'];
    if (!empty($med['recomendacoes'])) {
        $fullText .= ' (' . $med['recomendacoes'] . ')';
    }
    $pdf->MultiCell($labelWidth - 8, 3, $fullText, 0, 'L');
}

// ============================================================================
// PÁGINA FINAL: RECEITA ORIGINAL
// ============================================================================

if ($pdfOriginalDisponivel) {
    try {
        $pageCount = $pdf->setSourceFile($pdf_original_path);
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);

            // Margem zero tb para FPDI
            $pdf->SetMargins(0, 0, 0, true);
            $pdf->SetAutoPageBreak(false, 0);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            // Sem cabeçalho "Via Original"
        }
    } catch (Exception $e) {
        error_log("FPDI erro: " . $e->getMessage());

        // FALHA FPDI DETECTADA (provavelmente compressão > 1.4)
        // TENTA FALLBACK COM IMAGICK (Converte para imagem e anexa)
        if (extension_loaded('imagick')) {
            try {
                $imagick = new Imagick();
                $imagick->setResolution(300, 300); // Alta definição (300 DPI)
                $imagick->readImage($pdf_original_path);

                foreach ($imagick as $numeroPagina => $pagina) {
                    $pagina->setImageFormat('jpg');
                    $pagina->setImageCompressionQuality(100); // Qualidade máxima

                    // Salva temporário
                    $tempImg = tempnam(sys_get_temp_dir(), 'pdf_pg_') . '.jpg';
                    $pagina->writeImage($tempImg);

                    // Adiciona ao PDF
                    // Adiciona ao PDF com detecção de orientação
                    // Adiciona ao PDF com detecção de orientação
                    $width = $pagina->getImageWidth();
                    $height = $pagina->getImageHeight();
                    $orientation = ($width > $height) ? 'L' : 'P';

                    // Garante margem zero para preenchimento total
                    $pdf->SetMargins(0, 0, 0, true); // true = force
                    $pdf->SetAutoPageBreak(false, 0);

                    $pdf->AddPage($orientation);

                    // Sem cabeçalho "Via Original"

                    // Ajusta tamanho para PÁGINA INTEIRA (margem 0)
                    // A4: 297mm (L) ou 210mm (P)
                    $fullWidth = ($orientation == 'L') ? 297 : 210;
                    $pdf->Image($tempImg, 0, 0, $fullWidth, 0, 'JPG');

                    // Limpa temp
                    @unlink($tempImg);
                }

                $imagick->clear();
                $imagick->destroy();

            } catch (Exception $e2) {
                error_log("Imperdoável: Falha também no Imagick: " . $e2->getMessage());
                // Se tudo falhar (muito raro), silenciosamente segue (ou poderíamos por uma nota mínima)
            }
        }
    }
}

// Limpa
if ($pdfOriginalDisponivel && file_exists($pdf_original_path)) {
    @unlink($pdf_original_path);
}
unset($_SESSION['dados_receita'], $_SESSION['pdf_original_path']);

$pdf->Output('receita_visual_' . date('Y-m-d_His') . '.pdf', 'I');
?>