<?php defined('TIPO') or die();
$cuerpo .=
'<article class="'.TIPO[$entrada['tipo']].'">'.
'<div>';

if($resumen && !empty($entrada['resumen'])):
	$cuerpo .= '<p>'.$entrada['resumen'].'</p>';
endif;
if(!empty($entrada['contenido'])):
	$cuerpo .= $entrada['contenido'];
endif;
$cuerpo .=
'</div>'.
'<header>'.
'<h2>'.$timetag.'</h2>'.
'</header>'.
'</article>';
?>