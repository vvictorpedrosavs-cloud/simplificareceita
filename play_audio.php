<?php
/**
 * play_audio.php
 * Página de áudio com TTS e vídeos relacionados
 */

require_once 'config/database.php';

$textoParaFalar = isset($_GET['text']) ? htmlspecialchars($_GET['text'], ENT_QUOTES, 'UTF-8') : 'Nenhuma instrução fornecida.';

// Busca vídeos relacionados
$videosEncontrados = [];

try {
    $stmt = executeQuery("SELECT medicamento, youtube_url, titulo FROM videos_medicamentos WHERE ativo = 1");
    $videosDB = $stmt->fetchAll();

    foreach ($videosDB as $video) {
        if (stripos($textoParaFalar, $video['medicamento']) !== false) {
            $embedUrl = str_replace("watch?v=", "embed/", $video['youtube_url']);
            $videosEncontrados[] = [
                'medicamento' => $video['medicamento'],
                'titulo' => $video['titulo'] ?: $video['medicamento'],
                'url' => $embedUrl
            ];
        }
    }
} catch (Exception $e) {
    // Fallback para JSON local
    $videosDB = json_decode(file_get_contents('videos.json'), true) ?: [];
    foreach ($videosDB as $video) {
        if (stripos($textoParaFalar, $video['medicamento']) !== false) {
            $embedUrl = str_replace("watch?v=", "embed/", $video['url']);
            $videosEncontrados[] = [
                'medicamento' => $video['medicamento'],
                'titulo' => $video['medicamento'],
                'url' => $embedUrl
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instruções da Receita - Simplifica Receita</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

    <!-- Microsoft Clarity 
     NA VERSÃO DE PRODUÇÃO FICA AQUI PARA AVALIAR E OBTER DADOS ESSENCIAIS DA APLICAÇÃO;
     NA VERSÃO DO GITHUB É REMOVIDA 
    -->

    <style>
        .audio-container {
            max-width: 600px;
            margin: 0 auto;
            padding: var(--space-md);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .audio-header {
            text-align: center;
            padding: var(--space-xl) 0;
        }

        .audio-logo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space-md);
            font-size: 1.75rem;
            color: white;
        }

        .audio-title {
            font-size: 1.5rem;
            margin-bottom: var(--space-sm);
        }

        .audio-subtitle {
            color: var(--text-secondary);
        }

        .text-box {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
        }

        .text-content {
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--text-primary);
        }

        .highlight {
            background: rgba(245, 158, 11, 0.3);
            padding: 2px 6px;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .play-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-md);
            width: 100%;
            padding: var(--space-lg);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            border-radius: var(--radius-lg);
            color: white;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-normal);
            margin-bottom: var(--space-xl);
        }

        .play-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.4);
        }

        .play-button.playing {
            background: linear-gradient(135deg, var(--error), #dc2626);
        }

        .play-button i {
            font-size: 1.5rem;
        }

        .videos-section {
            margin-top: auto;
            padding-top: var(--space-xl);
            border-top: 1px solid var(--glass-border);
        }

        .videos-title {
            font-size: 1.1rem;
            margin-bottom: var(--space-md);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .videos-title i {
            color: var(--error);
        }

        .video-item {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: var(--space-md);
        }

        .video-header {
            padding: var(--space-md);
            font-weight: 600;
            border-bottom: 1px solid var(--glass-border);
        }

        .video-embed {
            aspect-ratio: 16/9;
        }

        .video-embed iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: var(--space-xl);
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .back-link:hover {
            color: var(--primary-light);
        }
    </style>
</head>

<body>
    <div class="bg-animated"></div>

    <div class="audio-container">
        <div class="audio-header fade-in">
            <div class="audio-logo">
                <i class="fas fa-volume-up"></i>
            </div>
            <h1 class="audio-title">Instruções da Receita</h1>
            <p class="audio-subtitle">Ouça as orientações de uso dos medicamentos</p>
        </div>

        <div class="text-box slide-up">
            <div id="texto-container" class="text-content"></div>
        </div>

        <button id="play-button" class="play-button slide-up">
            <i id="play-icon" class="fas fa-play"></i>
            <span id="play-text">Ouvir Instruções</span>
        </button>

        <?php if (!empty($videosEncontrados)): ?>
            <div class="videos-section slide-up">
                <h2 class="videos-title">
                    <i class="fab fa-youtube"></i>
                    Vídeos Explicativos
                </h2>

                <?php foreach ($videosEncontrados as $video): ?>
                    <div class="video-item">
                        <div class="video-header">
                            <i class="fas fa-pills" style="color: var(--primary); margin-right: 8px;"></i>
                            <?= htmlspecialchars($video['titulo']) ?>
                        </div>
                        <div class="video-embed">
                            <iframe src="<?= htmlspecialchars($video['url']) ?>"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen>
                            </iframe>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a href="/" class="back-link">
            <i class="fas fa-arrow-left"></i> Voltar ao início
        </a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const texto = <?= json_encode($textoParaFalar) ?>;
            const textoContainer = document.getElementById('texto-container');
            const playButton = document.getElementById('play-button');
            const playIcon = document.getElementById('play-icon');
            const playText = document.getElementById('play-text');

            // Prepara o texto dividindo em palavras
            const palavras = texto.split(/\s+/);
            textoContainer.innerHTML = palavras.map((palavra, index) =>
                `<span id="palavra-${index}">${palavra}</span>`
            ).join(' ');

            let speaking = false;

            function falar() {
                if (!('speechSynthesis' in window)) {
                    alert('Seu navegador não suporta leitura em voz alta.');
                    return;
                }

                window.speechSynthesis.cancel();

                const utterance = new SpeechSynthesisUtterance(texto);
                utterance.lang = 'pt-BR';
                utterance.rate = 0.85;

                utterance.onboundary = function (event) {
                    if (event.name !== 'word') return;

                    // Remove destaque anterior
                    document.querySelectorAll('#texto-container span').forEach(span => {
                        span.classList.remove('highlight');
                    });

                    // Encontra a palavra atual
                    const charIndex = event.charIndex;
                    let accumulatedLength = 0;

                    for (let i = 0; i < palavras.length; i++) {
                        const endPos = accumulatedLength + palavras[i].length;

                        if (charIndex >= accumulatedLength && charIndex < endPos) {
                            const span = document.getElementById(`palavra-${i}`);
                            if (span) {
                                span.classList.add('highlight');
                                span.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                            break;
                        }

                        accumulatedLength = endPos + 1; // +1 para espaço
                    }
                };

                utterance.onend = function () {
                    speaking = false;
                    document.querySelectorAll('#texto-container span').forEach(span => {
                        span.classList.remove('highlight');
                    });
                    playIcon.className = 'fas fa-redo';
                    playText.textContent = 'Ouvir Novamente';
                    playButton.classList.remove('playing');
                };

                window.speechSynthesis.speak(utterance);
                speaking = true;
                playIcon.className = 'fas fa-stop';
                playText.textContent = 'Parar';
                playButton.classList.add('playing');
            }

            playButton.addEventListener('click', function () {
                if (speaking) {
                    window.speechSynthesis.cancel();
                    speaking = false;
                    document.querySelectorAll('#texto-container span').forEach(span => {
                        span.classList.remove('highlight');
                    });
                    playIcon.className = 'fas fa-play';
                    playText.textContent = 'Ouvir Instruções';
                    playButton.classList.remove('playing');
                } else {
                    falar();
                }
            });
        });
    </script>
</body>

</html>