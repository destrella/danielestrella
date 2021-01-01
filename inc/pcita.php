<?php defined('TIPO') or die();
$cuerpo .=
'<article class="'.TIPO[$entrada['tipo']].'">';
if(!empty($entrada['resumen'])):
	$cuerpo .= '<p>'.$entrada['resumen'].'</p>';
endif;
if(!empty($entrada['contenido'])):
	$cuerpo .= $entrada['contenido'];
endif;
$cuerpo .= '</article>';
?>