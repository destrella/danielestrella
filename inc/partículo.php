<?php defined('TIPO') or die();
$cuerpo .=
'<article class="'.TIPO[$entrada['tipo']].'">';

if(!empty($entrada['título'])):
	$cuerpo .= '<h2>'.$entrada['título'].'</h2>';
endif;

$cuerpo .= '<footer>'.$timetag.'</footer>';

if(!empty($entrada['resumen'])):
	$cuerpo .= '<p>'.$entrada['resumen'].'</p>';
endif;

if(!empty($entrada['fotos'])):
	$cuerpo .= 
	'<div class="fotoDestacada">'.
	imgtag($entrada['fotos'][0], $entrada, '100vw').
	'</div>';
	unset($entrada['fotos'[0]]);

	foreach($entrada['fotos'] as $k=>$f):
		$busca = '[FOTO]';
		$pos = strpos($entrada['contenido'], $busca);
		if($pos !== false):
			$fotains =  imgtag($f, $entrada);
			$entrada['contenido'] = substr_replace($entrada['contenido'], $fotains, $pos, strlen($busca));
			unset($entrada['fotos'][$k]);
		else:
			break;
		endif;
	endforeach;
	$cuerpo .= str_replace($busca, '', $entrada['contenido']);
else:
	$cuerpo .= $entrada['contenido'];
endif;
//Si aún quedan fotos, crea una galería
if(!empty($entrada['fotos'])):
	include 'pgalería-fotos.php';
endif;

$cuerpo .= '</article>';
?>