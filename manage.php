<?php
$videoFile = 'videos.json';
$renameFile = 'rename_map.json';

// Funções genéricas para ler e salvar JSON
function getJsonData($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}

function saveJsonData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Lógica para Vídeos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_video'])) {
    $videos = getJsonData($videoFile);
    $videos[] = ['medicamento' => $_POST['medicamento'], 'url' => $_POST['url']];
    saveJsonData($videoFile, $videos);
    header('Location: manage.php'); exit;
}
if (isset($_GET['delete_video'])) {
    $videos = getJsonData($videoFile);
    array_splice($videos, (int)$_GET['delete_video'], 1);
    saveJsonData($videoFile, $videos);
    header('Location: manage.php'); exit;
}

// Lógica para RENAME Map
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_rename'])) {
    $renames = getJsonData($renameFile);
    $renames[] = ['medicamento' => $_POST['medicamento'], 'finalidade' => $_POST['finalidade'], 'emoji' => $_POST['emoji']];
    saveJsonData($renameFile, $renames);
    header('Location: manage.php'); exit;
}
if (isset($_GET['delete_rename'])) {
    $renames = getJsonData($renameFile);
    array_splice($renames, (int)$_GET['delete_rename'], 1);
    saveJsonData($renameFile, $renames);
    header('Location: manage.php'); exit;
}

$videos = getJsonData($videoFile);
$renames = getJsonData($renameFile);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Cadastros</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <header class="bg-white shadow-md">
        <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="text-2xl font-bold text-blue-600"><i class="fas fa-prescription-bottle-medical"></i> Receita Visual</div>
            <a href="index.php" class="text-gray-600 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Gerar Receita</a>
        </nav>
    </header>

    <main class="container mx-auto px-6 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <div class="bg-white p-8 rounded-lg shadow-lg">
                <h2 class="text-2xl font-semibold text-gray-700 mb-4"><i class="fas fa-pills"></i> Gerenciar Finalidades (RENAME)</h2>
                <form action="manage.php" method="post" class="space-y-4 mb-6">
                    <input type="text" name="medicamento" placeholder="Palavra-chave (Ex: Dipirona)" required class="w-full rounded-md border-gray-300 p-2">
                    <input type="text" name="finalidade" placeholder="Finalidade (Ex: Dor / Febre)" required class="w-full rounded-md border-gray-300 p-2">
                    <input type="text" name="emoji" placeholder="Emoji (Ex: 🤒)" required class="w-full rounded-md border-gray-300 p-2">
                    <button type="submit" name="add_rename" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Adicionar Finalidade</button>
                </form>
                <div class="overflow-auto max-h-60">
                    <table class="min-w-full divide-y divide-gray-200">
                        <?php foreach ($renames as $index => $item): ?>
                        <tr>
                            <td class="py-2 text-2xl"><?= htmlspecialchars($item['emoji']) ?></td>
                            <td class="py-2 text-gray-800"><?= htmlspecialchars($item['medicamento']) ?><br><small class="text-gray-500"><?= htmlspecialchars($item['finalidade']) ?></small></td>
                            <td class="py-2 text-right"><a href="?delete_rename=<?= $index ?>" class="text-red-500 hover:text-red-700" onclick="return confirm('Confirmar exclusão?');">Excluir</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <div class="bg-white p-8 rounded-lg shadow-lg">
                <h2 class="text-2xl font-semibold text-gray-700 mb-4"><i class="fas fa-video"></i> Gerenciar Vídeos</h2>
                <form action="manage.php" method="post" class="space-y-4 mb-6">
                    <input type="text" name="medicamento" placeholder="Palavra-chave do Medicamento" required class="w-full rounded-md border-gray-300 p-2">
                    <input type="url" name="url" placeholder="URL do Vídeo (Youtube)" required class="w-full rounded-md border-gray-300 p-2">
                    <button type="submit" name="add_video" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Adicionar Vídeo</button>
                </form>
                 <div class="overflow-auto max-h-60">
                    <table class="min-w-full divide-y divide-gray-200">
                        <?php foreach ($videos as $index => $item): ?>
                        <tr>
                            <td class="py-2 text-gray-800"><?= htmlspecialchars($item['medicamento']) ?></td>
                            <td class="py-2 text-right"><a href="?delete_video=<?= $index ?>" class="text-red-500 hover:text-red-700" onclick="return confirm('Confirmar exclusão?');">Excluir</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>