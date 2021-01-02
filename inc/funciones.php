<?php
$_ = function ($val){return $val;};
function normalizaEntrada($entrada, $t){
	$blank =
	[
	"t"=> 0,
	"tipo"=>0,
	"título"=>'',
	"resumen"=>'',
	"contenido"=>'',
	"ubicación"=>'',
	"idioma"=>'es',
	"fotos"=>[[
		'src' => '',
		'set'=>[
			'avif'=>'',
			'webp'=>'',
			'jpg'=>''
		],
		'tan'=>0,
		'tal'=>0,
		'alt'=>'',
		'dsc'=>'',
		'lnk'=>''
		]],
	"videos"=>[[
		'iframe' => '',
		'html' => [
			//Puede tener múltiple <source>
			['src' => '','mime' => '']
		],
		'dsc'=>''
		]],
		'inlinecs'=>''
	];
	$entrada['t'] = $t;
	$entrada = array_merge($blank, $entrada);

	//Crea una fecha legible
	$entrada['fechaLarga'] = DÍA[date('N', $entrada['t'])].', '.date('j', $entrada['t']).' de '.MES[date('n', $entrada['t'])].date(' Y', $entrada['t']).' a las '.date('H:i', $entrada['t']).' GMT'.date('P', $entrada['t']);

	//Si no existe un título, usa la fecha legible
	$entrada['título'] = trim($entrada['título']);
	if(empty($entrada['título'])):
		$entrada['título'] = $entrada['fechaLarga'];
		$entrada['slug'] = ARC.date('dHis', $entrada['t']);
	else:
		$entrada['slug'] = ARC.date('Y/m/', $entrada['t']).slug($entrada['título']);
	endif;

	//echo '['.$entrada['t'].'] '.$entrada['slug'].PHP_EOL;
	return $entrada;
}
function minifyhtml($html)
{
	$b = ['/\>[^\S ]+/s', '/[^\S ]+\</s', '/\s+/'];
	$r = ['>', '<', ' '];
	return trim(preg_replace($b,$r, $html));
}
function _r($msg, $l){
	$msg .= (MSGHTM?'<br>':PHP_EOL);
	if(DEBUG):
		return '[L-'.$l.'] '.$msg;
	else:
		return $msg;
	endif;
}
function imgtag($f, $entrada){
	foreach($f as $attr=>$val):
		if(is_string($val)):
			$f[$attr] = trim($val);
		endif;
	endforeach;

	switch($entrada['tipo']):
		case 1://Foto
		case 2://Diapositivas
			$sizes = '(max-width:960px) 90vw, calc(90% - 300px)';
			break;
		case 'fs'://Pantalla completa
			$sizes = '100vw';
			break;
		case 3://Galería
		default:
			$sizes = '320px';
			break;
	endswitch;

	$source = '';
	//elemento "source"
	foreach($f['set'] as $formato=>$imgsrc):
		//Formato jpg es sólo último recurso
		if($formato == 'jpg'
		|| empty($imgsrc)):
			continue;
		endif;
		$source .=
		'<source'.
		' type="image/'.$formato.'"'.
		' srcset="'.cloudtohotlink($f['set'][$formato]).'"'.
		' sizes="'.$sizes.'"'.
		'>';
	endforeach;

	//Atributos del elemento img
	$attrs = '';
	if(!empty($f['set']['jpg'])):
		$attrs .=
		' srcset="'.cloudtohotlink($f['set']['jpg']).'"'.
		' sizes="'.$sizes.'"';
	endif;
	if(!empty($f['alt'])):
		$attrs .= ' alt="'.$f['alt'].'" title="'.$f['alt'].'"';
	else:
		$attrs .= ' alt=""';
	endif;
	if(!empty($f['tan'])):
		$attrs .= ' width="'.$f['tan'].'"';
	endif;
	if(!empty($f['tal'])):
		$attrs .= ' height="'.$f['tal'].'"';
	endif;
	$attrs .= ' loading="lazy"';

	$imgtag = '<img src="'.cloudtohotlink($f['src']).'"'.$attrs.'>';
	if(!empty($source)):
		$imgtag = '<picture>'.$source.$imgtag.'</picture>';
	endif;

	return $imgtag;
}
//Funciona para google drive, onedrive y dropbox
function cloudtohotlink($lnk){
	if(strpos($lnk, 'onedrive.live.com') !== false):
		if(strpos($lnk, 'iframe') !== false):
			preg_match('/src="([^"]+)"/', $lnk, $match);
			$lnk = $match[1];
		endif;
		$quita = ['/embed?'];
		$pone = ['/download?'];
	elseif(strpos($lnk, 'drive.google.com') !== false):
		$quita = ['file/d/', '/view?usp=sharing'];
		$pone = ['uc?id='];
	elseif(strpos($lnk, 'dropbox.com') !== false):
		$quita = ['/s/', '?dl=0', '?dl=1', '?raw=1'];
		$pone = ['/s/dl/'];
	else:
		return $lnk;
	endif;
	return str_replace($quita, $pone, $lnk);
}
function generaResumen($cont){
	if(empty($cont)):
		return '';
	endif;
	$cont = minifyhtml(strip_tags($cont));
	if(strlen($cont) <= 160):
		return $cont;
	endif;
	$cont = explode(' ', $cont);
	$resumen = '';
	foreach($cont as $c):
		if(strlen($resumen.' '.$c) >= 159):
			return trim($resumen).'…';
		else:
			$resumen .= ' '.$c;
		endif;
	endforeach;
}
?>