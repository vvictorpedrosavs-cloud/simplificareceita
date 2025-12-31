<?php
/**
 * Setup Admin - Página Temporária
 * 
 * ATENÇÃO: EXCLUIR ESTE ARQUIVO APÓS CRIAR O ADMINISTRADOR!
 * 
 * Esta página permite criar o primeiro usuário administrador.
 * Por segurança, deve ser removida após o uso.
 */

require_once __DIR__ . '/config/database.php';

$error = '';
$success = '';
$setupAllowed = true;

// Verificar se já existe um admin
try {
    $stmt = executeQuery("SELECT COUNT(*) as total FROM usuarios");
    $result = $stmt->fetch();
    if ($result && $result['total'] > 0) {
        $setupAllowed = false;
    }
} catch (PDOException $e) {
    // Tabela pode não existir ainda - permitir setup
    $error = "Atenção: Execute o schema.sql primeiro para criar as tabelas.";
}

// Processar criação de admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $setupAllowed) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Validações
    if (empty($username) || empty($password) || empty($nome)) {
        $error = 'Preencha todos os campos obrigatórios.';
    } elseif (strlen($username) < 3) {
        $error = 'O usuário deve ter pelo menos 3 caracteres.';
    } elseif (strlen($password) < 8) {
        $error = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif ($password !== $password_confirm) {
        $error = 'As senhas não conferem.';
    } else {
        try {
            // Hash da senha
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Inserir admin
            insertAndGetId(
                "INSERT INTO usuarios (username, password_hash, nome, email) VALUES (?, ?, ?, ?)",
                [$username, $password_hash, $nome, $email]
            );

            $success = 'Administrador criado com sucesso! Agora você pode fazer login.';
            $success .= ' IMPORTANTE: Delete este arquivo (setup_admin.php) por segurança!';
            $setupAllowed = false;

        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                $error = 'Este usuário já existe.';
            } else {
                error_log("Setup admin error: " . $e->getMessage());
                $error = 'Erro ao criar administrador. Verifique se as tabelas foram criadas.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Inicial - Simplifica Receita</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --warning: #f59e0b;
            --error: #ef4444;
            --success: #22c55e;
            --background: #0f172a;
            --surface: rgba(255, 255, 255, 0.05);
            --glass-bg: rgba(255, 255, 255, 0.08);
            --glass-border: rgba(255, 255, 255, 0.15);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--background);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .setup-container {
            width: 100%;
            max-width: 500px;
        }

        .setup-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .warning-banner {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fcd34d;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .warning-banner i {
            margin-right: 0.5rem;
        }

        h1 {
            color: var(--text-primary);
            font-size: 1.5rem;
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 2rem;
            font-size: 0.875rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        label .required {
            color: var(--error);
        }

        input {
            width: 100%;
            padding: 0.875rem 1rem;
            background: var(--surface);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        input::placeholder {
            color: var(--text-secondary);
        }

        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.4);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
        }

        .blocked {
            text-align: center;
            color: var(--text-secondary);
            padding: 2rem;
        }

        .blocked i {
            font-size: 3rem;
            color: var(--success);
            margin-bottom: 1rem;
        }

        .blocked a {
            display: inline-block;
            margin-top: 1.5rem;
            color: var(--primary);
            text-decoration: none;
        }

        .blocked a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="setup-container">
        <div class="setup-card">
            <?php if (!$setupAllowed && empty($success)): ?>
                <div class="blocked">
                    <i class="fas fa-check-circle"></i>
                    <h1>Setup já realizado</h1>
                    <p>Um administrador já foi criado. Esta página pode ser removida.</p>
                    <a href="/auth/login.php"><i class="fas fa-sign-in-alt"></i> Ir para Login</a>
                </div>
            <?php else: ?>
                <div class="warning-banner">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>ATENÇÃO:</strong> Exclua este arquivo após criar o administrador!
                </div>

                <h1><i class="fas fa-user-shield"></i> Criar Administrador</h1>
                <p class="subtitle">Configure o primeiro usuário do sistema</p>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                    </div>
                    <div class="blocked">
                        <a href="/auth/login.php"><i class="fas fa-sign-in-alt"></i> Ir para Login</a>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <div class="form-group">
                            <label>Nome Completo <span class="required">*</span></label>
                            <input type="text" name="nome" placeholder="Seu nome" required>
                        </div>

                        <div class="form-group">
                            <label>E-mail</label>
                            <input type="email" name="email" placeholder="seu@email.com">
                        </div>

                        <div class="form-group">
                            <label>Usuário <span class="required">*</span></label>
                            <input type="text" name="username" placeholder="admin" minlength="3" required>
                        </div>

                        <div class="form-group">
                            <label>Senha <span class="required">*</span></label>
                            <input type="password" name="password" placeholder="Mínimo 8 caracteres" minlength="8" required>
                        </div>

                        <div class="form-group">
                            <label>Confirmar Senha <span class="required">*</span></label>
                            <input type="password" name="password_confirm" placeholder="Repita a senha" required>
                        </div>

                        <button type="submit" class="btn">
                            <i class="fas fa-user-plus"></i> Criar Administrador
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>