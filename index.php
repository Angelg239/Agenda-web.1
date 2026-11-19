<?php
/* ════════════════════════════════════════════════════════
   index.php · Página principal (Mi agenda)
   ════════════════════════════════════════════════════════ */

// 1. Incluir el guardia de seguridad (protege la página y define $usuarioId y $usuarioNombre)
require __DIR__ . '/partes/sesion.php';

// ==========================================
// FUNCIONES AUXILIARES
// ==========================================

// Limpia un texto antes de mostrarlo (evita XSS)
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

// '2026-10-08'  →  '08/10/2026'
function formatearFecha(string $fecha): string
{
    return date('d/m/Y', strtotime($fecha));
}

// 'ocio'  →  'Ocio / Deporte'
function nombreCategoria(string $clave): string
{
    $nombres = [
        'trabajo'  => 'Trabajo',
        'personal' => 'Personal',
        'estudio'  => 'Estudio',
        'deporte'  => 'Deporte',
        'ocio'     => 'Ocio / Deporte',
    ];
    return $nombres[$clave] ?? $clave;   // si la clave no existe, devuelve la original
}

// Recibe un evento y devuelve el HTML de su tarjeta
function mostrarEvento(array $ev): string
{
    $catClave = strtolower($ev['categoria']);
    
    // Mapa de colores e iconos por categoría
    $estilosCategorias = [
        'trabajo'  => ['bg' => '#E0F2FE', 'color' => '#0369A1', 'icono' => '💼'],
        'personal' => ['bg' => '#FCE7F3', 'color' => '#BE185D', 'icono' => '👤'],
        'estudio'  => ['bg' => '#FEF3C7', 'color' => '#B45309', 'icono' => '📚'],
        'deporte'  => ['bg' => '#DCFCE7', 'color' => '#15803D', 'icono' => '⚽'],
        'ocio'     => ['bg' => '#DCFCE7', 'color' => '#15803D', 'icono' => '⚽'],
    ];
    
    $catInfo = $estilosCategorias[$catClave] ?? ['bg' => '#E9E1F0', 'color' => '#5B21B6', 'icono' => '📌'];
    $id = (int) $ev['id'];

    $html  = '<div class="evento-item" style="background: #ffffff; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1.2rem; text-align: left; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">';
    
    // Cabecera de la tarjeta (Título + Badge + Acciones)
    $html .= '<div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.6rem;">';
    $html .= '<h3 style="margin: 0; font-size: 1.15rem; color: #1E293B; font-weight: 600;">' . e($ev['titulo']) . '</h3>';
    
    $html .= '<div style="display: flex; align-items: center; gap: 0.5rem;">';
    $html .= '<span class="badge" style="background: ' . $catInfo['bg'] . '; color: ' . $catInfo['color'] . '; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; white-space: nowrap;">'
           . $catInfo['icono'] . ' ' . e(nombreCategoria($catClave)) . '</span>';
    
    // Botón Editar -> Redirige a registrar.php pasando el ID
    $html .= '<a href="registrar.php?id=' . $id . '" '
           . 'style="background: #F1F5F9; color: #475569; text-decoration: none; font-size: 0.8rem; padding: 0.3rem 0.6rem; border-radius: 6px; font-weight: 600;" '
           . 'title="Editar evento">✏️ Editar</a>';

    // Botón / Icono de Borrar
    $html .= '<a href="index.php?eliminar=' . $id . '" '
           . 'onclick="return confirm(\'¿Seguro que deseas eliminar este evento?\');" '
           . 'style="color: #94A3B8; text-decoration: none; font-size: 1.1rem; padding: 0.2rem 0.4rem; border-radius: 4px;" '
           . 'title="Eliminar evento">🗑️</a>';
    $html .= '</div></div>';

    // Fecha y Hora
    $html .= '<div style="display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.88rem; color: #64748B; margin-bottom: 0.5rem;">';
    $html .= '<span>📅 ' . e(formatearFecha($ev['fecha'])) . '</span>';
    if (!empty($ev['hora'])) {
        $html .= '<span>⏰ ' . e(substr($ev['hora'], 0, 5)) . ' hs</span>';
    }
    $html .= '</div>';

    // Descripción (opcional)
    if (!empty($ev['descripcion'])) {
        $html .= '<p style="margin: 0.6rem 0 0 0; font-size: 0.9rem; color: #334155; background: #F8FAFC; padding: 0.6rem 0.8rem; border-radius: 8px; border-left: 3px solid #7C3AED;">'
               . nl2br(e($ev['descripcion'])) . '</p>';
    }

    $html .= '</div>';
    return $html;
}

