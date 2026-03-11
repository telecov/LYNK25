<?php
// Si no existe versión, evita error
if (!isset($system_version)) {
    $system_version = 'N/D';
}

// Nombre del producto (para reutilizar en otros dashboards)
if (!isset($product_name)) {
    $product_name = 'LYNK25';
}
?>

<footer class="bg-dark text-white text-center py-3 mt-auto small">
    🚀 <?php echo htmlspecialchars($product_name); ?> Dashboard v<?php echo htmlspecialchars($system_version); ?>
    · Desarrollado por <strong>Telecoviajero – CA2RDP</strong>
    · © <?php echo date('Y'); ?> Telecoviajero
    · <a href="https://github.com/telecov/<?php echo urlencode($product_name); ?>" 
         target="_blank" 
         class="text-info text-decoration-none">
         GitHub
      </a>
</footer>

