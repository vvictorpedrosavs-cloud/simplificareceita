<?php
/**
 * Receita Digital
 * Página acessível por QR code para o paciente visualizar sua receita
 * com vídeos explicativos e áudio das instruções
 */

session_start();
require_once 'config/database.php';

$token = $_GET['token'] ?? '';
$medicamentos = [];
$audioText = '';
$error = '';

if (!empty($token)) {
    try {
        $stmt = executeQuery(
            "SELECT medicamentos_json, audio_text FROM sessoes_receita WHERE token = ? AND expires_at > NOW()",
            [$token]
        );
        $sessao = $stmt->fetch();

        if ($sessao) {
            $medicamentos = json_decode($sessao['medicamentos_json'], true) ?: [];
            $audioText = $sessao['audio_text'] ?? '';
        } else {
            $error = 'Esta receita expirou ou não foi encontrada.';
        }
    } catch (Exception $e) {
        $error = 'Erro ao carregar a receita. Por favor, tente novamente.';
    }
} else {
    $error = 'Nenhuma receita especificada.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sua Receita Digital - Simplifica Receita</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

    <!-- Microsoft Clarity 
     NA VERSÃO DE PRODUÇÃO FICA AQUI PARA AVALIAR E OBTER DADOS ESSENCIAIS DA APLICAÇÃO;
     NA VERSÃO DO GITHUB É REMOVIDA 
    -->

    <style>
        .receita-container {
            max-width: 600px;
            margin: 0 auto;
            padding: var(--space-md);
        }

        .receita-header {
            text-align: center;
            padding: var(--space-xl) 0;
        }

        .receita-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space-md);
            font-size: 1.5rem;
            color: white;
        }

        .receita-title {
            font-size: 1.5rem;
            margin-bottom: var(--space-sm);
        }

        .receita-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .audio-button {
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
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: var(--space-xl);
            transition: all var(--transition-normal);
        }

        .audio-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.4);
        }

        .audio-button.playing {
            background: linear-gradient(135deg, var(--error), #dc2626);
        }

        .med-item {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-md);
            overflow: hidden;
        }

        .med-header {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-md);
            cursor: pointer;
        }

        .med-emoji {
            font-size: 2rem;
        }

        .med-info {
            flex: 1;
        }

        .med-name {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 4px;
        }

        .med-orientacao {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .med-expand {
            color: var(--text-secondary);
            transition: transform var(--transition-fast);
        }

        .med-item.expanded .med-expand {
            transform: rotate(180deg);
        }

        .med-details {
            display: none;
            padding: 0 var(--space-md) var(--space-md);
            border-top: 1px solid var(--glass-border);
        }

        .med-item.expanded .med-details {
            display: block;
        }

        .med-detail-row {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) 0;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .med-detail-row i {
            width: 20px;
            color: var(--primary-light);
        }

        .video-container {
            margin-top: var(--space-md);
            border-radius: var(--radius-md);
            overflow: hidden;
            background: black;
        }

        .video-container iframe {
            width: 100%;
            aspect-ratio: 16/9;
            border: none;
        }

        .error-container {
            text-align: center;
            padding: var(--space-2xl);
        }

        .error-icon {
            font-size: 4rem;
            color: var(--error);
            margin-bottom: var(--space-lg);
        }

        .error-text {
            color: var(--text-secondary);
            margin-bottom: var(--space-lg);
        }
    </style>
</head>

<body>
    <div class="bg-animated"></div>

    <div class="receita-container">
        <?php if ($error): ?>
            <div class="error-container fade-in">
                <div class="error-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h2>Ops!</h2>
                <p class="error-text"><?= htmlspecialchars($error) ?></p>
                <a href="/" class="btn btn-primary">
                    <i class="fas fa-home"></i> Ir para o início
                </a>
            </div>
        <?php else: ?>

            <div class="receita-header fade-in">
                <div class="receita-logo">
                    <i class="fas fa-prescription-bottle-medical"></i>
                </div>
                <h1 class="receita-title">Sua Receita Digital</h1>
                <p class="receita-subtitle">Toque nos medicamentos para ver mais detalhes</p>
            </div>

            <?php if ($audioText): ?>
                <button id="audio-btn" class="audio-button slide-up">
                    <i class="fas fa-volume-up" id="audio-icon"></i>
                    <span id="audio-text">Ouvir Instruções</span>
                </button>
            <?php endif; ?>

            <div class="med-list">
                <?php foreach ($medicamentos as $index => $med): ?>
                    <div class="med-item slide-up" style="animation-delay: <?= $index * 0.1 ?>s" data-index="<?= $index ?>">
                        <div class="med-header">
                            <span class="med-emoji"><?= htmlspecialchars($med['emoji'] ?? '💊') ?></span>
                            <div class="med-info">
                                <div class="med-name"><?= htmlspecialchars($med['nome']) ?></div>
                                <div class="med-orientacao">
                                    <?= htmlspecialchars($med['orientacoes'] ?? 'Conforme orientação médica') ?>
                                </div>
                            </div>
                            <i class="fas fa-chevron-down med-expand"></i>
                        </div>

                        <div class="med-details">
                            <?php if (!empty($med['finalidade'])): ?>
                                <div class="med-detail-row">
                                    <i class="fas fa-bullseye"></i>
                                    <span>Para: <?= htmlspecialchars($med['finalidade']) ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($med['duracao'])): ?>
                                <div class="med-detail-row">
                                    <i class="fas fa-calendar-days"></i>
                                    <span><?= htmlspecialchars($med['duracao']) ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($med['recomendacoes'])): ?>
                                <div class="med-detail-row">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <span><?= htmlspecialchars($med['recomendacoes']) ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($med['video_url'])):
                                $embedUrl = str_replace('watch?v=', 'embed/', $med['video_url']);
                                ?>
                                <div class="video-container">
                                    <iframe src="<?= htmlspecialchars($embedUrl) ?>"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                        allowfullscreen>
                                    </iframe>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>

    <script>
        // Toggle medication details
        document.querySelectorAll('.med-header').forEach(header => {
            header.addEventListener('click', () => {
                header.closest('.med-item').classList.toggle('expanded');
            });
        });

        // Audio player
        const audioBtn = document.getElementById('audio-btn');
        const audioText = <?= json_encode($audioText) ?>;

        if (audioBtn && 'speechSynthesis' in window) {
            let speaking = false;

            audioBtn.addEventListener('click', () => {
                if (speaking) {
                    window.speechSynthesis.cancel();
                    speaking = false;
                    document.getElementById('audio-icon').className = 'fas fa-volume-up';
                    document.getElementById('audio-text').textContent = 'Ouvir Instruções';
                    audioBtn.classList.remove('playing');
                } else {
                    const utterance = new SpeechSynthesisUtterance(audioText);
                    utterance.lang = 'pt-BR';
                    utterance.rate = 0.9;

                    utterance.onend = () => {
                        speaking = false;
                        document.getElementById('audio-icon').className = 'fas fa-volume-up';
                        document.getElementById('audio-text').textContent = 'Ouvir Novamente';
                        audioBtn.classList.remove('playing');
                    };

                    window.speechSynthesis.speak(utterance);
                    speaking = true;
                    document.getElementById('audio-icon').className = 'fas fa-stop';
                    document.getElementById('audio-text').textContent = 'Parar';
                    audioBtn.classList.add('playing');
                }
            });
        }
    </script>
</body>

</html>