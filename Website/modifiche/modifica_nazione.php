<?php
// Abilita la visualizzazione degli errori durante lo sviluppo
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Collegamento al database
require_once '../Utilities/dbconnect.php';

// Verifica la connessione al database
if (!$pdo) {
    die("Errore nella connessione al database.");
}

// Recupera il nome della nazione dalla query string
$nome_nazione = $_GET['nome'] ?? null;

if (!$nome_nazione) {
    die("Errore: Nome della nazione non fornito.");
}

try {
    // Recupera i dati della nazione dal database
    $query = "SELECT * FROM nazione WHERE nome = :nome";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':nome', $nome_nazione, PDO::PARAM_STR);
    $stmt->execute();
    $nazione = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$nazione) {
        die("Errore: Nazione non trovata.");
    }
} catch (PDOException $e) {
    die("Errore nella query: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ottieni i dati inviati dal form
    $nome = trim($_POST['nome'] ?? '');
    $codice_iso = trim($_POST['codice_iso'] ?? '');
    $codice_iso2 = trim($_POST['codice_iso2'] ?? '');
    $continente = trim($_POST['continente'] ?? '');
    $capitale = trim($_POST['capitale'] ?? '');

    // Creazione della cartella per l'immagine
    $cartella = "../Photo/nazione/" . strtolower(str_replace(' ', '_', $nome)) . "/";
    if (!is_dir($cartella)) {
        if (!mkdir($cartella, 0777, true)) {
            die("Errore: impossibile creare la cartella per la bandiera.");
        }
    }

    // Controllo se è stato caricato un file
    if (isset($_FILES['bandiera']) && $_FILES['bandiera']['error'] === UPLOAD_ERR_OK) {
        $nome_file = basename($_FILES['bandiera']['name']);
        $percorso_destinazione = $cartella . $nome_file;

        // Controlla se il file è un'immagine
        $estensione = strtolower(pathinfo($percorso_destinazione, PATHINFO_EXTENSION));
        $tipi_consentiti = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($estensione, $tipi_consentiti)) {
            die("Errore: Formato file non valido. Sono ammessi solo JPG, PNG, GIF, WebP.");
        }

        // Sposta il file nella cartella di destinazione
        if (move_uploaded_file($_FILES['bandiera']['tmp_name'], $percorso_destinazione)) {
            $bandiera_url = "/Photo/nazione/" . strtolower(str_replace(' ', '_', $nome)) . "/" . $nome_file;
        } else {
            die("Errore nel caricamento del file. Verifica i permessi della directory.");
        }
    } else {
        $bandiera_url = $nazione['bandiera'] ?? null; // Mantieni l'immagine attuale se non viene caricata una nuova
    }

    // ID gruppo modifica per tracciabilità
    $id_gruppo_modifica = rand(1000, 9999);

    // Inseriamo i dati nella tabella `modifiche_in_sospeso` per tutti i campi
    try {
        $query = "INSERT INTO modifiche_in_sospeso (id_gruppo_modifica, tabella_destinazione, campo_modificato, valore_nuovo, valore_vecchio, stato, autore) 
                  VALUES (:id_gruppo_modifica, 'nazione', :campo_modificato, :valore_nuovo, :valore_vecchio, 'In attesa', 'admin')";
        $stmt = $pdo->prepare($query);

        $campi = [
            'nome' => [$nome, $nazione['nome'] ?? null],
            'codice_iso' => [$codice_iso, $nazione['codice_iso'] ?? null],
            'codice_iso2' => [$codice_iso2, $nazione['codice_iso2'] ?? null],
            'continente' => [$continente, $nazione['continente'] ?? null],
            'capitale' => [$capitale, $nazione['capitale'] ?? null],
            'bandiera' => [$bandiera_url, $nazione['bandiera'] ?? null],
        ];

        foreach ($campi as $campo => [$valore_nuovo, $valore_vecchio]) {
            $stmt->bindParam(':id_gruppo_modifica', $id_gruppo_modifica);
            $stmt->bindParam(':campo_modificato', $campo);
            $stmt->bindParam(':valore_nuovo', $valore_nuovo);
            $stmt->bindParam(':valore_vecchio', $valore_vecchio);
            $stmt->execute();
        }

        echo "Le modifiche alla nazione sono state proposte con successo. In attesa di approvazione.";
    } catch (PDOException $e) {
        echo "Errore nell'inserimento della modifica: " . $e->getMessage();
    }
}

include '../header.html'; // Include l'header
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica Nazione</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-4">
    <h1 class="mb-3">Modifica Nazione</h1>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="nome" class="form-label">Nome della Nazione</label>
            <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($nazione['nome'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label for="codice_iso" class="form-label">Codice ISO (Alpha-3)</label>
            <input type="text" class="form-control" id="codice_iso" name="codice_iso" value="<?= htmlspecialchars($nazione['codice_iso'] ?? '') ?>" maxlength="3">
        </div>
        <div class="mb-3">
            <label for="codice_iso2" class="form-label">Codice ISO (Alpha-2)</label>
            <input type="text" class="form-control" id="codice_iso2" name="codice_iso2" value="<?= htmlspecialchars($nazione['codice_iso2'] ?? '') ?>" maxlength="2">
        </div>
        <div class="mb-3">
            <label for="continente" class="form-label">Continente</label>
            <input type="text" class="form-control" id="continente" name="continente" value="<?= htmlspecialchars($nazione['continente'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="capitale" class="form-label">Capitale</label>
            <input type="text" class="form-control" id="capitale" name="capitale" value="<?= htmlspecialchars($nazione['capitale'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="bandiera" class="form-label">Carica Nuova Bandiera</label>
            <input type="file" class="form-control" id="bandiera" name="bandiera" accept="image/*">
            <?php if (!empty($nazione['bandiera'])): ?>
                <p>Bandiera attuale: <img src="<?= htmlspecialchars($nazione['bandiera']) ?>" alt="Bandiera" style="max-width: 100px; max-height: 50px;"></p>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary">Proponi Modifiche</button>
        <a href="../nazioni.php" class="btn btn-secondary">Torna a nazione</a>
    </form>
</body>
</html>