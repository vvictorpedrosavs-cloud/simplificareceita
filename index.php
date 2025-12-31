<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Simplifique receitas médicas do e-SUS em versões pictográficas acessíveis para pacientes">
    <title>Simplifica Receita - Receitas Pictográficas Acessíveis</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Styles -->
    <link href="assets/css/style.css" rel="stylesheet">

    <!-- Microsoft Clarity 
     NA VERSÃO DE PRODUÇÃO FICA AQUI PARA AVALIAR E OBTER DADOS ESSENCIAIS DA APLICAÇÃO;
     NA VERSÃO DO GITHUB É REMOVIDA 
    -->

    <style>
        /* Page-specific styles */
        .hero {
            text-align: center;
            padding: var(--space-2xl) 0;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            background: var(--primary-50);
            color: var(--primary-light);
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-full);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: var(--space-lg);
        }

        .hero-title {
            font-size: 2.75rem;
            font-weight: 800;
            margin-bottom: var(--space-md);
            background: linear-gradient(135deg, var(--text-primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto var(--space-xl);
        }

        .main-card {
            max-width: 700px;
            margin: 0 auto;
            padding: var(--space-xl);
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-md);
            margin-top: var(--space-xl);
        }

        .feature {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-md);
            background: var(--surface);
            border-radius: var(--radius-md);
        }

        .feature-icon {
            width: 45px;
            height: 45px;
            background: var(--primary-50);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: var(--primary-light);
            flex-shrink: 0;
        }

        .feature-text {
            font-size: 0.9rem;
        }

        .feature-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .feature-desc {
            color: var(--text-secondary);
            font-size: 0.8rem;
        }

        .file-list {
            margin-top: var(--space-md);
            padding: var(--space-md);
            background: var(--surface);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
        }

        .file-list-title {
            font-weight: 600;
            margin-bottom: var(--space-sm);
            color: var(--secondary);
        }

        .file-item {
            color: var(--text-secondary);
            padding: var(--space-xs) 0;
        }

        .privacy-notice {
            margin-top: var(--space-lg);
            padding: var(--space-md);
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            color: var(--secondary-light);
        }

        .privacy-notice i {
            margin-right: var(--space-sm);
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 1.75rem;
            }

            .hero-subtitle {
                font-size: 1rem;
            }

            .features {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="bg-animated"></div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay hidden">
        <div class="loading-spinner"></div>
        <div class="loading-text">Processando receitas...</div>
        <div class="loading-subtext">Por favor, aguarde enquanto analisamos o PDF</div>
    </div>

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
                    <a href="/" class="navbar-link active">Gerar Receita</a>
                    <a href="/admin/dashboard.php" class="navbar-link">Administração</a>
                    <a href="/eula.php" class="navbar-link">Termos de Uso</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        <div class="container">
            <section class="hero fade-in">
                <div class="hero-badge">
                    <i class="fas fa-sparkles"></i>
                    Ferramenta gratuita para UBS
                </div>
                <h1 class="hero-title">Transforme Receitas em Guias Visuais</h1>
                <p class="hero-subtitle">
                    Converta receitas do e-SUS em versões pictográficas acessíveis,
                    com ícones, QR codes de áudio e vídeos educativos.
                </p>
            </section>

            <div class="main-card glass-card slide-up">
                <form action="process.php" method="post" enctype="multipart/form-data" id="upload-form">
                    <div class="upload-area" id="upload-area">
                        <div class="upload-icon">
                            <i class="fas fa-cloud-arrow-up"></i>
                        </div>
                        <div class="upload-title">Arraste o PDF aqui ou clique para selecionar</div>
                        <div class="upload-subtitle">Aceita receitas do e-SUS (PDF) - múltiplos arquivos permitidos
                        </div>
                        <input type="file" id="receitas" name="receitas[]" accept=".pdf" multiple required
                            style="display:none;">
                    </div>

                    <div id="file-list" class="file-list hidden">
                        <div class="file-list-title"><i class="fas fa-file-pdf"></i> Arquivos selecionados:</div>
                        <div id="file-names"></div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg mt-3" id="submit-btn" disabled>
                        <i class="fas fa-wand-magic-sparkles"></i>
                        Processar Receitas
                    </button>
                </form>

                <div class="features">
                    <div class="feature">
                        <div class="feature-icon">
                            <i class="fas fa-icons"></i>
                        </div>
                        <div class="feature-text">
                            <div class="feature-title">Pictogramas</div>
                            <div class="feature-desc">Ícones facilitam compreensão</div>
                        </div>
                    </div>
                    <div class="feature">
                        <div class="feature-icon">
                            <i class="fas fa-qrcode"></i>
                        </div>
                        <div class="feature-text">
                            <div class="feature-title">QR Codes</div>
                            <div class="feature-desc">Áudio e vídeos educativos</div>
                        </div>
                    </div>
                    <div class="feature">
                        <div class="feature-icon">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="feature-text">
                            <div class="feature-title">Análise IA</div>
                            <div class="feature-desc">Verificação de interações</div>
                        </div>
                    </div>
                </div>

                <div class="privacy-notice">
                    <i class="fas fa-shield-halved"></i>
                    <strong>Privacidade garantida (LGPD):</strong>
                    O nome do paciente e o PDF nunca são armazenados. Todos os dados são processados em memória
                    e descartados imediatamente após a geração do documento.
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <p>&copy; <?php echo date("Y"); ?> Simplifica Receita - Victor Pedrosa @vped.2000</p>
                <p class="mt-1" style="opacity: 0.7;">
                    <a href="/eula.php">Termos de Uso</a> ·
                    <a href="https://github.com" target="_blank">GitHub</a>
                </p>
            </div>
        </div>
    </footer>

    <script>
        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('receitas');
        const fileList = document.getElementById('file-list');
        const fileNames = document.getElementById('file-names');
        const submitBtn = document.getElementById('submit-btn');
        const form = document.getElementById('upload-form');
        const loadingOverlay = document.getElementById('loading-overlay');

        // Click to upload
        uploadArea.addEventListener('click', () => fileInput.click());

        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('drag-over');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateFileList();
            }
        });

        // File input change
        fileInput.addEventListener('change', updateFileList);

        function updateFileList() {
            if (fileInput.files.length > 0) {
                fileNames.innerHTML = '';
                for (const file of fileInput.files) {
                    const div = document.createElement('div');
                    div.className = 'file-item';
                    div.innerHTML = `<i class="fas fa-file-pdf" style="color: #ef4444; margin-right: 8px;"></i> ${file.name}`;
                    fileNames.appendChild(div);
                }
                fileList.classList.remove('hidden');
                submitBtn.disabled = false;
                uploadArea.style.borderColor = 'var(--secondary)';
            } else {
                fileList.classList.add('hidden');
                submitBtn.disabled = true;
                uploadArea.style.borderColor = '';
            }
        }

        // Form submit
        form.addEventListener('submit', (e) => {
            if (fileInput.files.length > 0) {
                loadingOverlay.classList.remove('hidden');
            }
        });
    </script>
    <footer
        style="text-align: center; margin-top: 2rem; padding: 1rem; color: var(--text-secondary); font-size: 0.875rem;">
        <p>
            &copy; 2025 Simplifica Receita -
            <a href="https://github.com/vvictorpedrosavs-cloud/simplificareceita" target="_blank"
                style="color: var(--primary);">
                <i class="fab fa-github"></i> Repositório GitHub
            </a>
        </p>
    </footer>
</body>

</html>