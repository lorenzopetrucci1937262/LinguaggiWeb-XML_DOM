<header class="testata"><a class="marchio" href="index.php">Cinema Artemis</a>
<nav aria-label="Menu principale">
<?php foreach (['index.php'=>'Home','programmazione.php'=>'Programmazione','film.php'=>'Catalogo','prezzi.php'=>'Sale e prezzi','area.php'=>'Area personale'] as $pagina=>$voce): ?>
<a href="<?=$pagina?>" <?=basename($_SERVER['SCRIPT_NAME']) === $pagina ? 'aria-current="page"' : ''?>><?=$voce?></a>
<?php endforeach; ?></nav></header>
