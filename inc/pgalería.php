<?php defined('TIPO') or die();
$cuerpo .=
'<article class="'.TIPO[$entrada['tipo']].'">';
if(!empty($entrada['título'])):
	$cuerpo .=
	'<header>'.
	'<h2>'.$entrada['título'].'</h2>'.
	$timetag.
	'</header>';
endif;
if(!empty($entrada['resumen'])
&& '<p>'.$entrada['resumen'].'</p>' != $entrada['contenido']):
	$cuerpo .= '<p>'.$entrada['resumen'].'</p>';
endif;
if(!empty($entrada['contenido'])):
	$cuerpo .= $entrada['contenido'];
endif;

include 'pgalería-fotos.php';
$cuerpo .= '</article>';
?>