// ==========================================
// LÓGICA PRINCIPAL
// ==========================================

$guardado = isset($_GET['ok']);
$eliminado = isset($_GET['deleted']);
$errorBD = false;
$eventos = [];
$categorias = [];

// Procesar eliminación asegurando que el evento pertenezca al usuario actual
if (isset($_GET['eliminar'])) {
    $idEliminar = (int)$_GET['eliminar'];
    try {
        require 'conexion.php';
        $stmt = $mysqli->prepare("DELETE FROM eventos WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param("ii", $idEliminar, $usuarioId);
        $stmt->execute();
        $stmt->close();
        $mysqli->close();
        header('Location: index.php?deleted=1');
        exit;
    } catch (mysqli_sql_exception $e) {
        $errorBD = true;
    }
}

// Procesar búsqueda, categorías y consulta dinámica con prepared statements
try {
    require 'conexion.php';

    $q = mb_substr(trim($_GET['q'] ?? ''), 0, 60);        // texto, máximo 60 caracteres
    $categoriaId = (int) ($_GET['categoria'] ?? 0);        // 0 = todas

    // Las categorías del filtro
    $categorias = $mysqli->query('SELECT id, nombre FROM categorias ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
    $idsValidos = array_map('intval', array_column($categorias, 'id'));
    if (!in_array($categoriaId, $idsValidos, true)) {
        $categoriaId = 0;                                  // una categoría inventada = todas
    }
    $buscando = ($q !== '' || $categoriaId !== 0);

    $condiciones = ['e.usuario_id = ?'];                        // SIEMPRE: solo mis eventos
    $tipos       = 'i';
    $valores     = [$usuarioId];

    if ($q !== '') {
        $patron = '%' . addcslashes($q, '%_\\') . '%';
        $condiciones[] = '(e.titulo LIKE ? OR e.descripcion LIKE ?)';
        $tipos      .= 'ss';
        $valores[]   = $patron;
        $valores[]   = $patron;
    }
    if ($categoriaId !== 0) {
        $condiciones[] = 'e.categoria_id = ?';
        $tipos      .= 'i';
        $valores[]   = $categoriaId;
    }

    $sql = 'SELECT e.id, e.titulo, e.fecha, e.hora, e.descripcion,
                  c.nombre AS categoria
              FROM eventos e
              JOIN categorias c ON c.id = e.categoria_id
             WHERE ' . implode(' AND ', $condiciones) . '
             ORDER BY e.fecha, e.hora';
             
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($tipos, ...$valores);
    $stmt->execute();
    $eventos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $mysqli->close();
} catch (mysqli_sql_exception $e) {
    $errorBD = true;
}

// Contadores por categoría
$conteoCategorias = ['trabajo' => 0, 'personal' => 0, 'estudio' => 0, 'deporte' => 0, 'ocio' => 0];
foreach ($eventos as $ev) {
    $cat = strtolower($ev['categoria']);
    if (isset($conteoCategorias[$cat])) {
        $conteoCategorias[$cat]++;
    }
}

// ==========================================
// CONFIGURACIÓN DEL CALENDARIO
// ==========================================
$mesActual = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$anioActual = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

$mesAnterior = $mesActual - 1;
$anioAnterior = $anioActual;
if ($mesAnterior < 1) { $mesAnterior = 12; $anioAnterior--; }

$mesSiguiente = $mesActual + 1;
$anioSiguiente = $anioActual;
if ($mesSiguiente > 12) { $mesSiguiente = 1; $anioSiguiente++; }

$nombreMeses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

$fechasConEventos = [];
foreach ($eventos as $ev) {
    $fechasConEventos[$ev['fecha']] = true;
}

$primerDiaTimestamp = mktime(0, 0, 0, $mesActual, 1, $anioActual);
$totalDiasMes = date('t', $primerDiaTimestamp);
$diaSemanaInicio = date('N', $primerDiaTimestamp);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="css/estilos.css" rel="stylesheet">
    <title>Mi agenda · AgendaWeb</title>
    <style>
        .calendar-container { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .calendar-header h2 { margin: 0; font-size: 1.1rem; color: #1E293B; }
        .calendar-header a { text-decoration: none; background: #E2E8F0; color: #334155; padding: 0.3rem 0.6rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; text-align: center; }
        .calendar-day-name { font-size: 0.75rem; font-weight: 700; color: #64748B; padding: 0.4rem 0; }
        .calendar-cell { padding: 0.5rem 0; font-size: 0.85rem; border-radius: 6px; color: #334155; background: #FFFFFF; border: 1px solid #F1F5F9; }
        .calendar-cell.empty { background: transparent; border: none; }
        .calendar-cell.has-event { background: #E0F2FE; color: #0369A1; font-weight: bold; border-color: #BAE6FD; }
        .calendar-cell.today { border: 2px solid #7C3AED; }
    </style>
</head>
<body>
<div class="card" style="max-width: 650px; margin: 2rem auto; padding: 2rem;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 2px solid #F1F5F9; padding-bottom: 1rem;">
        <div>
            <h1 class="card__title" style="margin: 0; font-size: 1.8rem;">Mi agenda</h1>
            <p class="page__subtitle" style="margin: 0.2rem 0 0 0; color: #6E617A; font-size: 0.9rem;">
                <?php if ($buscando): ?>
                    <?= count($eventos) ?> resultado<?= count($eventos) === 1 ? '' : 's' ?><?= $q !== '' ? ' para «' . e($q) . '»' : '' ?>
                <?php else: ?>
                    <?= count($eventos) ?> eventos registrados
                <?php endif; ?>
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <a href="registrar.php" class="btn-primary" style="text-decoration: none; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 600; white-space: nowrap;">+ Nuevo evento</a>
            <a href="logout.php" class="btn-primary" style="text-decoration: none; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 600; white-space: nowrap; background: #DC2626;">Salir</a>
        </div>
    </div>

    <!-- Mensajes de Estado -->
    <?php if ($guardado): ?>
        <div class="alert alert--ok" role="status">&#9989; Evento guardado correctamente.</div>
    <?php endif; ?>

    <?php if ($eliminado): ?>
        <div class="alert alert--ok" role="status" style="background-color: #FEF2F2; color: #991B1B; border: 1px solid #FCA5A5;">
            🗑️ Evento eliminado de la agenda.
        </div>
    <?php endif; ?>

    <?php if ($errorBD): ?>
        <div class="alert alert--error">No se pudieron procesar los datos de la agenda.</div>
    <?php endif; ?>

    <!-- CALENDARIO VISUAL EN EL INDEX -->
    <div class="calendar-container">
        <div class="calendar-header">
            <a href="index.php?mes=<?= $mesAnterior ?>&anio=<?= $anioAnterior ?>">&laquo; Anterior</a>
            <h2><?= $nombreMeses[$mesActual] ?> <?= $anioActual ?></h2>
            <a href="index.php?mes=<?= $mesSiguiente ?>&anio=<?= $anioSiguiente ?>">Siguiente &raquo;</a>
        </div>
        <div class="calendar-grid">
            <div class="calendar-day-name">Lun</div>
            <div class="calendar-day-name">Mar</div>
            <div class="calendar-day-name">Mié</div>
            <div class="calendar-day-name">Jue</div>
            <div class="calendar-day-name">Vie</div>
            <div class="calendar-day-name">Sáb</div>
            <div class="calendar-day-name">Dom</div>

            <?php
            for ($i = 1; $i < $diaSemanaInicio; $i++) {
                echo '<div class="calendar-cell empty"></div>';
            }

            for ($dia = 1; $dia <= $totalDiasMes; $dia++) {
                $mesStr = str_pad($mesActual, 2, '0', STR_PAD_LEFT);
                $diaStr = str_pad($dia, 2, '0', STR_PAD_LEFT);
                $fechaFormato = "$anioActual-$mesStr-$diaStr";

                $clases = 'calendar-cell';
                if (isset($fechasConEventos[$fechaFormato])) {
                    $clases .= ' has-event';
                }
                if ($fechaFormato === date('Y-m-d')) {
                    $clases .= ' today';
                }

                echo '<div class="' . $clases . '">' . $dia . '</div>';
            }
            ?>
        </div>
    </div>

    <!-- Resumen rápido de categorías -->
    <?php if (!empty($eventos)): ?>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; background: #F8FAFC; padding: 0.8rem; border-radius: 10px;">
            <span style="font-size: 0.85rem; color: #64748B; width: 100%; font-weight: 600; margin-bottom: 0.2rem;">Resumen por categoría:</span>
            <span style="font-size: 0.8rem; background: #E0F2FE; color: #0369A1; padding: 0.2rem 0.5rem; border-radius: 6px;">💼 Trabajo: <?= $conteoCategorias['trabajo'] ?></span>
            <span style="font-size: 0.8rem; background: #FCE7F3; color: #BE185D; padding: 0.2rem 0.5rem; border-radius: 6px;">👤 Personal: <?= $conteoCategorias['personal'] ?></span>
            <span style="font-size: 0.8rem; background: #FEF3C7; color: #B45309; padding: 0.2rem 0.5rem; border-radius: 6px;">📚 Estudio: <?= $conteoCategorias['estudio'] ?></span>
            <span style="font-size: 0.8rem; background: #DCFCE7; color: #15803D; padding: 0.2rem 0.5rem; border-radius: 6px;">⚽ Deporte: <?= $conteoCategorias['deporte'] + $conteoCategorias['ocio'] ?></span>
        </div>
    <?php endif; ?>

    <!-- FORMULARIO DE BÚSQUEDA (Debajo del resumen por categoría) -->
    <form class="buscador" method="get" action="index.php" role="search">
        <input class="form-input" type="search" name="q" value="<?= e($q) ?>" maxlength="60"
               placeholder="Buscar en título o descripción…" aria-label="Buscar eventos">
        <select class="form-select" name="categoria" aria-label="Filtrar por categoría">
            <option value="0">Todas las categorías</option>
            <?php foreach ($categorias as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>" <?= $categoriaId === (int) $cat['id'] ? 'selected' : '' ?>>
                    <?= e($cat['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-primary">Buscar</button>
        <?php if ($buscando): ?>
            <a href="index.php" class="btn-secondary">Limpiar</a>
        <?php endif; ?>
    </form>

    <!-- Lista de eventos guardados con estados vacíos diferenciados -->
    <div class="eventos-container">
        <?php if (empty($eventos) && $buscando): ?>
            <div class="empty-state" style="padding: 2.5rem 1rem; text-align: center; color: #6E617A; background: #F8FAFC; border-radius: 12px; border: 2px dashed #E2E8F0;">
                <p style="margin: 0; font-size: 1.1rem; font-weight: 500;">Sin resultados para tu búsqueda.</p>
                <p style="margin: 0.5rem 0 1rem 0; font-size: 0.9rem; color: #94A3B8;">Prueba con otros términos o limpia los filtros.</p>
                <a href="index.php" class="btn-secondary">Ver todos mis eventos</a>
            </div>
        <?php elseif (empty($eventos)): ?>
            <div style="padding: 2.5rem 1rem; text-align: center; color: #6E617A; background: #F8FAFC; border-radius: 12px; border: 2px dashed #E2E8F0;">
                <p style="margin: 0; font-size: 1.1rem; font-weight: 500;">📅 Aún no tienes eventos programados.</p>
                <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; color: #94A3B8;">Haz clic en el botón superior para agregar tu primer evento.</p>
            </div>
        <?php else: ?>
            <section class="card-list" aria-label="Lista de eventos" style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($eventos as $ev): ?>
                    <?= mostrarEvento($ev) ?>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
</div>

<?php if ($guardado || $eliminado): ?>
<script>
    setTimeout(function() {
        const alerta = document.querySelector('.alert');
        if (alerta) {
            alerta.style.transition = 'opacity 0.5s ease';
            alerta.style.opacity = '0';
            setTimeout(function() {
                alerta.remove();
            }, 500);
        }
    }, 3000);
</script>
<?php endif; ?>
</body>
</html>