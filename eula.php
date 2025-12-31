<?php
/**
 * EULA - Termos de Uso
 * End User License Agreement e conformidade com Microsoft Clarity
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termos de Uso - Simplifica Receita</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

    <!-- Microsoft Clarity removido -->

    <style>
        .legal-container {
            max-width: 800px;
            margin: var(--space-xl) auto;
        }

        .legal-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-xl);
            padding: var(--space-2xl);
        }

        .legal-title {
            font-size: 2rem;
            text-align: center;
            margin-bottom: var(--space-xl);
        }

        .legal-section {
            margin-bottom: var(--space-xl);
        }

        .legal-section h2 {
            font-size: 1.25rem;
            color: var(--primary-light);
            margin-bottom: var(--space-md);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .legal-section p,
        .legal-section ul,
        .legal-section li {
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: var(--space-md);
        }

        .legal-section ul {
            padding-left: var(--space-lg);
        }

        .legal-section li {
            margin-bottom: var(--space-sm);
        }

        .highlight-box {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: var(--radius-md);
            padding: var(--space-lg);
            margin: var(--space-lg) 0;
        }

        .highlight-box.info {
            background: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.3);
        }

        .highlight-box.success {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .last-updated {
            text-align: center;
            color: var(--text-tertiary);
            font-size: 0.875rem;
            margin-top: var(--space-xl);
            padding-top: var(--space-lg);
            border-top: 1px solid var(--glass-border);
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
                    <a href="/" class="navbar-link">Gerar Receita</a>
                    <a href="/eula.php" class="navbar-link active">Termos de Uso</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container legal-container">
        <div class="legal-card fade-in">
            <h1 class="legal-title">
                <i class="fas fa-file-contract" style="color: var(--primary);"></i>
                Termos de Uso
            </h1>

            <div class="legal-section">
                <h2><i class="fas fa-info-circle"></i> 1. Sobre a Plataforma</h2>
                <p>
                    O <strong>Simplifica Receita</strong> é uma ferramenta gratuita desenvolvida para auxiliar
                    profissionais de saúde na conversão de receitas médicas do e-SUS em formatos pictográficos
                    acessíveis, facilitando a compreensão por parte dos pacientes.
                </p>
                <p>
                    A plataforma oferece recursos como ícones visuais, QR codes com áudio das instruções,
                    vídeos educativos sobre medicamentos e, opcionalmente, análise de interações medicamentosas
                    por inteligência artificial.
                </p>
            </div>

            <div class="legal-section">
                <h2><i class="fas fa-robot"></i> 2. Uso de Inteligência Artificial</h2>
                <div class="highlight-box">
                    <strong><i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i> AVISO
                        IMPORTANTE:</strong>
                    <p style="margin-top: 8px; margin-bottom: 0;">
                        Esta plataforma utiliza Inteligência Artificial (Google Gemini) para análise de possíveis
                        interações medicamentosas. <strong>As análises geradas por IA podem conter erros e
                            NÃO devem ser utilizadas isoladamente para decisões clínicas.</strong>
                    </p>
                </div>
                <p>
                    O profissional de saúde é o único responsável pelas decisões clínicas e deve sempre:
                </p>
                <ul>
                    <li>Consultar literatura médica e fontes científicas atualizadas</li>
                    <li>Verificar informações em bases de dados farmacológicas confiáveis</li>
                    <li>Considerar o histórico completo do paciente</li>
                    <li>Aplicar seu julgamento clínico profissional</li>
                </ul>
            </div>

            <div class="legal-section">
                <h2><i class="fas fa-shield-halved"></i> 3. Privacidade e LGPD</h2>
                <div class="highlight-box success">
                    <strong><i class="fas fa-check-circle" style="color: var(--success);"></i> Compromisso com a
                        Privacidade:</strong>
                    <p style="margin-top: 8px; margin-bottom: 0;">
                        O nome do paciente e o arquivo PDF original <strong>NUNCA são armazenados</strong> em nossos
                        servidores.
                        Todos os dados pessoais são processados exclusivamente em memória e descartados imediatamente
                        após a geração do documento.
                    </p>
                </div>
                <p>Dados que <strong>NÃO</strong> são armazenados:</p>
                <ul>
                    <li>Nome do paciente</li>
                    <li>Arquivo PDF original da receita</li>
                    <li>Dados de identificação pessoal</li>
                </ul>
                <p>Dados que <strong>podem</strong> ser armazenados (anonimizados):</p>
                <ul>
                    <li>Total de medicamentos processados (sem identificação)</li>
                    <li>Tipo de receituário (comum, especial, controlado)</li>
                    <li>Tempo de processamento</li>
                    <li>Hash do IP (não reversível) para fins de segurança</li>
                </ul>
            </div>

            <div class="legal-section">
                <h2><i class="fas fa-chart-line"></i> 4. Microsoft Clarity</h2>
                <div class="highlight-box info">
                    <p style="margin-bottom: 0;">
                        Esta plataforma utiliza o <strong>Microsoft Clarity</strong> para análise de uso e melhoria
                        da experiência do usuário. O Clarity pode coletar dados de interação com a página, como
                        cliques e movimentos do mouse, para fins de análise.
                    </p>
                </div>
                <p>
                    Em conformidade com os Termos Adicionais do Microsoft Clarity, declaramos que:
                </p>
                <ul>
                    <li>Nossas atividades de processamento estão em conformidade com a legislação de proteção de dados
                    </li>
                    <li>Não utilizamos o Clarity para processar dados sensíveis de saúde</li>
                    <li>Informações pessoais de pacientes não são expostas ao Clarity</li>
                    <li>O processamento é realizado apenas em dados anonimizados de uso da interface</li>
                </ul>
                <p>
                    Para mais informações sobre como a Microsoft processa dados do Clarity, consulte a
                    <a href="https://privacy.microsoft.com/privacystatement" target="_blank" rel="noopener">
                        Política de Privacidade da Microsoft
                    </a>.
                </p>
            </div>

            <div class="legal-section">
                <h2><i class="fas fa-balance-scale"></i> 5. Limitação de Responsabilidade</h2>
                <p>
                    O Simplifica Receita é fornecido "como está", sem garantias de qualquer tipo.
                    Os desenvolvedores não se responsabilizam por:
                </p>
                <ul>
                    <li>Erros na extração de dados do PDF</li>
                    <li>Imprecisões nas análises de IA</li>
                    <li>Decisões clínicas tomadas com base nas informações geradas</li>
                    <li>Qualquer dano direto ou indireto decorrente do uso da plataforma</li>
                </ul>
                <p>
                    O profissional de saúde é integralmente responsável por revisar todas as informações
                    geradas antes de entregar ao paciente.
                </p>
            </div>

            <div class="legal-section">
                <h2><i class="fas fa-gavel"></i> 6. Uso Aceitável</h2>
                <p>Ao utilizar esta plataforma, você concorda em:</p>
                <ul>
                    <li>Utilizar a ferramenta apenas para fins legítimos de saúde</li>
                    <li>Revisar todas as informações geradas antes do uso</li>
                    <li>Não utilizar a plataforma para processar dados de forma contrária à LGPD</li>
                    <li>Não tentar burlar as medidas de segurança da plataforma</li>
                </ul>
            </div>

            <div class="legal-section">
                <h2><i class="fas fa-envelope"></i> 7. Contato</h2>
                <p>
                    Para dúvidas, sugestões ou relato de problemas, entre em contato:
                </p>
                <p>
                    <strong>E-mail:</strong> vvictor.pedrosa.vs@gmail.com<br>
                    <strong>Instagram:</strong> @vped.2000
                </p>
            </div>

            <div class="last-updated">
                <i class="fas fa-clock"></i>
                Última atualização: <?= date('d/m/Y') ?>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <p>&copy; <?= date("Y") ?> Simplifica Receita - Victor Pedrosa</p>
            </div>
        </div>
    </footer>
</body>

</html>