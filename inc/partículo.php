<?php defined('TIPO') or die();
$cuerpo .=
'<article class="'.TIPO[$entrada['tipo']].'">';
$cuerpo .= '<footer>'.$timetag.'</footer>';
if(!empty($entrada['resumen'])):
	$cuerpo .= '<p>'.$entrada['resumen'].'</p>';
endif;
//Reemplaza marcadores de imagenes posibles con las imagenes
if(!empty($entrada['fotos'])):
	foreach($entrada['fotos'] as $k=>$f):
		$busca = '[FOTO]';
		$pos = strpos($entrada['contenido'], $busca);
		if($pos !== false):
			$entrada['contenido'] = substr_replace($entrada['contenido'], $f['src'], $pos, strlen($busca));
		endif;
	endforeach;
	$cuerpo .= str_replace($busca, '', $entrada['contenido']);
endif;
//Si existen más fotos que marcadores, crea una galería
$cuerpo .= '</article>';
?>