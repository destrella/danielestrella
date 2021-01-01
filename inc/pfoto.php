<?php defined('TIPO') or die();
//tamaños imgpile: foto.jpg, foto.md.jpg, foto.th.jpg
$cuerpo .=
'<article class="'.TIPO[$entrada['tipo']].'">';
if(!empty($entrada['fotos'][0]['src'])):
	$cuerpo .= imgtag($entrada['fotos'][0], $entrada);
else:
	$cuerpo .= '<img src="'.NOFOTO.'" alt="">';
endif;
$cuerpo .=
'<div class="contenido">'.
'<h2>'.$entrada['título'].'</h2>';
if($resumen && !empty($entrada['resumen'])):
	$cuerpo .= '<p>'.$entrada['resumen'].'</p>';
endif;
if(!empty($entrada['contenido'])):
	$cuerpo .= $entrada['contenido'];
endif;
$cuerpo .= '<footer>'.$timetag.'</footer></div></article>';
?>