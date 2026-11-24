<?php
$anagrafica = new DOMDocument();
$anagrafica->load(__DIR__.'/cinema.xml', LIBXML_NONET);
$indirizzoCinema = $anagrafica->getElementsByTagName('indirizzo')->item(0)->textContent;
?>
<footer class="piede-artemis">
<p>Ci trovi in <?=h($indirizzoCinema)?><br>Vieni a trovarci! | <a href="admin.php">Area Riservata Admin</a></p>
<hr>
<p>&copy;Cinema Artemis Copyright 2026. All rights reserved | P.IVA: 01234567890</p>
</footer>
